<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Canonical source for discovery consent and recommendation visibility.
        // `discovery_enabled` here is authoritative; user_privacy_settings.discovery_enabled
        // is kept in sync as a read mirror by the discovery preference service.
        Schema::create('discovery_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('discovery_enabled')->default(false);
            $table->boolean('show_in_recommendations')->default(true);
            $table->jsonb('preferred_interest_categories')->nullable();
            $table->timestamps();
        });

        Schema::create('discovery_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('feedback_type', 30);
            $table->timestamps();
            $table->unique(['user_id', 'target_user_id']);
            $table->index(['user_id', 'feedback_type']);
        });

        Schema::create('discovery_impressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at')->index();
            $table->index(['viewer_id', 'viewed_at']);
        });

        // Discovery eligibility is filtered on profile visibility.
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->index('visibility');
            $table->index('display_name');
        });

        // Interest membership is the matching backbone; support interest -> users lookups.
        Schema::table('user_interests', function (Blueprint $table): void {
            $table->index('interest_id');
        });

        // Backfill preference rows so eligibility joins never silently exclude existing users.
        $backfill = DB::query()
            ->from('users as u')
            ->leftJoin('discovery_preferences as d', 'd.user_id', '=', 'u.id')
            ->leftJoin('user_privacy_settings as p', 'p.user_id', '=', 'u.id')
            ->whereNull('d.user_id')
            ->select('u.id as user_id', DB::raw('coalesce(p.discovery_enabled, 0) as discovery_enabled'));

        $backfill->lazyById('user_id', 'u')->each(function (object $user): void {
            DB::table('discovery_preferences')->insert([
                'user_id' => $user->user_id,
                'discovery_enabled' => (bool) $user->discovery_enabled,
                'show_in_recommendations' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('user_interests', function (Blueprint $table): void {
            $table->dropIndex(['interest_id']);
        });
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->dropIndex(['visibility']);
            $table->dropIndex(['display_name']);
        });
        Schema::dropIfExists('discovery_impressions');
        Schema::dropIfExists('discovery_feedback');
        Schema::dropIfExists('discovery_preferences');
    }
};
