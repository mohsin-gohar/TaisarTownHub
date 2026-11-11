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

        $query = $user->notifications();

        if ($request->boolean('unreadOnly')) {
            $query->whereNull('read_at');
        }

        $page = $query->paginate(min((int) $request->get('per_page', 20), 100));

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
        return response()->json([
            'success' => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
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
        $request->user()->unreadNotifications->each->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }
}
