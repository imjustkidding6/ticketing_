<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemAnnouncement;
use App\Models\User;
use App\Notifications\SystemAnnouncementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemAnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = SystemAnnouncement::query()
            ->with('publisher')
            ->latest('published_at')
            ->latest('id')
            ->paginate(20);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        return view('admin.announcements.create', [
            'severities' => SystemAnnouncement::SEVERITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'severity' => ['required', 'string', Rule::in(SystemAnnouncement::SEVERITIES)],
        ]);

        $announcement = DB::transaction(function () use ($validated) {
            $announcement = SystemAnnouncement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'severity' => $validated['severity'],
                'published_by' => Auth::id(),
                'published_at' => now(),
            ]);

            // Broadcast to every user in the system. Chunk to avoid memory spikes
            // on large tenants.
            $total = 0;
            User::query()->chunk(200, function ($users) use ($announcement, &$total) {
                Notification::send($users, new SystemAnnouncementNotification($announcement));
                $total += $users->count();
            });

            $announcement->update(['recipient_count' => $total]);

            return $announcement;
        });

        return redirect()->route('admin.announcements.index')
            ->with('success', "Announcement broadcast to {$announcement->recipient_count} users.");
    }

    public function destroy(SystemAnnouncement $announcement): RedirectResponse
    {
        $announcement->delete();

        // Remove the announcement from all users' notification bells.
        DB::table('notifications')
            ->where('type', SystemAnnouncementNotification::class)
            ->whereJsonContains('data->announcement_id', $announcement->id)
            ->delete();

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Announcement deleted and revoked from all users.');
    }
}
