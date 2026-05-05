<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    private function getAdmin()
    {
        return User::where('role', 'admin')->first();
    }

    // Customer: get conversation with admin
    public function customerMessages()
    {
        $admin = $this->getAdmin();
        $messages = Message::where(function ($q) use ($admin) {
            $q->where('sender_id', Auth::id())->where('receiver_id', $admin->id);
        })->orWhere(function ($q) use ($admin) {
            $q->where('sender_id', $admin->id)->where('receiver_id', Auth::id());
        })->orderBy('created_at')->get();

        return response()->json([
            'messages'   => $messages->map(fn($m) => [
                'id'          => $m->id,
                'body'        => $m->body,
                'sender_id'   => $m->sender_id,
                'sender_name' => $m->sender->name,
                'created_at'  => $m->created_at->format('h:i A'),
            ]),
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

        $message = Message::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'body'        => $request->body,
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'success' => true,
            'message' => [
                'id'          => $message->id,
                'body'        => $message->body,
                'sender_id'   => $message->sender_id,
                'sender_name' => Auth::user()->name,
                'created_at'  => $message->created_at->format('h:i A'),
            ],
        ]);
    }

    // Admin: list all customers who messaged
    public function adminInbox()
    {
        $adminId = Auth::id();
        $customers = Message::where('receiver_id', $adminId)
            ->orWhere('sender_id', $adminId)
            ->with('sender', 'receiver')
            ->get()
            ->flatMap(fn($m) => [$m->sender, $m->receiver])
            ->unique('id')
            ->filter(fn($u) => $u && $u->id !== $adminId && $u->role === 'customer')
            ->values();

        return response()->json(['customers' => $customers]);
    }

    // Admin: get conversation with a specific customer
    public function adminMessages($customerId)
    {
        $messages = Message::where(function ($q) use ($customerId) {
            $q->where('sender_id', Auth::id())->where('receiver_id', $customerId);
        })->orWhere(function ($q) use ($customerId) {
            $q->where('sender_id', $customerId)->where('receiver_id', Auth::id());
        })->orderBy('created_at')->get();

        // Mark as read
        Message::where('sender_id', $customerId)
            ->where('receiver_id', Auth::id())
            ->update(['is_read' => true]);

        $customer = User::findOrFail($customerId);

        return response()->json([
            'messages' => $messages->map(fn($m) => [
                'id'          => $m->id,
                'body'        => $m->body,
                'sender_id'   => $m->sender_id,
                'sender_name' => $m->sender->name,
                'created_at'  => $m->created_at->format('h:i A'),
            ]),
            'customer' => ['id' => $customer->id, 'name' => $customer->name],
        ]);
    }

    // Unread count (for notification badge)
    public function unreadCount()
    {
        return response()->json([
            'count' => Message::where('receiver_id', Auth::id())
                              ->where('is_read', false)
                              ->count(),
        ]);
    }
}
