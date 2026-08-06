<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_memories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('key', 160);
            $table->longText('value');
            $table->json('tags')->nullable();
            $table->unsignedTinyInteger('importance')->default(50);
            $table->uuid('source_run_id')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['agent_id', 'key']);
            $table->index(['workspace_id', 'agent_id', 'importance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_memories');
    }
};
