<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 40)->nullable()->unique()->after('name');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->ipAddress('last_login_ip')->nullable()->after('last_login_at');
            $table->softDeletes();
        });
        Schema::table('user_profiles', function (Blueprint $table): void {
            $table->string('display_name', 120)->nullable()->after('user_id');
            $table->string('cover_image_path')->nullable()->after('avatar_path');
            $table->string('website_url', 255)->nullable()->after('location');
            $table->date('birth_date')->nullable()->after('website_url');
            $table->timestamp('onboarding_completed_at')->nullable()->after('birth_date');
        });
        Schema::table('interests', function (Blueprint $table): void { $table->boolean('is_active')->default(true)->index()->after('category'); });
        Schema::table('user_interests', function (Blueprint $table): void { $table->timestamp('updated_at')->nullable()->after('created_at'); });
        Schema::table('user_settings', function (Blueprint $table): void { $table->string('language', 10)->default('en')->after('user_id'); $table->boolean('push_notifications')->default(false)->after('email_notifications'); });
        Schema::table('user_privacy_settings', function (Blueprint $table): void {
            $table->boolean('discovery_enabled')->default(false)->after('profile_visibility');
            $table->boolean('show_online_status')->default(false)->after('discovery_enabled');
            $table->boolean('allow_message_requests')->default(false)->after('show_online_status');
            $table->boolean('allow_connection_requests')->default(true)->after('allow_message_requests');
            $table->boolean('show_interests')->default(true)->after('allow_connection_requests');
            $table->boolean('show_location')->default(false)->after('show_interests');
        });
        DB::table('users')->where('role', 'member')->update(['role' => 'user']);
        DB::table('users')->whereNull('username')->get(['id', 'email'])->each(function (object $user): void {
            $base = Str::lower(Str::before($user->email, '@'));
            $username = $base; $counter = 1;
            while (DB::table('users')->where('username', $username)->exists()) $username = $base.($counter++);
            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        });
        DB::statement("UPDATE user_profiles SET display_name = COALESCE(display_name, username) WHERE display_name IS NULL");
    }

    public function down(): void
    {
        Schema::table('user_privacy_settings', function (Blueprint $table): void { $table->dropColumn(['discovery_enabled', 'show_online_status', 'allow_message_requests', 'allow_connection_requests', 'show_interests', 'show_location']); });
        Schema::table('user_settings', function (Blueprint $table): void { $table->dropColumn(['language', 'push_notifications']); });
        Schema::table('user_interests', function (Blueprint $table): void { $table->dropColumn('updated_at'); });
        Schema::table('interests', function (Blueprint $table): void { $table->dropColumn('is_active'); });
        Schema::table('user_profiles', function (Blueprint $table): void { $table->dropColumn(['display_name', 'cover_image_path', 'website_url', 'birth_date', 'onboarding_completed_at']); });
        Schema::table('users', function (Blueprint $table): void { $table->dropSoftDeletes(); $table->dropColumn(['username', 'last_login_at', 'last_login_ip']); });
    }
};
