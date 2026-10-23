<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GCash downpayment receipts for catering bookings. Same storage approach
 * as ProofPhotoController: the public disk (the only one on Railway's
 * persistent volume), a random UUID filename, and every <img> pointing at
 * show() — which checks owner-or-admin — never at the raw storage path.
 */
class ReceiptController extends Controller
{
    public function show(Request $request, Order $order)
    {
        $user = $request->user();

        abort_unless($user->role === 'admin' || $order->user_id === $user->id, 403);
        abort_if(!$order->receipt_path, 404);
        abort_unless(Storage::disk('public')->exists($order->receipt_path), 404);

        return Storage::disk('public')->response($order->receipt_path, null, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Customer uploads (or re-uploads) the receipt while still Pending Payment. */
    public function store(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        if (!$order->canUploadReceipt()) {
            return $this->fail($request, $order, $order->isAwaitingDownpayment()
                ? 'The payment deadline has passed, so a receipt can no longer be uploaded.'
                : 'This booking isn\'t waiting for a downpayment any more.');
        }

        $validated = $request->validate([
            'receipt'         => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'gcash_reference' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ]+$/'],
        ], [
            'receipt.required' => 'Please choose a photo or screenshot of your GCash receipt.',
            'receipt.mimes'    => 'The receipt must be a JPG, PNG or WEBP image.',
            'receipt.max'      => 'That image is too large (max 5 MB).',
            'gcash_reference.regex' => 'The GCash reference number should contain digits only.',
        ]);

        $file = $validated['receipt'];
        $path = $file->storeAs('payment-receipts', Str::uuid() . '.' . ($file->guessExtension() ?: 'jpg'), 'public');

        $previous = $order->receipt_path;
        $wasRejected = $order->receipt_rejected_at !== null;

        // Quiet: the explicit entry below says it better than a generic
        // "updated Order (receipt_path, receipt_uploaded_at, …)".
        ActivityLogger::withoutLogging(fn () => $order->update([
            'receipt_path'             => $path,
            'receipt_uploaded_at'      => now(),
            'receipt_rejected_at'      => null,
            'receipt_rejection_reason' => null,
            'gcash_reference'          => isset($validated['gcash_reference'])
                ? preg_replace('/\s+/', '', $validated['gcash_reference'])
                : $order->gcash_reference,
        ]));

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        ActivityLogger::log(
            'booking.receipt_uploaded',
            'orders',
            "{$order->user->name} " . ($wasRejected || $previous ? 're-uploaded' : 'uploaded')
                . " a GCash receipt for Booking #{$order->order_number}",
            subject: $order,
        );

        $message = 'Receipt uploaded! We\'ll verify your downpayment and confirm your booking soon.';

        if ($request->expectsJson()) {
            session()->flash('success', $message);

            return response()->json(['redirect' => route('customer.orders.show', $order->id)]);
        }

        return redirect()->route('customer.orders.show', $order->id)->with('success', $message);
    }

    private function fail(Request $request, Order $order, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'errors' => ['receipt' => [$message]]], 422);
        }

        return redirect()->route('customer.orders.show', $order->id)->with('error', $message);
    }
}
