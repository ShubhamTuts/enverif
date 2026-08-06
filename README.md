<p align="center">
  <img src="public/assets/enverif-mark.svg" width="82" height="82" alt="Enverif logo">
</p>

<h1 align="center">Enverif</h1>
<p align="center"><strong>The open-source agent operating system for revenue.</strong></p>
<p align="center">A durable, approval-aware AI workspace for prospect research, lead generation, outreach, follow-up, sales workflows and revenue operations — self-hosted on anything from shared hosting to Redis-powered infrastructure.</p>

<p align="center">
  <a href="LICENSE"><img alt="MIT" src="https://img.shields.io/badge/license-MIT-111827.svg"></a>
  <img alt="PHP 8.3+" src="https://img.shields.io/badge/PHP-8.3%2B-777BB4.svg">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20.svg">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-durable_state-4479A1.svg">
  <img alt="Redis optional" src="https://img.shields.io/badge/Redis-optional-DC382D.svg">
</p>

<p align="center">
  <a href="https://shubhamtuts.github.io/enverif/">Documentation</a> ·
  <a href="docs/getting-started/installation.md">Install</a> ·
  <a href="CONTRIBUTING.md">Contribute</a> ·
  <a href="SECURITY.md">Security</a>
</p>

---

## One conversation. Your complete revenue stack.

Enverif opens into an agentic, ChatGPT-style revenue workspace. Pick an `@agent`, tag a `@plugin`, `@skill` or `@workflow`, then ask for the business outcome. Every turn still becomes an immutable durable run with tool traces, permission decisions and approvals under the hood.

**Examples**

```text
Find 30 HVAC companies in Melbourne, qualify the top 10,
find decision-makers, save them as leads and draft personalized emails.
```

```text
Review my hot leads, find who has not replied, research fresh context,
and prepare follow-ups for approval.
```

```text
Use Apollo + Google Maps research, hand deep research to my specialist
agent, then add qualified companies to the Australia outbound campaign.
```

## Enverif 1.3.7

The 1.3.7 release fixes chat live progress (queue re-kick while polling), stops the composer from collapsing after send, adds removable context tags, and repairs workflow node dragging / palette drop plus a cleaner single-trigger starter canvas.

## Enverif 1.3.6

The 1.3.6 release upgrades the visual workflow studio toward an n8n-style builder (grouped palette, port links, pan, auto-layout, edge editing) and adds a first-party **Google Sheets** OAuth connector with real Google Sheets branding. Plugin action schemas are exposed to the workflow inspector so connector nodes are easier to wire end-to-end.

## Enverif 1.3.5

The 1.3.5 release hardens the AI tool/provider boundary so DeepSeek and OpenAI-compatible providers accept Enverif tool schemas (empty JSON Schema `properties` encode as `{}`, not `[]`). A capability-aware Model Registry backs suggested catalogs. First-party Slack and Buffer plugins land for team messaging and social scheduling. Chat run settings collapse into a compact “Run settings” control, and agents gain creative/social publishing configuration for specialist roles.

## Enverif 1.3.4

The 1.3.4 release fixes two production DeepSeek failures: encrypted API-key decrypt errors (“The MAC is invalid” after an `APP_KEY` change) now show a clear re-enter-key recovery path, and tool names with dots (`memory.search`, `connector.*`, `mcp.*`) are sanitized for DeepSeek/OpenAI/Anthropic while remaining reversible for tool routing. Chat surfaces failed provider/credential runs as Error chips, and connection forms keep consistent action-row spacing.

1.3.3 made BYOK model catalogs production-current (DeepSeek V4, current Claude/Gemini/OpenAI IDs), remapped retired model aliases, and polished chat/plugin UI. See the [product requirements](docs/PRODUCT-REQUIREMENTS.md) and [changelog](CHANGELOG.md).

## What ships

- **Agentic chat** — searchable/grouped chat history, persistent per-thread agent/model/effort defaults, one-message overrides, structured `@agent`, `@plugin`, `@skill`, `@workflow`, `@lead` and `@campaign` context, private attachments and stop controls.
- **AI agents** — instructions, model, default effort, private avatars, skills, plugin connections, limits, memory, delegation and explicit capability policy; each run snapshots its mutable execution configuration for durability.
- **Durable execution** — persisted messages, steps, retries, approvals, child runs, token/cost accounting and terminal audit state.
- **Visual workflows** — n8n/Make-style canvas with triggers, agents, plugin actions, skills, conditions, delays, leads, campaigns, approvals and outputs, plus runtime validation, dry-run/test execution, retry/resume controls and per-node inspection.
- **Email automation** — first-party Gmail OAuth, Microsoft Outlook OAuth and SMTP plugins developed by **Codefreex**.
- **Approval-first outreach** — drafts can be automated; send/reply are always external writes and ask by default. Autonomous sending is an explicit agent/workflow switch.
- **Sales workspace** — leads, scores, research summaries, provenance, activities, campaigns and ordered campaign steps.
- **Schedules** — month calendar and recurring agent/workflow schedules with five-field cron + IANA timezone support.
- **BYOK models** — OpenAI, Anthropic Claude, Google Gemini and DeepSeek with encrypted credentials and custom model IDs.
- **Revenue plugins** — Apify, Apollo, GSC, GA4, Google Maps research, Calendly, n8n/Zapier/Make/custom webhook, Gmail, Outlook and SMTP.
- **MCP** — remote Streamable HTTP tool servers.
- **Skills** — local or trusted Git-hosted `SKILL.md` packages with provenance, checksums and security scanning.
- **System health** — runtime, queue/cache, scheduler heartbeat, pending/failed jobs, PHP, Redis availability, storage and base URL.
- **English, French and Dutch** — translation-key parity checked at release time.

## Runs on ordinary shared hosting

Redis is **not required**. Enverif has two full runtime profiles:

| | Shared Hosting Mode | Performance Mode |
|---|---|---|
| Durable state | MySQL | MySQL |
| Queue | Database | Redis preferred |
| Cache/locks | Database | Redis preferred |
| Background execution | bounded cron | persistent workers |
| SSH required after package upload | No | Usually |
| Features | Full | Full |

The ready-to-upload shared-hosting release contains Composer dependencies and compiled assets. Upload → extract → create MySQL database → visit `/install` → copy the generated cron command.

```bash
php /absolute/path/to/enverif/artisan enverif:tick
```

Run it once per minute. `enverif:tick` acquires a lock, dispatches schedules, drains queue work inside a configured time budget, writes a heartbeat, and exits cleanly. Durable agent/workflow state resumes on the next tick.

Enverif supports:

- root domains and subdomains;
- nested paths such as `https://example.com/tools/enverif/`;
- Apache document roots pointed at `public/`;
- file-manager-only deployments using the included secure root `.htaccess`;
- Hostinger, cPanel and Plesk cron workflows;
- Redis/VPS/Docker performance deployments.

Read the [shared-hosting guide](docs/hosting/shared-hosting.md) or the dedicated [Hostinger guide](docs/hosting/hostinger.md).

## Install

### Shared hosting / no SSH

Download the `*-shared-hosting.zip` artifact from GitHub Releases, extract it and visit:

```text
https://your-domain.example/install
```

The installer checks PHP/extensions, MySQL CREATE permissions, storage, current subfolder, execution constraints and Redis availability. Missing Redis is informational — Shared Hosting Mode selects database queue/cache automatically.

### Docker

```bash
git clone https://github.com/ShubhamTuts/enverif.git
cd enverif
./install.sh
```

Open `http://localhost:8080/install` and use Performance Mode when Redis is available.

### Native VPS

```bash
git clone https://github.com/ShubhamTuts/enverif.git
cd enverif
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

Point Nginx/Apache to `public/`, finish `/install`, then run queue workers and a scheduler under Supervisor/systemd.

## Email automation

### Gmail

Create a Google OAuth web client, save its client ID/secret in a Gmail plugin connection, then click **Connect mailbox**. Actions: account, search, thread, draft, send, reply.

### Outlook

Create a Microsoft Entra app with delegated `User.Read`, `Mail.ReadWrite`, `Mail.Send` and offline access, then connect it through the Outlook plugin.

### SMTP

Use SMTP for Hostinger Email, cPanel mailboxes or any standard provider. Host/port/encryption/from identity are configuration; username/password are encrypted credentials.

**Safety invariant:** email `send` and `reply` remain `external_write` actions. They require approval unless the executing agent/workflow explicitly enables autonomous external writes.

## Visual workflows

The workflow engine is durable, not a browser-only diagrammer. Nodes and transitions are persisted so delays, agent delegation, queue restarts and human approvals can resume safely.

Supported nodes:

```text
Manual / Schedule / Webhook
        ↓
Agent → Plugin → Skill → Condition ─ true/false
                         ↓
                 Delay / Lead / Campaign
                         ↓
                    Approval → Output
```

Use context expressions such as `{{input.company}}`, `{{nodes.research.email}}` and `{{previous.lead_id}}`.

## Safety model

Tool calls declare one of:

`read` · `internal_write` · `network` · `external_write` · `secrets` · `destructive`

1. Explicit deny wins.
2. Read/internal operations stay fast by default.
3. External writes ask unless autonomous writes are deliberately enabled.
4. Secret-bearing operations always ask.
5. Destructive actions are denied by default and still require approval when enabled.
6. Delegated child agents inherit the strictest policy ceiling of every ancestor.
7. A chat-tagged plugin is exposed only for that immutable run and only inside the current workspace.

## Architecture

```text
Chat / UI / Schedules / Webhooks
              │
      Durable orchestration
       ┌──────┴───────┐
     Agents        Workflows
       │               │
  Model providers  Node engine
       └──────┬────────┘
              │
   Capability policy + approvals
              │
   Tools / Plugins / Skills / MCP
              │
       MySQL authoritative state
              │
  Database queue OR Redis workers
```

See [docs/architecture.md](docs/architecture.md).

## Repository map

```text
app/Core/Agents        agent loop, memory, delegation, tools, capability policy
app/Core/Chat          bounded conversation context + tagged selections
app/Core/Workflows     definition validation, templating, durable workflow engine
app/Core/Email         OAuth token refresh, message building, email risk policy
app/Core/Runtime       shared-host/runtime profiles, heartbeat, bounded tick, web cron
app/Core/Connectors    plugin contracts and first-party drivers
app/Core/Models        BYOK provider adapters
app/Core/Skills        skill parser/installer/scanner
app/Core/Mcp           MCP discovery and calls
app/Core/Audit         hash-linked audit events
plugins/builtin        first-party plugins — developer: Codefreex
plugins/external       third-party connector plugins
skills/builtin         starter revenue skills
docs                   operator + developer documentation
scripts                 release verification, packaging and Pages build
```

## Contributors

Contributions are welcome across **Core, Plugin/Connector, Skill, Translation, UI/UX, Documentation and Testing**. Start with [CONTRIBUTING.md](CONTRIBUTING.md) and [docs/contributing/index.md](docs/contributing/index.md).

For behavior changes, add a failing test first. At minimum run:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/standalone/run.php
php scripts/verify.php
php artisan test
```

CI additionally runs MySQL and both Redis/database-runtime paths.

## Attribution

**Enverif is maintained by Codefreex.** First-party Enverif plugins use `developer: Codefreex`. Third-party plugins must retain their real author/developer attribution.

## License

Enverif is released under the [MIT License](LICENSE). External APIs, third-party plugins and upstream skill projects retain their own terms.
