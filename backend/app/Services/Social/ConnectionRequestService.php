<?php

namespace App\Services\Social;

use App\Enums\ConnectionRequestStatus;
use App\Exceptions\SocialConflictException;
use App\Models\ConnectionRequest;
use App\Models\User;
use App\Models\UserConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Friend (connection) request lifecycle: send, accept, decline, cancel.
 * Behavior is idempotent under retries and safe under concurrency:
 * the accept path re-locks the request row and re-checks both accounts
 * and the block table inside the transaction before creating the
 * normalized connection pair.
 */
class ConnectionRequestService
{
    public function __construct(
        private readonly RelationshipService $relationships,
        private readonly SocialNotificationService $notifications,
    ) {}

    /**
     * Send a connection request to the receiver. Retrying an identical
     * pending request is idempotent; conflicting states become 409s.
     *
     * @return array{0: ConnectionRequest, 1: array<string, bool|string>, 2: bool} [request, relationship, wasCreated]
     */
    public function send(User $sender, User $receiver): array
    {
        $this->assertSendable($sender, $receiver);

        return DB::transaction(function () use ($sender, $receiver): array {
            DB::table('users')->whereIn('id', [$sender->getKey(), $receiver->getKey()])->orderBy('id')->lockForUpdate()->get();

            $existingPending = User::pendingRequestBetween((int) $sender->getKey(), (int) $receiver->getKey());

            if ($existingPending !== null) {
                // Same direction: idempotent resend. Opposite direction: 409.
                if ($existingPending->sender_id === (int) $sender->getKey()) {
                    return [$existingPending, $this->relationships->forPair($sender, $receiver), false];
                }
                throw new SocialConflictException('A connection request is already waiting from the other side.');
            }

            if (User::connectionWith((int) $sender->getKey(), (int) $receiver->getKey()) !== null) {
                throw new SocialConflictException('You are already connected with this person.');
            }

            $request = ConnectionRequest::query()->create([
                'sender_id' => $sender->getKey(),
                'receiver_id' => $receiver->getKey(),
                'status' => ConnectionRequestStatus::Pending,
            ]);

            // afterCommit delivery.
            $this->notifications->connectionRequested($receiver, $sender);

            return [$request, $this->relationships->forPair($sender, $receiver), true];
        });
    }

    /**
     * Accept a pending request addressed to the actor. Concurrent/problem
     * replays converge on either an accepted connection or a clear error;
     * the connection pair creation is guarded by the unique pair index.
     *
     * @return array{0: ConnectionRequest, 1: UserConnection, 2: array<string, bool|string>}
     */
    public function accept(User $actor, int $requestId): array
    {
        return DB::transaction(function () use ($actor, $requestId): array {
            /** @var ConnectionRequest $request */
            $request = ConnectionRequest::query()->whereKey($requestId)->lockForUpdate()->first();

            if ($request === null || (int) $request->receiver_id !== (int) $actor->getKey()) {
                throw ValidationException::withMessages(['request' => 'That connection request is not yours to accept.']);
            }

            if ($request->status === ConnectionRequestStatus::Accepted) {
                [$connection] = $this->ensureConnection($request);
                $senderId = (int) $request->sender_id;

                return [$request, $connection, $this->relationships->forPair($actor, User::findOrFail($senderId))];
            }

            if (! $request->isPending()) {
                throw new SocialConflictException('That connection request has already been resolved.');
            }

            $sender = User::query()->findOrFail($request->sender_id);
            $receiver = User::query()->findOrFail($request->receiver_id);

            // Recheck account health and blocking state under the lock.
            if (! $sender->isSociallyActive() || ! $receiver->isSociallyActive()) {
                throw new SocialConflictException('This connection request can no longer be accepted.');
            }

            $blocked = DB::table('user_blocks')
                ->where(static function ($query) use ($sender, $receiver): void {
                    $query->where(static function ($sub) use ($sender, $receiver): void {
                        $sub->where('user_id', $sender->getKey())->where('blocked_user_id', $receiver->getKey());
                    })->orWhere(static function ($sub) use ($sender, $receiver): void {
                        $sub->where('user_id', $receiver->getKey())->where('blocked_user_id', $sender->getKey());
                    });
                })
                ->exists();

            if ($blocked) {
                throw new SocialConflictException('This connection can no longer be made.');
            }

            [$connection] = $this->ensureConnection($request);

            $request->forceFill(['status' => ConnectionRequestStatus::Accepted, 'responded_at' => now()])->save();

            // afterCommit delivery to the original sender.
            $this->notifications->connectionAccepted($sender, $receiver);

            return [$request, $connection, $this->relationships->forPair($actor, $sender)];
        });
    }

    /** Receiver declines a pending request; keeps history, allows future retries. */
    public function decline(User $actor, int $requestId): ConnectionRequest
    {
        return DB::transaction(function () use ($actor, $requestId): ConnectionRequest {
            $request = ConnectionRequest::query()->whereKey($requestId)->lockForUpdate()->first();

            if ($request === null || (int) $request->receiver_id !== (int) $actor->getKey()) {
                throw ValidationException::withMessages(['request' => 'That connection request is not yours to respond to.']);
            }

            if (! $request->isPending()) {
                throw new SocialConflictException('That connection request has already been resolved.');
            }

            $request->forceFill(['status' => ConnectionRequestStatus::Declined, 'responded_at' => now()])->save();

            return $request;
        });
    }

    /** Sender cancels their own pending request. */
    public function cancel(User $actor, int $requestId): ConnectionRequest
    {
        return DB::transaction(function () use ($actor, $requestId): ConnectionRequest {
            $request = ConnectionRequest::query()->whereKey($requestId)->lockForUpdate()->first();

            if ($request === null || (int) $request->sender_id !== (int) $actor->getKey()) {
                throw ValidationException::withMessages(['request' => 'That connection request is not yours to cancel.']);
            }

            if (! $request->isPending()) {
                throw new SocialConflictException('That connection request has already been resolved.');
            }

            $request->forceFill(['status' => ConnectionRequestStatus::Cancelled, 'responded_at' => now()])->save();

            return $request;
        });
    }

    /** Paginated pending requests addressed to the actor. */
    public function incoming(User $actor, array $params): array
    {
        return $this->requestList($actor, 'receiver', $params);
    }

    /** Paginated pending requests sent by the actor. */
    public function outgoing(User $actor, array $params): array
    {
        return $this->requestList($actor, 'sender', $params);
    }

    /**
     * @param  array{page?: int, per_page?: int}  $params
     * @return array{data: array, meta: array}
     */
    private function requestList(User $actor, string $side, array $params): array
    {
        $otherSide = $side === 'receiver' ? 'sender_id' : 'receiver_id';
        $otherAlias = $side === 'receiver' ? 'request_senders' : 'request_receivers';

        $columns = ['connection_requests.*', $otherAlias.'.name as other_name'];

        $query = ConnectionRequest::query()
            ->from('connection_requests')
            ->join("users as {$otherAlias}", "{$otherAlias}.id", '=', "connection_requests.{$otherSide}")
            ->join('user_profiles as request_profiles', 'request_profiles.user_id', '=', "connection_requests.{$otherSide}")
            ->select(array_merge($columns, ['request_profiles.display_name', 'request_profiles.username as profile_username', 'request_profiles.avatar_path']))
            ->where("connection_requests.{$side}_id", $actor->getKey())
            ->where('connection_requests.status', ConnectionRequestStatus::Pending->value)
            ->orderBy('connection_requests.id', $side === 'receiver' ? 'asc' : 'desc');

        $page = $query->paginate($params['per_page'] ?? 10, $columns, 'page', max($params['page'] ?? 1, 1));

        // Hydrate the peer users in one batched query (no N+1).
        $peerIds = collect($page->items())->map(static fn (ConnectionRequest $request): int => (int) $request->{$otherSide})->unique()->all();
        $peers = User::query()->with(['profile'])->whereIn('users.id', $peerIds)->get()->keyBy(static fn (User $user): int => (int) $user->getKey());

        $data = collect($page->items())->values()->map(static function (ConnectionRequest $request) use ($peers, $otherSide): array {
            /** @var User $other */
            $other = $peers[$request->{$otherSide}];
            $senderUser = $otherSide === 'sender_id' ? $other : null;
            $receiverUser = $otherSide === 'receiver_id' ? $other : null;

            return [
                'id' => $request->getKey(),
                'status' => $request->status->value,
                'created_at' => $request->created_at?->toISOString(),
                'sender' => $senderUser ? [
                    'id' => $senderUser->getKey(),
                    'username' => $senderUser->profile?->username,
                    'display_name' => $senderUser->profile?->display_name ?? $senderUser->name,
                    'bio' => $senderUser->profile?->bio,
                    'avatar_url' => $senderUser->profile?->avatar_path ? asset('storage/'.$senderUser->profile->avatar_path) : null,
                ] : null,
                'receiver' => $receiverUser ? [
                    'id' => $receiverUser->getKey(),
                    'username' => $receiverUser->profile?->username,
                    'display_name' => $receiverUser->profile?->display_name ?? $receiverUser->name,
                    'bio' => $receiverUser->profile?->bio,
                    'avatar_url' => $receiverUser->profile?->avatar_path ? asset('storage/'.$receiverUser->profile->avatar_path) : null,
                ] : null,
            ];
        })->all();

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }

    /**
     * Create-or-find the normalized connection between the request ends.
     * The pair unique index guarantees at most one row even under races.
     *
     * @return array{0: UserConnection, 1: bool}
     */
    private function ensureConnection(ConnectionRequest $request): array
    {
        $one = min((int) $request->sender_id, (int) $request->receiver_id);
        $two = max((int) $request->sender_id, (int) $request->receiver_id);

        $connection = UserConnection::query()->firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
            ['connected_at' => now()],
        );

        return [$connection, $connection->wasRecentlyCreated];
    }

    /** Sender/receiver gating for new connection requests. */
    private function assertSendable(User $sender, User $receiver): void
    {
        if ($sender->is($receiver)) {
            throw ValidationException::withMessages(['receiver_id' => 'You cannot send a connection request to your own account.']);
        }

        if (! $sender->isSociallyActive() || ! $receiver->isSociallyActive()) {
            throw ValidationException::withMessages(['receiver_id' => 'This account is not accepting connection requests right now.']);
        }

        $blocked = DB::table('user_blocks')
            ->where(static function ($query) use ($sender, $receiver): void {
                $query->where(static function ($sub) use ($sender, $receiver): void {
                    $sub->where('user_id', $sender->getKey())->where('blocked_user_id', $receiver->getKey());
                })->orWhere(static function ($sub) use ($sender, $receiver): void {
                    $sub->where('user_id', $receiver->getKey())->where('blocked_user_id', $sender->getKey());
                });
            })
            ->exists();

        if ($blocked) {
            throw new SocialConflictException('You cannot connect with this account.');
        }

        $privacy = $receiver->privacySettings;

        if (! (bool) $privacy?->allow_connection_requests) {
            throw new SocialConflictException('This profile does not accept connection requests.');
        }
    }
}
