<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;

class FeedbackController extends Controller
{
    public function index()
    {
        $feedback = Feedback::with('user', 'order')
            ->orderBy('created_at', 'desc')
            ->get();

        $averageRating = $feedback->avg('rating') ?? 0;
        $totalFeedback = $feedback->count();

        return view('admin.feedback', compact('feedback', 'averageRating', 'totalFeedback'));
    }
}