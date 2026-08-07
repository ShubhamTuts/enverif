<?php

namespace Tests\Feature;

use App\Core\Agents\SystemPromptBuilder;
use App\Models\{Agent, Skill, Workspace};
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SystemPromptRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_prompt_uses_only_assigned_or_explicit_skills_and_does_not_launch_tools_for_small_talk(): void
    {
        $workspace = Workspace::create([
            'name' => 'Prompt Workspace',
            'slug' => 'prompt-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        app(WorkspaceContext::class)->set($workspace->id);

        $agent = Agent::create([
            'workspace_id' => $workspace->id,
            'name' => 'General Agent',
            'slug' => 'general-agent',
            'instructions' => 'Help the operator accurately.',
            'status' => 'active',
            'max_steps' => 20,
            'max_runtime_seconds' => 300,
            'max_cost_usd' => 2,
        ]);

        $unassigned = Skill::create([
            'workspace_id' => null,
            'name' => 'Unassigned Global Skill',
            'slug' => 'unassigned-global-skill',
            'body' => 'UNASSIGNED_SKILL_MUST_NOT_LEAK',
            'status' => 'active',
            'built_in' => true,
        ]);
        $assigned = Skill::create([
            'workspace_id' => $workspace->id,
            'name' => 'Assigned Skill',
            'slug' => 'assigned-skill',
            'body' => 'ASSIGNED_SKILL_BODY',
            'status' => 'active',
            'built_in' => false,
        ]);
        $agent->skills()->attach($assigned->id);

        $prompt = app(SystemPromptBuilder::class)->build($agent);

        self::assertStringContainsString('ASSIGNED_SKILL_BODY', $prompt);
        self::assertStringNotContainsString('UNASSIGNED_SKILL_MUST_NOT_LEAK', $prompt);
        self::assertStringContainsString('For greetings, small talk, and general questions that do not require current external or workspace state, answer directly without calling tools.', $prompt);
        self::assertStringContainsString('Do not launch unrelated mission work just because the operator sent a greeting or conversational message.', $prompt);
        self::assertStringContainsString('Choose tools by their names, descriptions, schemas, capabilities, and the operator intent', $prompt);

        self::assertNotNull($unassigned->id);
    }
}
