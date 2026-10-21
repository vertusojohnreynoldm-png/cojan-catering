<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->filtered($request)
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        $actors = User::orderBy('name')->get(['id', 'name']);

        return view('admin.activity', compact('logs', 'actors'));
    }

    // Respects the same filters as index() — "Export CSV" always exports
    // exactly what's currently on screen, not the whole table.
    public function export(Request $request)
    {
        $rows = $this->filtered($request)->orderBy('created_at', 'desc')->get();
        $filename = 'activity-log-' . now()->format('Y-m-d_His') . '.csv';

        return new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date/Time (Asia/Manila)', 'Actor', 'Role', 'Category', 'Action', 'Description', 'Subject', 'IP Address', 'Device']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->created_at->timezone('Asia/Manila')->format('Y-m-d H:i:s'),
                    $row->actor_name,
                    $row->actor_role,
                    $row->category,
                    $row->action,
                    $row->description,
                    $row->subject_label,
                    $row->ip_address,
                    $row->user_agent,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filtered(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', (int) $request->input('actor_id'));
        }

        if ($request->filled('role')) {
            $query->where('actor_role', $request->input('role'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->input('search') . '%');
        }

        return $query;
    }
}
