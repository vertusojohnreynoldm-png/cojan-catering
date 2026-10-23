<?php

namespace App\Http\Controllers;

use App\Events\AdminInboxUpdated;
use App\Events\MessageSent;
use App\Events\MessagesRead;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ChatController extends Controller
{
    private function getAdmin()
    {
        return User::where('role', 'admin')->first();
    }

    // A broadcast failure (Pusher outage/misconfig) must never break sending
    // or marking-as-read — those already succeeded in the database by the
    // time this runs, so the live-update push is best-effort only. Clients
    // fall back to polling (see the front-end interval checks) when the
    // socket isn't connected.
    private function safeBroadcast($event): void
    {
        try {
            broadcast($event)->toOthers();
        } catch (Throwable $e) {
            Log::warning('Chat broadcast failed: ' . $e->getMessage());
        }
    }

    // Customer: get conversation with admin
    public function customerMessages()
    {
        $admin = $this->getAdmin();

        // No admin account at all (fresh install, or every admin deleted):
        // an empty, clearly-unavailable chat instead of a 500.
        if (!$admin) {
            return response()->json([
                'messages'    => [],
                'admin_id'    => null,
                'admin_name'  => null,
                'unavailable' => true,
            ]);
        }

        $messages = Message::where(function ($q) use ($admin) {
            $q->where('sender_id', Auth::id())->where('receiver_id', $admin->id);
        })->orWhere(function ($q) use ($admin) {
            $q->where('sender_id', $admin->id)->where('receiver_id', Auth::id());
        })->orderBy('created_at')->get();

        return response()->json([
            'messages'   => $messages->map(fn($m) => $this->formatMessage($m)),
            'admin_id'   => $admin->id,
            'admin_name' => $admin->name,
        ]);
    }

    // Send message (used by both customer and admin)
    public function send(Request $request)
    {
        $request->validate([
            'body'        => 'required|string|max:1000',
            'receiver_id' => 'required|exists:users,id',
        ]);

        $sender = Auth::user();

        if ($sender->role === 'customer') {
            // Customers can only ever message support — ignore whatever receiver_id
            // the client sent and use the same admin lookup as customerMessages().
            $admin = $this->getAdmin();

            if (!$admin) {
                abort(403, 'No admin is available to receive messages.');
            }

            $receiverId = $admin->id;
        } elseif ($sender->role === 'admin') {
            $receiver = User::find($request->receiver_id);

            if (!$receiver || $receiver->role !== 'customer') {
                abort(403, 'You can only message customers.');
            }

            $receiverId = $receiver->id;
        } else {
            // No chat UI exists for the delivery role today.
            abort(403, 'Chat is not available for your account.');
        }

        $message = Message::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $receiverId,
            'body'        => $request->body,
        ]);

        $this->safeBroadcast(new MessageSent($message));

        // Keep the admin inbox list live regardless of which conversation (if
        // any) is currently open — see AdminInboxUpdated's own doc comment.
        $admin = $this->getAdmin();
        if ($admin) {
            $unreadForThisCustomer = Message::where('receiver_id', $admin->id)
                ->where('sender_id', $sender->role === 'customer' ? $sender->id : $receiverId)
                ->whereNull('read_at')
                ->count();
            $this->safeBroadcast(new AdminInboxUpdated($message, $admin->id, $unreadForThisCustomer));
        }

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message, Auth::user()->name),
        ]);
    }

    // Admin: list all customers who messaged, most recent conversation first,
    // each with a preview + per-conversation unread count.
    public function adminInbox()
    {
        $adminId = Auth::id();

        $customerIds = Message::where('receiver_id', $adminId)
            ->orWhere('sender_id', $adminId)
            ->with('sender:id,name,role', 'receiver:id,name,role')
            ->get(['sender_id', 'receiver_id'])
            ->flatMap(fn($m) => [$m->sender, $m->receiver])
            ->unique('id')
            ->filter(fn($u) => $u && $u->id !== $adminId && $u->role === 'customer')
            ->pluck('id');

        $conversations = $customerIds->map(function ($customerId) use ($adminId) {
            $last = Message::where(function ($q) use ($adminId, $customerId) {
                $q->where('sender_id', $adminId)->where('receiver_id', $customerId);
            })->orWhere(function ($q) use ($adminId, $customerId) {
                $q->where('sender_id', $customerId)->where('receiver_id', $adminId);
            })->latest('created_at')->first();

            $customer = $last->sender_id === $customerId ? $last->sender : $last->receiver;

            return [
                'id'            => $customerId,
                'name'          => $customer->name,
                'preview'       => Str::limit($last->body, 60),
                'created_at'    => $last->created_at->format('h:i A'),
                'timestamp'     => $last->created_at->toIso8601String(),
                'unread_count'  => Message::where('sender_id', $customerId)
                    ->where('receiver_id', $adminId)
                    ->whereNull('read_at')
                    ->count(),
            ];
        })->sortByDesc('timestamp')->values();

        return response()->json(['customers' => $conversations]);
    }

    // Admin: get conversation with a specific customer
    public function adminMessages($customerId)
    {
        $messages = Message::where(function ($q) use ($customerId) {
            $q->where('sender_id', Auth::id())->where('receiver_id', $customerId);
        })->orWhere(function ($q) use ($customerId) {
            $q->where('sender_id', $customerId)->where('receiver_id', Auth::id());
        })->orderBy('created_at')->get();

        $now = now();
        $hadUnread = Message::where('sender_id', $customerId)
            ->where('receiver_id', Auth::id())
            ->whereNull('read_at')
            ->exists();

        Message::where('sender_id', $customerId)
            ->where('receiver_id', Auth::id())
            ->whereNull('read_at')
            ->update(['is_read' => true, 'read_at' => $now]);

        if ($hadUnread) {
            $this->safeBroadcast(new MessagesRead(Auth::id(), (int) $customerId, $now->toIso8601String()));
        }

        $customer = User::findOrFail($customerId);

        return response()->json([
            'messages' => $messages->map(fn($m) => $this->formatMessage($m)),
            'customer' => ['id' => $customer->id, 'name' => $customer->name],
        ]);
    }

    // Unread count (for notification badge)
    public function unreadCount()
    {
        return response()->json([
            'count' => Message::where('receiver_id', Auth::id())
                              ->whereNull('read_at')
                              ->count(),
        ]);
    }

    private function formatMessage(Message $m, ?string $senderNameOverride = null): array
    {
        return [
            'id'          => $m->id,
            'body'        => $m->body,
            'sender_id'   => $m->sender_id,
            'sender_name' => $senderNameOverride ?? $m->sender->name,
            'created_at'  => $m->created_at->format('h:i A'),
            'timestamp'   => $m->created_at->toIso8601String(),
            'read_at'     => $m->read_at?->toIso8601String(),
        ];
    }
}
