<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 06 — social feed, posts, media, reactions, comments, saved posts,
 * shares, hashtags, hiding, and content reporting. Extends the Phase 02
 * `user_reports` table instead of creating a duplicate reporting workflow.
 * SQLite (tests) and PostgreSQL (local/production) are both supported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('visibility', 20)->default('public')->index(); // public|connections|private
            $table->string('status', 20)->default('published')->index(); // draft|published|removed
            $table->timestamp('published_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'visibility', 'published_at']);
        });

        Schema::create('post_media', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('media_type', 20)->default('image')->index();
            $table->string('storage_disk', 30)->default('local');
            $table->string('storage_path');
            $table->string('mime_type', 60);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['post_id', 'sort_order']);
        });

        Schema::create('post_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reaction_type', 20)->default('like');
            $table->timestamps();
            $table->unique(['post_id', 'user_id']);
            $table->index(['user_id', 'post_id']);
        });

        Schema::create('post_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('post_comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['post_id', 'created_at']);
            $table->index('parent_id');
        });

        Schema::create('saved_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'post_id']);
            $table->index(['post_id', 'user_id']);
        });

        Schema::create('post_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('original_post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('shared_post_id')->constrained('posts')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique('shared_post_id'); // one share row per share post (no recursive chains)
            $table->index(['original_post_id', 'user_id']);
        });

        Schema::create('post_hashtags', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->timestamps();
        });

        Schema::create('post_hashtag_post', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('post_hashtag_id')->constrained('post_hashtags')->cascadeOnDelete();
            $table->primary(['post_id', 'post_hashtag_id']);
            $table->index('post_hashtag_id');
        });

        Schema::create('post_hides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'post_id']);
        });

        // Extend the Phase 02 reporting architecture for post/comment targets
        // instead of a parallel content-reports table.
        Schema::table('user_reports', function (Blueprint $table): void {
            $table->nullableMorphs('reportable');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->index(['status', 'reportable_type']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            //GIN full-text search on post bodies ('simple' config ships with every PostgreSQL install).
            DB::statement("CREATE INDEX posts_body_fulltext_idx ON posts USING GIN (to_tsvector('simple', coalesce(body, '')))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS posts_body_fulltext_idx');
        }

        Schema::table('user_reports', function (Blueprint $table): void {
            $table->dropIndex(['status', 'reportable_type']);
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn(['resolved_at', 'resolution_note']);
            $table->dropMorphs('reportable');
        });

        Schema::dropIfExists('post_hides');
        Schema::dropIfExists('post_hashtag_post');
        Schema::dropIfExists('post_hashtags');
        Schema::dropIfExists('post_shares');
        Schema::dropIfExists('saved_posts');
        Schema::dropIfExists('post_comments');
        Schema::dropIfExists('post_reactions');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
