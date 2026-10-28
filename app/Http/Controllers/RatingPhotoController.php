<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The only place a rating photo is read from disk — no page prints its
 * storage path. Visible reviews' photos can be seen by any signed-in user
 * (they're shown with the review); a hidden review's photo only by the
 * customer who posted it and admins.
 */
class RatingPhotoController extends Controller
{
    public function show(Request $request, Feedback $feedback)
    {
        $user = $request->user();
        $privileged = $user->role === 'admin' || $feedback->user_id === $user->id;

        abort_if($feedback->is_hidden && !$privileged, 404);
        abort_if(!$feedback->photo_path || !Storage::disk('public')->exists($feedback->photo_path), 404);

        return Storage::disk('public')->response($feedback->photo_path, null, [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
