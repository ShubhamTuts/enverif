<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Agents\Tools\ToolRegistry;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RevenueAgentToolsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Agent $agent;
    private AgentRun $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Revenue Workspace',
            'slug' => 'revenue-workspace',
            'timezone' => 'UTC',
            'locale' => 'en',
        ]);
        app(WorkspaceContext::class)->set($this->workspace->id);

        $this->agent = Agent::create([
            'name' => 'Revenue Agent',
            'slug' => 'revenue-agent',
            'instructions' => 'Research and operate revenue workflows.',
            'status' => 'active',
            'max_steps' => 40,
            'max_runtime_seconds' => 900,
            'max_cost_usd' => 10,
        ]);
        $this->run = AgentRun::create([
            'agent_id' => $this->agent->id,
            'status' => 'running',
            'input' => 'Find and persist qualified leads.',
            'started_at' => now(),
            'context' => [],
        ]);
    }

    public function test_single_lead_upsert_reuses_an_existing_lead_by_email(): void
    {
        $tools = app(ToolRegistry::class);

        $first = $tools->execute($this->run, 'leads.upsert', [
            'email' => 'sara@example.test',
            'first_name' => 'Sara',
            'company' => 'Acme',
            'score' => 82,
        ]);
        $second = $tools->execute($this->run, 'leads.upsert', [
            'email' => 'SARA@example.test',
            'company' => 'Acme Group',
            'score' => 94,
        ]);

        self::assertTrue($first->ok);
        self::assertTrue($second->ok);
        self::assertSame(1, Lead::count());
        self::assertSame('Acme Group', Lead::firstOrFail()->company);
        self::assertSame(94, (int) Lead::firstOrFail()->score);
    }

    public function test_agent_can_bulk_persist_researched_leads_without_one_tool_turn_per_lead(): void
    {
        $result = app(ToolRegistry::class)->execute($this->run, 'leads.upsert_many', [
            'leads' => [
                ['email' => 'sara@example.test', 'first_name' => 'Sara', 'company' => 'Acme', 'score' => 94, 'source' => 'research'],
                ['email' => 'alex@example.test', 'first_name' => 'Alex', 'company' => 'Beta', 'score' => 88, 'source' => 'research'],
            ],
        ]);

        self::assertTrue($result->ok, $result->message ?? 'bulk lead upsert failed');
        self::assertSame(2, Lead::count());
        self::assertCount(2, $result->data['leads'] ?? []);
    }

    public function test_agent_can_create_a_draft_campaign_with_multiple_sequence_steps_and_leads(): void
    {
        $lead = Lead::create([
            'first_name' => 'Sara',
            'email' => 'sara@example.test',
            'company' => 'Acme',
            'status' => 'qualified',
            'score' => 94,
        ]);

        $result = app(ToolRegistry::class)->execute($this->run, 'campaigns.create', [
            'name' => 'Qualified SaaS follow-up',
            'description' => 'Multi-touch follow-up for researched leads.',
            'lead_ids' => [$lead->id],
            'steps' => [
                [
                    'channel' => 'email',
                    'action' => 'draft_intro',
                    'delay_minutes' => 0,
                    'content' => 'Personalized introduction based on verified research.',
                    'requires_approval' => true,
                ],
                [
                    'channel' => 'email',
                    'action' => 'draft_follow_up',
                    'delay_minutes' => 2880,
                    'content' => 'Follow up if there is no response.',
                    'requires_approval' => true,
                ],
            ],
        ]);

        self::assertTrue($result->ok, $result->message ?? 'campaign creation failed');
        $campaign = Campaign::firstOrFail();
        self::assertSame('draft', $campaign->status);
        self::assertSame(2, $campaign->steps()->count());
        self::assertSame([$lead->id], $campaign->leads()->pluck('leads.id')->map(fn ($id) => (int) $id)->all());
    }
}
