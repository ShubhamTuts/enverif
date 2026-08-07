<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_action_executions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('run_type', 24);
            $table->string('run_id', 64);
            $table->string('step_key', 128);
            $table->string('action', 191);
            $table->char('arguments_hash', 64);
            $table->string('status', 32)->default('pending');
            $table->json('result')->nullable();
            $table->string('external_id', 191)->nullable();
            $table->string('error_class', 191)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['workspace_id', 'run_type', 'run_id', 'step_key', 'action'],
                'external_action_identity_unique',
            );
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_action_executions');
    }
};
