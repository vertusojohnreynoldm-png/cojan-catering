<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The only place a proof-of-delivery photo is ever read from disk. No view,
 * email, or API response prints the raw storage path/URL anywhere — every
 * <img> in the app points here instead, so access is gated by this
 * owner/rider/admin check regardless of how "guessable" the underlying
 * filename is (it's also a Str::random(40) name from Storage::store(),
 * never derived from the order number or any other predictable value).
 */
class ProofPhotoController extends Controller
{
    public function show(Request $request, Order $order)
    {
        $user = $request->user();
        $delivery = $order->delivery;

        $authorized = $user->role === 'admin'
            || $order->user_id === $user->id
            || ($delivery && $delivery->user_id === $user->id);

        abort_unless($authorized, 403);
        abort_if(!$delivery || !$delivery->proof_photo_path, 404);
        abort_unless(Storage::disk('public')->exists($delivery->proof_photo_path), 404);

        return Storage::disk('public')->response($delivery->proof_photo_path);
    }
}
