<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Social\SendConnectionRequestRequest;
use App\Http\Requests\Social\SocialListRequest;
use App\Models\User;
use App\Services\Social\ConnectionRequestService;
use Illuminate\Http\JsonResponse;

/**
 * Friend (connection) request HTTP surface. Ownership and state checks
 * are enforced inside the service inside transactions.
 */
class ConnectionRequestController extends Controller
{
    public function __construct(private readonly ConnectionRequestService $requests) {}

    /** POST /connections/requests */
    public function store(SendConnectionRequestRequest $request): JsonResponse
    {
        $receiver = User::query()->findOrFail($request->validated('receiver_id'));

        [$requestModel, $relationship, $created] = $this->requests->send($request->user(), $receiver);

        return response()->json([
            'message' => $created ? 'Connection request sent.' : 'Connection request already pending.',
            'request' => [
                'id' => $requestModel->getKey(),
                'status' => $requestModel->status->value,
                'sender_id' => (int) $requestModel->sender_id,
                'receiver_id' => (int) $requestModel->receiver_id,
                'created_at' => $requestModel->created_at?->toISOString(),
            ],
            'relationship' => $relationship,
            'created' => $created,
        ], $created ? 201 : 200);
    }

    /** GET /connections/requests/incoming */
    public function incoming(SocialListRequest $request): JsonResponse
    {
        return response()->json($this->requests->incoming($request->user(), $request->validatedRange()));
    }

    /** GET /connections/requests/outgoing */
    public function outgoing(SocialListRequest $request): JsonResponse
    {
        return response()->json($this->requests->outgoing($request->user(), $request->validatedRange()));
    }

    /** POST /connections/requests/{request}/accept */
    public function accept(string $requestId): JsonResponse
    {
        [, $connection, $relationship] = $this->requests->accept(request()->user(), (int) $requestId);

        return response()->json([
            'message' => 'Connection request accepted.',
            'connection' => [
                'user_one_id' => (int) $connection->user_one_id,
                'user_two_id' => (int) $connection->user_two_id,
                'connected_at' => $connection->connected_at?->toISOString(),
            ],
            'relationship' => $relationship,
        ]);
    }

    /** POST /connections/requests/{request}/decline */
    public function decline(string $requestId): JsonResponse
    {
        $request = $this->requests->decline(request()->user(), (int) $requestId);

        return response()->json(['message' => 'Connection request declined.', 'request' => ['id' => $request->getKey(), 'status' => $request->status->value]]);
    }

    /** POST /connections/requests/{request}/cancel */
    public function cancel(string $requestId): JsonResponse
    {
        $request = $this->requests->cancel(request()->user(), (int) $requestId);

        return response()->json(['message' => 'Connection request cancelled.', 'request' => ['id' => $request->getKey(), 'status' => $request->status->value]]);
    }
}
