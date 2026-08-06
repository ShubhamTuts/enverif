<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160)->default('New chat');
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->index(['workspace_id', 'user_id', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->uuid('run_id')->nullable()->index();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique(['thread_id', 'run_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
    }
};
