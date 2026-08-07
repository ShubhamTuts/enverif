<p align="center">
  <img src="public/assets/enverif-mark.svg" width="84" height="84" alt="Enverif logo">
</p>

<h1 align="center">Enverif</h1>
<p align="center"><strong>Open-source AI sales employees for your agency or business.</strong></p>
<p align="center">
  Give Enverif a sales outcome. Your AI employees can research prospects, find and qualify leads, prepare personalized outreach, follow up, manage campaigns, operate connected tools and keep revenue work moving — from one self-hosted workspace.
</p>

<p align="center">
  <a href="https://enverif.com">Website</a> ·
  <a href="docs/getting-started/installation.md">Get started</a> ·
  <a href="docs/PRODUCT-REQUIREMENTS.md">Product</a> ·
  <a href="CHANGELOG.md">Changelog</a> ·
  <a href="CONTRIBUTING.md">Contribute</a>
</p>

<p align="center">
  <a href="LICENSE"><img alt="MIT License" src="https://img.shields.io/badge/license-MIT-111827.svg"></a>
  <img alt="PHP 8.3+" src="https://img.shields.io/badge/PHP-8.3%2B-777BB4.svg">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20.svg">
  <img alt="Self-hosted" src="https://img.shields.io/badge/self--hosted-yes-22C55E.svg">
  <img alt="BYOK" src="https://img.shields.io/badge/models-BYOK-6366F1.svg">
</p>

<p align="center">
  <a href="https://enverif.com">
    <img src="https://enverif.com/assets/hero-workspace.png" alt="Enverif AI sales employees workspace" width="100%">
  </a>
</p>

---

## Hire AI employees, not another chatbot

Most AI tools wait for another prompt.

**Enverif is built to keep working.**

Create specialized AI employees for the jobs your revenue team repeats every day: prospect research, lead generation, qualification, account enrichment, outreach preparation, follow-up, campaign operations and sales administration. Give each employee its own instructions, model, skills, tools, limits, memory and schedule — then let them collaborate through durable workflows.

You stay in control of the boundary between **autonomous work** and **human approval**. Research and internal work can move quickly; external writes such as sending email can remain approval-first or be explicitly enabled for autonomous execution.

### Tell the team what outcome you want

```text
Find 50 web-design prospects in Australia that look ready to buy.
Research each company, identify the owner or founder, score the opportunity,
save the best 15 as leads and prepare personalized outreach for approval.
```

```text
Review every warm lead that has not replied in the last 5 days.
Research a fresh reason to contact them, draft a useful follow-up,
and queue the strongest messages for me to approve.
```

```text
Build an outbound workflow for local service businesses in the US.
Use my research tools, qualify companies before outreach, update lead status,
and hand high-intent replies to my closer agent.
```

Enverif turns a prompt into **durable sales work**: agent runs, tool calls, approvals, lead updates, campaign activity and workflow state are persisted instead of disappearing when a browser tab closes.

## Build your AI sales team

Create agents for roles such as:

| AI employee | What you can make it responsible for |
|---|---|
| **Prospect Researcher** | Find companies, investigate websites, collect business context and build account briefs. |
| **Lead Generation Agent** | Discover prospects through connected data sources and turn qualified results into persistent leads. |
| **AI SDR** | Qualify accounts, identify decision-makers and prepare personalized outreach. |
| **Follow-up Agent** | Review conversations, detect stale opportunities and prepare context-aware follow-ups. |
| **Sales Operations Agent** | Keep lead state, campaign activity, schedules and workflow handoffs organized. |
| **Specialist / Closer Agent** | Receive delegated high-value opportunities with focused instructions, context and tool access. |

These are roles, not hard-coded bots. An Enverif agent is configured from its instructions, model, skills, plugin permissions, limits, memory, schedules and delegation policy, so you can shape employees around your own sales process.

## One workspace for the complete sales loop

**Research → qualify → save → outreach → follow up → hand off → learn → repeat.**

Enverif brings the operating pieces into one system:

- **Agentic chat** — talk to the whole revenue workspace from one ChatGPT-style interface. Mention `@agents`, `@plugins`, `@skills`, `@workflows`, `@leads` and `@campaigns`, attach private files and choose the right model/effort for the job.
- **Persistent AI agents** — each employee has instructions, model preferences, skills, tool permissions, memory, limits, schedules and delegation controls.
- **Lead workspace** — keep prospects, scores, research summaries, provenance and activity history as durable business state.
- **Campaigns** — organize prospects and ordered sales steps instead of leaving outreach context inside isolated chats.
- **Visual workflows** — connect triggers, agents, plugin actions, skills, conditions, delays, leads, campaigns, approvals and outputs on a durable workflow engine.
- **Schedules** — give employees recurring responsibilities with cron-based schedules and timezone support.
- **Approvals & policy** — classify tool risk and decide which actions can run autonomously, which must ask and which remain denied.
- **Run history** — inspect agent runs, workflow runs, steps, retries, child agents, tool activity, token/cost accounting and terminal state.

## Connect the tools your sales team already uses

Enverif ships with first-party and extensible connections for revenue work, including:

**Gmail · Microsoft Outlook · SMTP · Apollo · Apify · Google Maps research · Google Search Console · Google Analytics · Calendly · Google Sheets · Slack · Buffer · webhooks for n8n / Zapier / Make · MCP servers**

Connections are capabilities your employees can use. Skills describe **how to do the job**. Workflows define **how repeatable work should execute**. Agents decide **what to do next** inside the permissions you give them.

## Bring your own AI models

Use your own provider credentials and keep model choice independent from the employee or workflow design.

Supported provider families include:

- OpenAI
- Anthropic Claude
- Google Gemini
- DeepSeek
- custom compatible model IDs where supported

Credentials are encrypted at rest. Model connections expose health/diagnostic state so provider failures can be separated from agent or workflow failures.

## Designed for agencies

An agency can use Enverif as an internal AI sales team that continuously turns market research into organized pipeline work.

A practical setup might be:

```text
Research Agent
    ↓
Lead Qualification Agent
    ↓
AI SDR
    ↓
Human approval or autonomous send policy
    ↓
Follow-up Agent
    ↓
Closer / owner handoff
```

Use separate agents for niches, geographies, offers or client acquisition motions. Reuse skills and workflows across them while keeping tool access, schedules and action policy explicit.

## Designed for businesses

For a small business, Enverif can become the operating layer behind repetitive sales work without requiring a large sales-ops stack.

Use it to research target accounts, build lead lists, keep prospect context together, prepare outreach, monitor follow-up work, route qualified opportunities and automate the repetitive steps around your team — while keeping important external actions governed.

## Not just prompts: durable execution

A useful AI employee must survive more than one chat turn.

Enverif persists execution so long-running work can be inspected and resumed. Agent and workflow runs record their execution state, and mutable configuration is snapshotted so editing an agent later does not silently rewrite work already in flight.

```text
Prompt / Schedule / Webhook
            │
            ▼
        AI Employee
            │
      ┌─────┴─────┐
      │           │
   Skills      Plugins / MCP
      │           │
      └─────┬─────┘
            │
      Policy + Approval
            │
            ▼
 Leads / Campaigns / External Actions
            │
            ▼
     Durable Run History
```

## Autonomy without giving up control

Every tool action is classified into a capability/risk class:

`read` · `internal_write` · `network` · `external_write` · `secrets` · `destructive`

The important defaults are simple:

1. Explicit deny wins.
2. Read and internal operations can stay fast.
3. External writes ask by default unless autonomous writes are deliberately enabled.
4. Secret-bearing operations require approval.
5. Destructive actions are denied by default and remain separately permissioned.
6. Delegated child agents cannot exceed their parent policy ceiling.
7. Tool access remains workspace-scoped and run-scoped.

The goal is not to make an employee ask about every harmless step. The goal is to let useful autonomous work happen **inside a boundary you can understand and audit**.

## Runs on shared hosting, VPS or Docker

Redis is optional. Enverif supports two runtime profiles while keeping MySQL as authoritative durable state.

| | Shared Hosting Mode | Performance Mode |
|---|---|---|
| Durable state | MySQL | MySQL |
| Queue | Database | Redis preferred |
| Cache / locks | Database | Redis preferred |
| Background execution | Bounded cron | Persistent workers |
| SSH required after package upload | No | Usually |
| Core product features | Full | Full |

That makes Enverif deployable on ordinary hosting as well as a dedicated server.

### Shared hosting / no SSH

Download the `*-shared-hosting.zip` package from GitHub Releases, upload it, extract it, create a MySQL database and visit:

```text
https://your-domain.example/install
```

The installer checks PHP/extensions, database access, writable storage, runtime mode and deployment path. After installation, run the generated cron command once per minute:

```bash
php /absolute/path/to/enverif/artisan enverif:tick
```

`enverif:tick` acquires a lock, dispatches schedules, drains bounded queue work, writes a heartbeat and exits cleanly so durable work can continue on the next tick.

### Docker

```bash
git clone https://github.com/ShubhamTuts/enverif.git
cd enverif
./install.sh
```

Then open the installer and select Performance Mode when Redis is available.

### Native VPS

```bash
git clone https://github.com/ShubhamTuts/enverif.git
cd enverif
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

Point your web server at `public/`, complete `/install`, and run queue workers/scheduling under your preferred process manager.

See the [installation guide](docs/getting-started/installation.md), [shared-hosting guide](docs/hosting/shared-hosting.md) and [Hostinger guide](docs/hosting/hostinger.md).

## Email is a first-class sales capability

### Gmail

Connect a Google OAuth client and give an employee permission to inspect the mailbox, search threads, prepare drafts, send or reply according to policy.

### Outlook

Connect Microsoft Graph through an Entra application with delegated mail permissions.

### SMTP

Use Hostinger Email, cPanel mailboxes or any standard SMTP provider with encrypted credentials.

Email `send` and `reply` are treated as `external_write` actions. They remain approval-first unless autonomous external writes are deliberately enabled for the executing employee/workflow.

## Skills, plugins and MCP

Enverif is meant to grow beyond the integrations that ship in the repository.

- **Skills** are reusable job knowledge packaged as `SKILL.md` content.
- **Plugins / connections** expose authenticated capabilities and actions.
- **MCP servers** expose remote tools through the Model Context Protocol.
- **Workflows** compose deterministic and agentic execution into repeatable operations.

Trusted Git-hosted skills retain provenance/checksum metadata and pass security scanning before installation. External connectors keep their own developer attribution instead of being relabeled as first-party Enverif integrations.

## Architecture

```text
Chat / Schedules / Webhooks
           │
           ▼
 Durable orchestration
    ┌──────┴──────┐
  Agents       Workflows
    │              │
 Models        Node engine
    └──────┬───────┘
           │
 Capability policy + approvals
           │
 Plugins / Skills / MCP / Files
           │
 Leads / Campaigns / Activities
           │
 MySQL authoritative state
           │
 Database queue OR Redis workers
```

See [docs/architecture.md](docs/architecture.md) for the deeper runtime model.

## Repository map

```text
app/Core/Agents        agent loop, memory, delegation, tools and capability policy
app/Core/Chat          conversation context, files and structured selections
app/Core/Workflows     validation, templating and durable workflow execution
app/Core/Email         OAuth refresh, message building and email risk policy
app/Core/Runtime       shared-host/runtime profiles, heartbeat and bounded tick
app/Core/Connectors    plugin contracts and first-party drivers
app/Core/Models        BYOK provider adapters and model registry
app/Core/Skills        skill parser, installer and security scanner
app/Core/Mcp           MCP discovery and calls
app/Core/Audit         durable audit events
plugins/builtin        first-party capabilities
plugins/external       third-party connector plugins
skills/builtin         starter revenue skills
docs                   operator and developer documentation
scripts                verification and release tooling
```

## Build and verify

For behavior changes, add a failing test first. The core local verification path is:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/standalone/run.php
php scripts/verify.php
php artisan test
npm run check
npm run build
```

CI exercises PHP 8.3, 8.4 and 8.5, Redis-backed execution, database-only shared-hosting execution, the browser installer/login/core screens, the frontend build and workflow YAML validation.

## Contribute

Enverif is open source and designed to be extended.

Contributions are welcome across **Core, Agents, Plugins/Connectors, Skills, Workflows, Models, Translations, UI/UX, Documentation and Testing**.

Start with [CONTRIBUTING.md](CONTRIBUTING.md) and [docs/contributing/index.md](docs/contributing/index.md).

If Enverif is useful to you, **star the repository, build an employee or integration for your workflow, and share what you automate.**

## Maintainer

Enverif is maintained by **Codefreex**. First-party Enverif integrations may identify Codefreex as their developer; third-party extensions retain their actual author/developer attribution.

## License

Enverif is released under the [MIT License](LICENSE). External APIs, third-party plugins, model providers and upstream skill projects retain their own terms.
