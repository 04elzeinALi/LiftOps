<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * A person's own in-app messages. Every action here is scoped to the signed-in
 * user — there is no path to read or touch anyone else's, for any role.
 */
class NotificationController extends Controller
{
    /**
     * Newest first, with the unread count alongside so the bell badge and the
     * list arrive in one request rather than two.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $notifications,
            'unread_count' => Notification::where('user_id', $user->id)->unread()->count(),
        ]);
    }

    /**
     * Marks one message read. Scoped by user_id in the lookup itself, so
     * another person's id simply doesn't resolve.
     */
    public function markRead(Request $request, string $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    /**
     * Clears the badge in one go.
     */
    public function markAllRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
