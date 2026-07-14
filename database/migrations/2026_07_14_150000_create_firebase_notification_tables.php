<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable'); // user or admin
            $table->string('token', 512)->unique();
            $table->string('platform', 20)->nullable(); // android|ios|web
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('type', 50)->nullable()->after('body');
            $table->json('data')->nullable()->after('type');
        });

        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('type', 50)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropColumn(['type', 'data']);
        });

        Schema::dropIfExists('device_tokens');
    }
};
