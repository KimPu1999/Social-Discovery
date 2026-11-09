<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Services\Social\SocialNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notification feed surface. Every read and write is scoped to the
 * authenticated user only — cross-user reads are impossible.
 */
class NotificationController extends Controller
{
    public function __construct(private readonly SocialNotificationService $notifications) {}

    /** GET /notifications — paginated feed plus unread counter. */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(min((int) $request->query('per_page', 15), 50), 5);
        $page = max((int) $request->query('page', 1), 1);
        $feed = $this->notifications->feed($request->user(), $perPage, $page);

        $data = collect($feed->items())->values()->map(static fn (object $notification): array => [
            'id' => $notification->id,
            'type' => $notification->data['type'] ?? null,
            'actor' => $notification->data['actor'] ?? null,
            'url' => $notification->data['url'] ?? '/dashboard',
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ])->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $feed->currentPage(),
                'last_page' => $feed->lastPage(),
                'per_page' => $feed->perPage(),
                'total' => $feed->total(),
            ],
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    /** GET /notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $this->notifications->unreadCount($request->user())]);
    }

    /** POST /notifications/{id}/read */
    public function read(Request $request, string $notificationId): JsonResponse
    {
        $read = $this->notifications->markRead($request->user(), $notificationId);

        if (! $read) {
            abort(404, 'Notification not found.');
        }

        return response()->json(['message' => 'Notification marked as read.', 'unread_count' => $this->notifications->unreadCount($request->user())]);
    }

    /** POST /notifications/read-all */
    public function readAll(Request $request): JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return response()->json(['message' => 'All notifications marked as read.', 'unread_count' => 0]);
    }
}
