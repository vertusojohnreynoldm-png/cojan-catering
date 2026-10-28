<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Order;
use App\Services\Ratings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rate an order: stars for every item (or the package), three service
 * scores, an optional comment and photo. One rating per order, editable for
 * a few days (Ratings). Verified + not-blocked + rate limit: route middleware.
 */
class FeedbackController extends Controller
{
    public function create($orderId)
    {
        $order = $this->ownOrder($orderId);
        $status = Ratings::status($order);

        if (!in_array($status, [Ratings::OPEN, Ratings::EDITABLE], true)) {
            return redirect()->route('customer.orders.show', $order->id)->with('error', $this->refusal($order, $status));
        }

        return view('customer.feedback', [
            'order' => $order,
            'feedback' => $order->feedback?->load('items'),
            'hasService' => $this->hasService($order),
            'riderName' => $order->isBooking() ? null : $order->delivery?->personnel?->name,
            'deadline' => $status === Ratings::OPEN ? Ratings::rateDeadline($order) : Ratings::editDeadline($order),
        ]);
    }

    public function store(Request $request, $orderId)
    {
        return $this->save($request, $orderId, editing: false);
    }

    public function update(Request $request, $orderId)
    {
        return $this->save($request, $orderId, editing: true);
    }

    private function save(Request $request, $orderId, bool $editing)
    {
        $order = $this->ownOrder($orderId);
        $data = $this->validated($request, $order);

        $newPhoto = $request->hasFile('photo')
            ? $request->file('photo')->storeAs('ratings', Str::uuid() . '.' . ($request->file('photo')->guessExtension() ?: 'jpg'), 'public')
            : null;

        // Locked so a double-tapped submit (or two tabs) can't create two
        // ratings for one order — the second request sees the first's row.
        $outcome = DB::transaction(function () use ($order, $data, $editing, $newPhoto, $request) {
            Order::whereKey($order->id)->lockForUpdate()->first();
            $order->load('feedback');
            $status = Ratings::status($order);

            if (!$editing && $status !== Ratings::OPEN) {
                return ['refused', $status];
            }
            if ($editing && $status !== Ratings::EDITABLE) {
                return ['refused', $status];
            }

            $feedback = $order->feedback ?? new Feedback(['user_id' => $order->user_id, 'order_id' => $order->id]);
            $oldPhoto = $feedback->photo_path;

            $feedback->fill([
                'rating' => $data['rating'],
                'rating_food' => $data['rating_food'],
                'rating_service' => $this->hasService($order) ? $data['rating_service'] : null,
                'rider_id' => $order->isBooking() ? null : $order->delivery?->user_id,
                'comment' => $data['comment'] ?? null,
            ]);
            if ($newPhoto) {
                $feedback->photo_path = $newPhoto;
            } elseif ($request->boolean('remove_photo')) {
                $feedback->photo_path = null;
            }
            if ($editing) {
                $feedback->edited_at = now();
            }
            $feedback->save();

            $feedback->items()->delete();
            foreach ($order->orderItems as $line) {
                $feedback->items()->create([
                    'order_item_id' => $line->id,
                    'menu_item_id' => $line->menu_item_id,
                    'package_id' => $line->package_id,
                    'rating' => $data['items'][$line->id]['rating'],
                    'comment' => $data['items'][$line->id]['comment'] ?? null,
                ]);
            }

            return ['saved', $oldPhoto !== $feedback->photo_path ? $oldPhoto : null];
        });

        [$result, $detail] = $outcome;

        if ($result === 'refused') {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }

            return redirect()->route('customer.orders.show', $order->id)->with('error', $this->refusal($order->fresh('feedback'), $detail));
        }

        if ($detail) {
            Storage::disk('public')->delete($detail); // replaced or removed photo
        }

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', $editing ? 'Your rating was updated.' : 'Thank you for rating your order!');
    }

    private function validated(Request $request, Order $order): array
    {
        // Plain text only, before the length checks run on it.
        $items = $request->input('items', []);
        if (is_array($items)) {
            foreach ($items as $id => $line) {
                if (is_array($line) && array_key_exists('comment', $line)) {
                    $items[$id]['comment'] = Ratings::clean(is_string($line['comment']) ? $line['comment'] : null);
                }
            }
        }
        $request->merge([
            'items' => $items,
            'comment' => Ratings::clean(is_string($request->input('comment')) ? $request->input('comment') : null),
        ]);

        $stars = ['required', 'integer', 'between:1,5'];
        $labels = Feedback::serviceLabels($order->isBooking());
        $rules = [
            'rating' => $stars,
            'rating_food' => $stars,
            'rating_service' => $this->hasService($order) ? $stars : ['nullable'],
            'comment' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
        $messages = [
            'rating.required' => 'Please rate your overall experience.',
            'rating_food.required' => 'Please rate the ' . strtolower($labels['rating_food']) . '.',
            'rating_service.required' => 'Please rate the ' . strtolower($labels['rating_service']) . '.',
            'between' => 'Ratings are from 1 to 5 stars.',
            'comment.max' => 'Please keep your comment under 500 characters.',
            'photo.image' => 'The photo must be an image (JPG, PNG or WebP).',
            'photo.mimes' => 'The photo must be an image (JPG, PNG or WebP).',
            'photo.max' => 'That photo is too large — please choose a smaller one.',
        ];
        foreach ($order->orderItems as $line) {
            $rules["items.{$line->id}.rating"] = $stars;
            $rules["items.{$line->id}.comment"] = ['nullable', 'string', 'max:300'];
            $messages["items.{$line->id}.rating.required"] = 'Please rate ' . $line->displayName() . '.';
            $messages["items.{$line->id}.comment.max"] = 'Please keep the comment on ' . $line->displayName() . ' under 300 characters.';
        }

        return $request->validate($rules, $messages);
    }

    /** Menu orders are rated on the rider only when one delivered it. */
    private function hasService(Order $order): bool
    {
        return $order->isBooking() || (bool) $order->delivery?->user_id;
    }

    private function ownOrder($orderId): Order
    {
        return Order::with('orderItems.menuItem', 'orderItems.package', 'delivery.personnel', 'feedback')
            ->where('user_id', auth()->id())
            ->findOrFail($orderId);
    }

    private function refusal(Order $order, string $status): string
    {
        return match ($status) {
            Ratings::EDITABLE => 'You\'ve already rated this order. You can edit it until '
                . Ratings::editDeadline($order)->format('M j, g:i A') . '.',
            Ratings::LOCKED => 'You\'ve already rated this order, and the time to edit it has passed.',
            Ratings::CLOSED => 'Rating for this order closed on ' . Ratings::rateDeadline($order)->format('M j, Y') . '.',
            default => $order->isBooking()
                ? 'You can rate this booking once the event is completed.'
                : 'You can rate this order once it has been delivered.',
        };
    }
}
