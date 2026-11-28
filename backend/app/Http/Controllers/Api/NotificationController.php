<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use AuthorizesApi;

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $query = $user->notifications()->latest();

        if ($request->boolean('unreadOnly')) {
            $query->whereNull('read_at');
        }

        $perPage = max(1, min((int) $request->get('per_page', 20), 100));
        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'notifications' => $page->items(),
            'unread_count' => $user->unreadNotifications()->count(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        return response()->json([
            'success' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $notification = $user->notifications()->findOrFail($id);
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $user->unreadNotifications()->update(['read_at' => now()]);
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'unread_count' => $unreadCount,
        ]);
    }
}
