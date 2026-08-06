<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->foreignId('default_agent_id')->nullable()->after('agent_id')->constrained('agents')->nullOnDelete();
            $table->foreignId('default_model_connection_id')->nullable()->after('default_agent_id')->constrained('model_connections')->nullOnDelete();
            $table->string('default_model', 160)->nullable()->after('default_model_connection_id');
            $table->string('default_effort', 20)->default('standard')->after('default_model');
            $table->text('summary')->nullable()->after('title');
            $table->timestamp('archived_at')->nullable()->after('last_message_at')->index();
            $table->index(['workspace_id', 'user_id', 'archived_at', 'last_message_at'], 'chat_thread_history_index');
        });

        DB::table('chat_threads')->whereNull('default_agent_id')->update([
            'default_agent_id' => DB::raw('agent_id'),
        ]);

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->string('kind', 24)->default('message')->after('role');
            $table->string('status', 24)->nullable()->after('kind');
        });

        Schema::create('chat_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('chat_threads')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('chat_messages')->cascadeOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'thread_id', 'created_at'], 'chat_attachment_workspace_thread_index');
        });

        Schema::table('agents', function (Blueprint $table): void {
            $table->string('avatar_path', 500)->nullable()->after('description');
            $table->string('default_effort', 20)->default('standard')->after('model');
        });

        Schema::table('workflow_runs', function (Blueprint $table): void {
            $table->string('mode', 20)->default('execute')->after('trigger');
            $table->uuid('retry_of')->nullable()->after('mode')->index();
        });
    }

    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table): void {
            $table->dropColumn(['mode', 'retry_of']);
        });
        Schema::table('agents', function (Blueprint $table): void {
            $table->dropColumn(['avatar_path', 'default_effort']);
        });
        Schema::dropIfExists('chat_attachments');
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'status']);
        });
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->dropIndex('chat_thread_history_index');
            $table->dropConstrainedForeignId('default_model_connection_id');
            $table->dropConstrainedForeignId('default_agent_id');
            $table->dropColumn(['default_model', 'default_effort', 'summary', 'archived_at']);
        });
    }
};
