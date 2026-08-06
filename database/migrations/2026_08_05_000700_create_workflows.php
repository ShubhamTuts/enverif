<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->json('definition');
            $table->json('settings')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('workflow_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('queued')->index();
            $table->string('trigger', 30)->default('manual');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('current_node_id', 80)->nullable();
            $table->json('context')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['workflow_id','created_at']);
        });

        Schema::create('workflow_run_steps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('workflow_run_id')->index();
            $table->foreign('workflow_run_id')->references('id')->on('workflow_runs')->cascadeOnDelete();
            $table->string('node_id', 80);
            $table->string('node_type', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_run_id','node_id']);
        });

        Schema::table('approvals', function (Blueprint $table): void {
            $table->uuid('workflow_run_id')->nullable()->after('run_id')->index();
            $table->unsignedBigInteger('workflow_run_step_id')->nullable()->after('run_step_id')->index();
            $table->foreign('workflow_run_id')->references('id')->on('workflow_runs')->nullOnDelete();
            $table->foreign('workflow_run_step_id')->references('id')->on('workflow_run_steps')->nullOnDelete();
        });

        Schema::table('agent_schedules', function (Blueprint $table): void {
            $table->foreignId('workflow_id')->nullable()->after('agent_id')->constrained('workflows')->nullOnDelete();
            $table->unsignedBigInteger('agent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('agent_schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workflow_id');
            $table->unsignedBigInteger('agent_id')->nullable(false)->change();
        });
        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropForeign(['workflow_run_step_id']);
            $table->dropForeign(['workflow_run_id']);
            $table->dropColumn(['workflow_run_step_id','workflow_run_id']);
        });
        Schema::dropIfExists('workflow_run_steps');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('workflows');
    }
};
