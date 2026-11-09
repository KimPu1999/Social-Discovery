<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 05 — social connections, follows, friend requests, blocking,
 * and the database notifications table. SQLite (tests) and PostgreSQL
 * (local/production) are both supported; pair uniqueness that depends
 * on SQL expressions is applied per-driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rendering pipeline for social notifications (Laravel database channel).
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index('read_at');
        });

        Schema::create('user_follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('following_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_id', 'following_id']);
            $table->index(['following_id', 'follower_id']);
            $table->index('created_at');
        });

        Schema::create('connection_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['receiver_id', 'status']);
            $table->index(['sender_id', 'status']);
            $table->index('created_at');
        });

        // Direction-agnostic uniqueness: one pending request per unordered pair
        // (covers reverse-direction duplicates), and one accepted request per
        // pair so historical records stay unique per state change.
        $this->createPairExpressionIndex('connection_requests', 'connection_requests_pair_pending_unique', 'pending');
        $this->createPairExpressionIndex('connection_requests', 'connection_requests_pair_accepted_unique', 'accepted');
        // Self requests are impossible, full stop (both drivers).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE connection_requests ADD CONSTRAINT connection_requests_no_self_check CHECK (sender_id <> receiver_id)');
        } else {
            DB::statement("CREATE TRIGGER connection_requests_no_self_insert BEFORE INSERT ON connection_requests WHEN NEW.sender_id = NEW.receiver_id BEGIN SELECT RAISE(ABORT, 'connection_requests_no_self_check'); END");
            DB::statement("CREATE TRIGGER connection_requests_no_self_update BEFORE UPDATE ON connection_requests WHEN NEW.sender_id = NEW.receiver_id BEGIN SELECT RAISE(ABORT, 'connection_requests_no_self_check'); END");
        }

        Schema::create('user_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('connected_at');
            $table->timestamps();
            $table->unique(['user_one_id', 'user_two_id']);
            $table->index(['user_two_id', 'user_one_id']);
            $table->index('connected_at');
        });

        // Enforce canonical ordering (user_one_id < user_two_id) and forbid
        // self-connections at the database level in either supported driver.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_connections ADD CONSTRAINT user_connections_ordered_check CHECK (user_one_id < user_two_id)');
            DB::statement('ALTER TABLE user_connections ADD CONSTRAINT user_connections_no_self_check CHECK (user_one_id <> user_two_id)');
        } else {
            DB::statement("CREATE TRIGGER user_connections_ordered_insert BEFORE INSERT ON user_connections WHEN NEW.user_one_id >= NEW.user_two_id BEGIN SELECT RAISE(ABORT, 'user_connections_ordered_check'); END");
            DB::statement("CREATE TRIGGER user_connections_ordered_update BEFORE UPDATE ON user_connections WHEN NEW.user_one_id >= NEW.user_two_id BEGIN SELECT RAISE(ABORT, 'user_connections_ordered_check'); END");
        }
        // Self-connections are impossible on every driver through a partial
        // unique index (only one row where the pair is a single user).
        DB::statement('CREATE UNIQUE INDEX user_connections_no_self_unique ON user_connections (user_one_id) WHERE user_one_id = user_two_id');

        // Blocking must also be enforced without application races when drivers
        // allow it; the table itself keeps its unique (user_id, blocked_user_id).
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_blocks ADD CONSTRAINT user_blocks_no_self_check CHECK (user_id <> blocked_user_id)');
        } else {
            DB::statement("CREATE TRIGGER user_blocks_no_self_insert BEFORE INSERT ON user_blocks WHEN NEW.user_id = NEW.blocked_user_id BEGIN SELECT RAISE(ABORT, 'user_blocks_no_self_check'); END");
            DB::statement("CREATE TRIGGER user_blocks_no_self_update BEFORE UPDATE ON user_blocks WHEN NEW.user_id = NEW.blocked_user_id BEGIN SELECT RAISE(ABORT, 'user_blocks_no_self_check'); END");
        }

        // Phase 05 privacy extensions (Phase 02 model, additive only).
        Schema::table('user_privacy_settings', function (Blueprint $table): void {
            $table->boolean('allow_follows')->default(true)->after('allow_connection_requests');
            $table->boolean('show_followers')->default(true)->after('show_interests');
            $table->boolean('show_following')->default(true)->after('show_followers');
            $table->boolean('show_connections')->default(true)->after('show_following');
            $table->boolean('show_mutual_connections')->default(true)->after('show_connections');
        });
    }

    public function down(): void
    {
        Schema::table('user_privacy_settings', function (Blueprint $table): void {
            $table->dropColumn(['allow_follows', 'show_followers', 'show_following', 'show_connections', 'show_mutual_connections']);
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            foreach (['user_blocks', 'user_connections', 'connection_requests'] as $table) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_no_self_insert");
                DB::statement("DROP TRIGGER IF EXISTS {$table}_no_self_update");
                DB::statement("DROP TRIGGER IF EXISTS {$table}_ordered_insert");
                DB::statement("DROP TRIGGER IF EXISTS {$table}_ordered_update");
            }
        }

        Schema::dropIfExists('user_connections');
        Schema::dropIfExists('connection_requests');
        Schema::dropIfExists('user_follows');
        Schema::dropIfExists('notifications');
    }

    /**
     * Unique (min, max) pair expression index restricted to one status. Works
     * on SQLite (functional indexes) and PostgreSQL (expressions + predicate).
     */
    private function createPairExpressionIndex(string $table, string $name, string $status): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX {$name} ON {$table} ((least(sender_id, receiver_id)), (greatest(sender_id, receiver_id))) WHERE status = '{$status}'");

            return;
        }

        DB::statement("CREATE UNIQUE INDEX {$name} ON {$table} (min(sender_id, receiver_id), max(sender_id, receiver_id)) WHERE status = '{$status}'");
    }
};
