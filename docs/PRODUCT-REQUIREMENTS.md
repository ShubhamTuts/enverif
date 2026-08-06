# Enverif Product Requirements Document

**Product:** Enverif
**Maintainer:** Codefreex
**Target release:** 1.3.7

### 1.3.7 chat live progress + workflow studio reliability

Version 1.3.7 fixes chat composer collapse after send, removable context tags, live status polling with queue re-kick (no refresh required), workflow node drag/drop/connectors, and a single-trigger starter canvas.

### 1.3.6 workflow studio + Google Sheets addendum

Version 1.3.6 upgrades the visual workflow builder toward an n8n-style studio (grouped searchable palette, port linking, pan, auto-layout, edge editing) and ships a first-party Google Sheets OAuth connector with brand-accurate iconography for spreadsheet read/write automation.

### 1.3.5 production OS hardening addendum

Version 1.3.5 closes the DeepSeek tool-schema blocker (`[] is not of type 'object'`), introduces a capability-aware Model Registry, adds first-party Slack and Buffer connectors, collapses chat run settings into a compact control, and adds agent creative/social publishing configuration (brand voice, Buffer/Slack defaults) for specialist agents.

### 1.3.4 credential and tool-name hotfix addendum

Version 1.3.4 closes two DeepSeek production defects:

1. Encrypted model/plugin/MCP credentials that fail Laravel Crypt MAC verification (typically after `APP_KEY` rotation) must surface an actionable “re-enter key under AI Models / APP_KEY mismatch” message — never the raw “The MAC is invalid” string framed as a model-ID failure.
2. Provider tool function names that contain dots or other characters outside `^[a-zA-Z0-9_-]+$` must be sanitized for DeepSeek and other OpenAI-compatible / Anthropic tool schemas, with a reversible mapping so ToolRegistry routing stays correct. Gemini may keep dotted names when the Gemini API allows them.
3. Chat final messages for failed provider/credential runs must be visually distinct Error chips, and connection edit/test paths must allow recovering by pasting a fresh secret.

### 1.3.3 production readiness addendum

Version 1.3.3 closes BYOK model and chat UI production gaps discovered after shared-hosting deployment of 1.3.1/1.3.2:

1. Suggested model catalogs must track current OpenAI, Anthropic, Gemini and DeepSeek public API IDs; retired DeepSeek/Gemini names must remap rather than fail obscurely.
2. Provider failures must surface HTTP status and API error text into the chat final message (not only `storage/logs`).
3. Chat attachment control must render a real icon; AI Models / Plugins cards must use bundled local brand assets (no remote favicon dependency).
4. Light-mode primary button hover and form action gaps must remain readable and spaced.
5. The GitHub application repository ships the Laravel app + `docs/` Markdown; marketing/docs static sites under `websites/` are optional packaging artifacts, not required for application CI.

### 1.3.1 installer-contract hotfix addendum

Version 1.3.1 closes a shared-hosting installer view-contract defect discovered on the live Laravel 13.24 deployment. `InstallController@index` must always provide both the provider model catalog and its safely serialized JSON form; the installer Blade template also retains a defensive local fallback so a partially overwritten deployment does not crash before database setup. A fresh `/install` HTTP render is an explicit CI release gate, and critical controller-to-view payload contracts are statically verified. Guest/login/installer assets carry the release version to prevent stale LiteSpeed/browser assets after upgrades.

### 1.3 production-hardening addendum

The approved 1.2 architecture remains the product contract. Version 1.3 is the production-hardening release that makes the contract reliable on real shared hosting: in-place chat transport, versioned frontend assets, corrected desktop shell geometry, bounded post-response queue progress for interactive work, explicit workflow condition branches, model-readiness validation, deterministic local integration icons, and framework-level HTTP smoke coverage for every primary authenticated screen. The main product lockup is **Enverif**; Codefreex is the maintainer and first-party plugin developer, not a product-name suffix.
**Status:** Approved product architecture (Approach A) / implementation contract  
**License:** MIT  
**Primary stack:** Laravel 13, PHP 8.3+, MySQL, database queue/cache or Redis, Blade + Vite  
**Primary deployment targets:** Hostinger/cPanel/Plesk shared hosting, Apache/Nginx VPS, Docker  

## 1. Product vision

Enverif is an open-source revenue agent operating system. The primary product experience is a persistent, ChatGPT-style revenue workspace where users can ask for outcomes, switch agents and models, tag capabilities, upload context, approve high-risk actions, inspect durable execution, and turn repeatable work into visual workflows.

The platform must be usable as the control plane for an entire small revenue team: prospect research, qualification, enrichment, lead capture, outreach drafting, approved/autonomous email sending, follow-up, scheduling, campaigns, workflow automation, reporting, and operational oversight.

The product is not a thin chat wrapper. Chat is the command surface over a durable agent/workflow runtime with permissions, approvals, memory, schedules, plugins, skills, MCP, leads, campaigns, audit, and recoverable background execution.

## 2. Non-negotiable product principles

1. **Chat-first, not chat-only.** Existing operational features remain available and become controllable from chat.
2. **Durable by default.** User intent, selections, messages, runs, tool actions, approvals, workflow state, and final responses are persisted before UI confirmation.
3. **Workspace isolation is mandatory.** No route binding, background job, connector, attachment, run, or plugin asset may cross workspaces.
4. **Approval-first external effects.** Research, drafting, internal lead updates, and safe reads may run autonomously. Email sends/replies and other external writes ask unless the executing agent/workflow explicitly enables autonomous external writes.
5. **No infrastructure downgrade on shared hosting.** Shared-hosting mode uses database queue/cache and bounded cron; it does not remove agent, workflow, approval, email, plugin, skill, or MCP features.
6. **No hidden runtime failure.** A stalled queue, missing model, failed provider, invalid workflow, disabled connector, or pending approval must surface as an actionable state in the UI.
7. **Extension metadata is first-class.** Plugins and skills have author/developer attribution, icons, documentation, capabilities, provenance, and versioning.
8. **Codefreex attribution is accurate, not hard-coded over third parties.** First-party plugins identify Codefreex with a hyperlink; third-party extensions retain their actual developer metadata.
9. **Release claims require integration evidence.** Static source checks are insufficient for production readiness.

## 3. Current-source audit and release blockers

The 1.1.2 source is not a production-complete baseline. The following findings are release blockers and must be corrected before 1.3.0.

### 3.1 Fatal UUID/workspace route-binding collision

`App\Models\Concerns\BelongsToWorkspace` defines `resolveRouteBindingQuery()`. Laravel 13's UUID concern also provides that method through `HasUniqueStringIds`. `AgentRun` and `WorkflowRun` use both traits, producing fatal class composition errors before the page can render.

Required resolution:

- `BelongsToWorkspace` is limited to workspace global and creating scopes; it must not implement `resolveRouteBindingQuery()`.
- Laravel's `HasUuids` / `HasUniqueStringIds` remains the sole UUID route-binding validator on UUID-backed models.
- Workspace isolation is applied by the global Eloquent scope, so UUID validation and workspace boundaries compose without competing trait methods.
- Add a static scan preventing any workspace concern from redefining `resolveRouteBindingQuery()` while UUID concerns are in use.
- Add HTTP route tests for `/agents`, `/runs/{uuid}`, `/workflows`, and `/workflow-runs/{uuid}` including invalid UUID and cross-workspace cases.
- `php artisan route:list`, model boot, and protected-route HTTP smoke tests are release gates.

### 3.2 Installer and deployment recovery

Past failures exposed session-table bootstrap order, workspace pivot naming, incomplete-install recovery, stale installed markers, and root Apache routing. 1.3.0 treats these as one installer state machine, not unrelated patches.

Required states:

`fresh → prerequisites → database validated → schema migrated → owner assigned → runtime configured → health verified → installed`

An install is complete only when required schema and owner membership exist. A marker file alone is not authoritative.

### 3.3 Chat is persistent but incomplete

The current `chat_threads` model stores only `agent_id`, title, and timestamp. Messages persist plain text and generic metadata. Missing product behavior includes thread model/effort defaults, thread search, rename, archive, attachments, agent avatars, structured mentions, per-message overrides, rich execution states, provider/model provenance, retry/edit semantics, and a canonical final-response record.

### 3.4 Plugins expose placeholder identity

The connector catalog renders generated initials and does not expose manifest-driven icons, developer URL, homepage, documentation URL, or category. Built-in manifests name Codefreex but the UI cannot hyperlink the developer or render real product artwork.

### 3.5 Workflow canvas is ahead of workflow execution UX

The visual editor persists nodes and edges, but runtime diagnosis is limited. `skill` currently surfaces skill instructions as output instead of being a meaningful executable workflow behavior. Test-run, node validation, per-node inspection, retry/resume, queue-health feedback, and activation gating need to be complete.

### 3.6 Release automation is fragmented

Multiple release/publish workflows overlap, one workflow has a hard-coded release version, and lockfile generation can write directly to `main`. 1.3.0 uses one deterministic release path tied to the repository `VERSION` file and successful integration CI.

## 4. Primary users

### 4.1 Founder / owner

Wants one place to ask Enverif to find prospects, research companies, prepare outreach, follow up, and monitor the pipeline without manually switching tools.

### 4.2 Sales operator

Needs repeatable agent/workflow execution, approval queues, lead/campaign context, schedules, and reliable email actions.

### 4.3 Agency / multi-client operator

Needs strict workspace separation, independent credentials/models, auditable actions, reusable workflows, and predictable shared-host/VPS deployment.

### 4.4 Developer / contributor

Needs stable extension contracts, local development documentation, test fixtures, plugin/skill/MCP guides, release rules, and backward-compatible metadata.

## 5. Global information architecture

The authenticated shell must be globally consistent on desktop, tablet, and mobile.

### 5.1 Sidebar

Top-to-bottom:

1. Enverif brand
2. **New chat** primary action
3. Search chats
4. Recent chat history
5. Schedules
6. Plugins
7. Skills
8. Workflows
9. More
   - Agents
   - Leads
   - Campaigns
   - Models
   - MCP
   - Approvals
   - Audit
   - System Health / Overview
10. Help
11. Settings
12. Account/profile control
13. Logout

On narrow screens the sidebar becomes a drawer with keyboard/focus-safe navigation.

### 5.2 Settings

Settings is the central configuration surface, split into:

- Profile
- Password & security
- Appearance & locale
- Workspaces
- AI models/providers
- APIs & integrations
- Email automation
- Runtime & scheduler
- System health
- Data/privacy

Deep links to dedicated CRUD screens may remain, but users should not need to discover core API/model settings through unrelated navigation.

## 6. Agentic chat — Approach A

### 6.1 Thread-persistent defaults

Every chat thread stores:

- `workspace_id`
- `user_id`
- `title`
- `default_agent_id`
- `default_model_connection_id`
- `default_model`
- `default_effort`
- `last_message_at`
- `archived_at`
- optional thread summary / context checkpoint

Changing the agent/model/effort in the composer updates the thread default by default. A **This message only** control creates a per-message override without changing the thread default.

### 6.2 Chat history

Required behavior:

- Recent chats in the sidebar, grouped by Today / Yesterday / Previous 7 days / Older.
- Search by title and message content.
- Rename chat.
- Delete chat with confirmation.
- Archive/unarchive.
- Paginated or cursor-based history; do not hard-limit users to the most recent 30 forever.
- New chat creates no database row until the first message is submitted.
- Titles are auto-generated from the first meaningful user request but remain editable.
- Conversation context is built from persisted messages and bounded by provider context constraints, not an arbitrary UI-only transcript.

### 6.3 Composer

The composer has:

- multiline prompt
- attachment button
- `@` mention autocomplete
- agent selector
- model selector
- effort selector
- selected capability chips
- send/stop button
- per-message/thread-default toggle

Keyboard behavior:

- `Enter` sends when appropriate.
- `Shift+Enter` adds a newline.
- `@` opens capability search.
- `Esc` closes menus.
- selection remains keyboard navigable.

### 6.4 Structured `@` mentions

Typing `@` searches a unified registry of:

- agents
- plugins/connections
- skills
- workflows
- leads (read/context)
- campaigns (read/context)

Mentions are stored as structured message context, not inferred later from display text. Each mention stores type, ID, label snapshot, and workspace ownership. The visible token may render `@Apollo`, but execution uses validated IDs.

The existing plus menu remains as an alternate discovery surface and uses the same registry.

### 6.5 Per-thread and per-message model selection

The model selector is ChatGPT-like but provider-aware:

1. Model connection (e.g. "OpenAI Production")
2. Model (from provider catalog, connection default, or explicit custom model)
3. Effort: Fast / Standard / Deep

Precedence for an agent run:

`message override → thread default → agent override → agent connection default → workspace default connection`

The final resolved provider, connection, model, and effort are snapshotted on the immutable run so historical messages remain explainable after settings change.

### 6.6 Effort semantics

`Fast`, `Standard`, and `Deep` are Enverif-level intent settings.

- Provider adapters map effort to native reasoning controls only when supported.
- Unsupported providers receive normal valid API payloads; Enverif adjusts run/tool budgets and prompts instead of sending invalid provider fields.
- Effort never silently changes the safety/capability policy.
- The run inspector displays the chosen effort.

### 6.7 Assistant response experience

Responses support safe Markdown rendering, lists, tables, code blocks, links, copy action, and long-response scrolling.

Visible execution states may include:

- Queued
- Working
- Using `<plugin/tool>`
- Delegated to `<agent>`
- Waiting for approval
- Waiting for workflow/delay
- Retrying provider
- Completed / Final
- Failed / Cancelled

These are operational status events only. Enverif must not expose private model chain-of-thought.

Each final assistant message shows a compact provenance row:

- agent
- provider/model
- effort
- used plugins/skills/workflows
- run link
- duration/cost where available

### 6.8 Edit, retry, stop

- Stop cancels the active durable run and descendants where appropriate.
- Retry creates a new immutable run linked to the previous message/run.
- Editing a prior user message creates a new branch/retry lineage; historical messages are not silently mutated after execution.
- Failed provider/tool states can be retried after configuration is corrected.

## 7. Attachments and agent avatars

### 7.1 Chat attachments

Add `chat_attachments` with workspace/thread/message ownership and metadata:

- UUID/public identifier
- original name
- MIME
- size
- storage path
- sha256
- type/capability classification
- extraction status
- timestamps

Requirements:

- Store outside the public web root.
- Validate MIME by file contents, not extension only.
- Configurable max file size/count.
- Initial allowlist: common images, PDF, TXT, CSV, JSON, Markdown, office documents where safely supported.
- Reject executable/script/archive types unless a dedicated importer explicitly supports them.
- Download/view routes enforce authentication and workspace ownership.
- Shared hosting must not depend on `storage:link`.

Provider adapters translate canonical attachment content into provider-supported multimodal requests. Unsupported model/file combinations produce a clear message before starting the run.

### 7.2 Agent avatar

Agents support optional avatar upload plus deterministic fallback.

- Image MIME validation.
- Reasonable pixel/file caps.
- Server-side safe re-encoding where available; otherwise strict MIME allowlist.
- Avatar rendered in chat messages, agent picker, agent cards, delegation display, and workflow agent nodes.
- Private workspace media route avoids required symlinks on shared hosting.

## 8. Agent runtime

### 8.1 Run creation

An immutable run snapshots:

- agent
- resolved model connection/provider/model
- effort
- thread/message source
- selected plugins/connections
- selected skills
- selected workflows/context entities
- policy ceiling
- limits
- attachment references

### 8.2 Execution guarantees

The durable loop must be integration-tested for:

- direct model final response
- model tool call
- tool approval
- denied tool
- provider failure
- retry
- cancellation
- child-agent delegation
- memory read/write
- tagged extra connector
- database queue
- Redis queue
- bounded cron continuation

A run is never marked completed until the final assistant message is persisted or a terminal failure is persisted.

### 8.3 Model connection diagnostics

Before starting a run, the UI/API validates:

- enabled model connection exists
- API credential can be decrypted
- model ID is resolved
- provider adapter supports the requested mode/attachments

Configuration errors return a user-actionable message with a link to Settings → Models instead of a generic 500.

## 9. Plugin system

### 9.1 Manifest metadata

Maintain backward compatibility with `enverif.plugin/v1` while allowing these optional validated fields:

- `icon`
- `developer`
- `developer_url`
- `homepage`
- `docs_url`
- `category`
- `description`

Built-in first-party manifests use:

- `developer: Codefreex`
- `developer_url: https://codefreex.com/`

The UI hyperlinks the developer name when `developer_url` exists.

### 9.2 Icon contract

- Built-in plugins ship local SVG/PNG icons; no runtime dependency on remote hotlinks.
- External plugin icon paths must stay inside their plugin directory.
- Validate extension, path traversal, file size, and image type.
- Serve plugin assets through a safe plugin-asset route or copied hashed cache, never arbitrary filesystem paths.
- Fallback icon uses a neutral generated mark only if the plugin omitted an icon.

Initial real icons required for:

- Gmail
- Microsoft Outlook
- SMTP/mail
- Apollo
- Apify
- Google Search Console
- Google Analytics
- Google Maps
- Calendly
- n8n
- Zapier
- Make
- Custom Webhook

### 9.3 Plugin developer documentation

Docs must include:

- manifest reference
- connector driver contract
- configuration schema
- encrypted credential fields
- action/risk declaration
- OAuth lifecycle
- icons and developer metadata
- testing requirements
- packaging layout
- compatibility policy
- security rules
- example complete plugin

## 10. Skills

Skills remain `SKILL.md` packages with provenance and checksums.

Required improvements:

- Skill detail page includes source/developer/license/capabilities and install provenance.
- `@skill` exposes the skill only to the immutable run/message that selected it unless attached to the agent permanently.
- Workflow skill nodes become executable semantics, not merely "return the skill instructions".

### 10.1 Workflow skill execution

A skill workflow node must support one of two explicit modes:

1. **Attach context:** inject the skill into the next agent node's effective skill set.
2. **Execute with agent:** run the selected skill using a selected agent/model as a durable child agent run.

The editor must require the mode and any required agent ID. No ambiguous skill node is allowed to activate.

## 11. MCP

MCP remains a remote Streamable HTTP extension source.

Required product behavior:

- clear server connection/test state
- tool discovery timestamp
- per-tool risk mapping and overrides
- connection timeout diagnostics
- workspace ownership
- chat `@` support for MCP server/tool where appropriate
- workflow MCP-tool node support through the same capability/approval system
- developer docs for protocol version, stateless mode, auth, risk declaration, timeouts, and test fixtures

## 12. Visual workflow system

### 12.1 Editor

The editor is a real visual automation builder with:

- pan/zoom
- node palette/search
- drag/drop
- edge creation
- inspector
- duplicate/delete
- undo/redo for client-side editor changes
- unsaved changes warning
- node validation badges
- disabled activation until graph validates
- responsive read-only fallback on narrow mobile if full editing is impractical

### 12.2 Node types

Required:

- Manual trigger
- Schedule trigger
- Webhook trigger
- Agent
- Plugin action
- Skill
- MCP tool
- Condition
- Delay
- Lead upsert/activity
- Campaign membership/action
- Approval
- Output

### 12.3 Runtime

Workflow execution is durable and restart-safe.

Every node run stores:

- node snapshot/version
- resolved input
- status
- output
- error
- timestamps
- linked child run/approval/tool references

The workflow run UI visually highlights current/completed/failed nodes and opens a node inspector with input/output/error.

### 12.4 Test run

Before activation users can **Test workflow** with sample input.

- External writes default to dry-run/approval behavior.
- The execution panel updates while nodes run.
- Errors link directly to the invalid node or connector configuration.

### 12.5 Retry/resume

- Retry failed node only when idempotency/risk rules permit.
- Resume after approval/delay/agent child completion.
- Cancellation persists terminal state and prevents future delayed continuation.
- Duplicate queued jobs are protected by unique jobs plus database/Redis locks where needed.

## 13. Email automation

First-party Codefreex plugins:

- Gmail OAuth
- Microsoft Outlook OAuth / Graph
- SMTP

Required actions:

- account/profile
- search/list messages where provider supports it
- read thread/message
- create draft
- send
- reply

Safety:

- send/reply = `external_write`
- approval required by default
- explicit per-agent/per-workflow autonomous external-write switch required to bypass
- audit stores recipients, provider action, message/thread identifiers, approval, and run provenance without logging secrets

## 14. Leads and campaigns

Chat and workflows can create/update leads through existing workspace-scoped tools.

Enhancements:

- `@lead` and `@campaign` context mentions
- run/activity provenance links
- campaign actions visible in workflow traces
- duplicate lead handling documented and tested
- bulk operations bounded/paginated

## 15. Schedules

Schedules support agents and workflows.

Required UX:

- month calendar
- agenda/list view
- create/edit recurrence
- IANA timezone
- next/last run
- paused/active
- last failure summary
- link to resulting run

Shared-hosting heartbeat status is displayed directly on schedule pages when execution may be stalled.

## 16. Approvals and audit

Approval inbox supports:

- agent tool calls
- workflow actions
- email sends/replies
- destructive actions

Each approval shows human-readable effect, actor/run/workflow, risk, target, and payload summary without exposing decrypted credentials.

Audit remains hash-linked and exportable. New chat/thread/model/effort/attachment/plugin selection events that materially affect execution are included.

## 17. Shared hosting and `.htaccess`

### 17.1 Runtime modes

**Shared mode**

- MySQL authoritative state
- database queue
- database cache/locks
- database sessions after install
- minute cron calling bounded `enverif:tick`
- signed web cron fallback

**Performance mode**

- MySQL state
- Redis queue/cache/locks
- persistent workers
- scheduler

### 17.2 Apache layouts

Preferred: domain document root points to `public/`.

Fallback: application lives inside web root and root `.htaccess` securely bridges requests to `public/`.

The fallback must:

- route bare `/` directly to `public/index.php`
- route non-empty application paths into `public/`
- avoid rewrite recursion
- protect `.env`, `.git`, `.github`, `vendor`, storage, config, routes, database, tests, skills, plugins, source metadata, and backup files
- allow ACME `.well-known`
- work from a subfolder without hard-coded `RewriteBase /`
- retain `DirectoryIndex index.php` in `public/.htaccess`

Release tests include representative Apache rewrite fixtures for root, subdomain-style root, and `/enverif/` subfolder requests.

### 17.3 Installer

Fresh `/install` must not depend on migrated database session/cache/queue tables.

Before installation:

- file session
- file cache
- sync queue
- stable bootstrap encryption key

After migration and owner creation, `.env` switches to chosen production runtime.

Installer performs an actual post-install health check before displaying success.

## 18. System Health

System Health reports:

- installed/schema state
- runtime mode
- PHP/version/extensions
- writable directories
- queue connection
- cache/lock backend
- Redis availability (optional)
- scheduler heartbeat
- queue heartbeat
- pending/failed jobs
- oldest queued job age
- base URL/subfolder
- rewrite self-test
- model connection availability/test status
- email connector status

A broken background runner produces a prominent actionable warning in chat, workflows, and schedules.

## 19. Security

- Credentials encrypted at rest using Laravel encrypted casts/application key.
- No credentials in audit/log/UI after save.
- Attachment and plugin file paths cannot escape approved directories.
- CSRF on browser mutations.
- OAuth state validation.
- Signed web cron with replay protection/time freshness.
- Workspace authorization on every entity and download route.
- External writes remain policy-controlled.
- Markdown output is sanitized against script/unsafe HTML injection.
- Uploads are content-validated and size-bounded.
- Production `APP_DEBUG=false` documented and checked by health diagnostics.

## 20. Data model changes

### 20.1 Chat threads

Add:

- `default_agent_id`
- `default_model_connection_id`
- `default_model`
- `default_effort`
- `archived_at`
- optional `summary`

Migration preserves existing `agent_id` data into `default_agent_id`.

### 20.2 Chat messages

Keep immutable role/content and add structured metadata conventions for:

- selection snapshot
- model resolution
- effort
- mentions
- attachment IDs
- response status/provenance
- retry/edit lineage

### 20.3 Attachments

Create `chat_attachments` as described above.

### 20.4 Agents

Add avatar/media reference and default effort if not already represented in settings.

### 20.5 Runs

Persist resolved model connection/model/effort either as explicit columns or a versioned immutable execution snapshot in `context`. Explicit columns are preferred for diagnostics/reporting.

## 21. Error handling contract

User-facing operations must not expose Laravel stack traces in production.

Expected failures map to product states:

- missing model → configuration CTA
- invalid model → model selector error
- provider auth → connection test CTA
- tool approval → approval state
- queue unavailable → runtime health warning
- workflow invalid → node validation
- attachment unsupported → preflight error
- plugin unavailable → disabled mention/chip with explanation
- route binding invalid UUID → 404, never fatal trait error
- workspace mismatch → 404/403 according to resource semantics, never cross-workspace lookup

## 22. Test strategy and release gates

### 22.1 Static/source gates

- PHP lint all PHP files
- JS syntax/build
- Blade compile/bootstrap check
- workflow YAML parse
- translation parity
- docs/site link checker
- plugin manifest validation
- forbidden secret scan
- package inspection

### 22.2 Laravel unit/feature gates

At minimum add tests for:

- UUID workspace route binding for AgentRun and WorkflowRun
- all protected resource route bindings
- installer fresh database
- installer retry after partial failure
- stale installed marker + reset DB
- chat thread CRUD/search/archive
- thread defaults and per-message overrides
- structured `@` mentions workspace validation
- attachments upload/download authorization
- agent avatar upload
- model resolution precedence
- effort mapping fallback
- direct agent final response
- tool + approval + resume
- delegation
- workflow manual run
- workflow agent node
- workflow plugin node
- workflow skill modes
- workflow condition/delay
- workflow approval
- workflow retry/cancel
- Gmail/Outlook/SMTP approval-first behavior
- database queue runtime
- Redis runtime
- scheduler tick/heartbeat
- web cron signature/replay

### 22.3 Browser/E2E gates

Use Playwright or equivalent against a real built app:

- install wizard from empty DB
- login/logout/password
- new chat/send/final response
- history rename/search/delete/archive
- agent/model/effort switching
- `@` menu keyboard interactions
- attachment upload
- agent avatar
- plugins real icons/developer links
- workflow create/connect/save/test/run
- schedules calendar
- responsive sidebar/mobile
- dark/light/system appearance

### 22.4 Database/infrastructure matrix

CI must run:

- PHP 8.3 / 8.4 / current supported
- MySQL 8.x
- database queue/cache without ext-redis
- Redis queue/cache
- migrations fresh and upgrade from previous supported release fixture

### 22.5 Release blocker policy

No release if:

- any HTTP route returns a fatal error
- installer cannot complete from empty database
- agent cannot return one final response through a configured test provider/fake adapter
- workflow cannot complete a representative multi-node run
- shared-host package lacks real `vendor/autoload.php`
- static site/docs have broken local links
- release artifact checksums fail

## 23. Documentation requirements

### 23.1 User documentation

- Installation
- First chat
- Chat history
- Agents
- Models and effort
- Attachments
- Plugins
- Skills
- MCP
- Workflows
- Email automation
- Leads/campaigns
- Schedules
- Approvals
- System Health
- Troubleshooting

### 23.2 Hosting documentation

- Shared hosting overview
- Hostinger
- cPanel
- Plesk
- Apache
- Nginx
- VPS/systemd/Supervisor
- Docker
- Cron
- Signed Web Cron
- Redis optional/performance migration
- Backups/upgrades

### 23.3 Developer documentation

- Architecture
- Local setup
- Database/runtime lifecycle
- Agent runtime contract
- Model provider adapter
- Plugin development
- Plugin icons/developer metadata
- OAuth connector development
- Skills format/security
- Workflow node development
- MCP integration
- UI conventions
- Translations
- Testing
- Release process

### 23.4 Contributor onboarding

`CONTRIBUTING.md` and docs must identify contribution tracks:

- Core
- Plugins/connectors
- Skills
- Workflow nodes
- Models/providers
- UI/UX
- Translations
- Docs
- Tests

Each track has prerequisites, local commands, expected tests, and PR checklist.

## 24. GitHub repository presentation

Repository README must include:

- clear one-line product description
- screenshot/preview of agentic chat and workflows
- install options
- shared-hosting support caveat/requirements
- feature map
- plugin/skill/MCP links
- contributor CTA
- security/reporting link
- Codefreex attribution
- accurate release download instructions

Recommended repository metadata:

**Description:** `Open-source AI revenue agent OS with persistent chat, sales workflows, plugins, skills, MCP, approvals, email automation and shared-hosting support.`

**Topics:** `ai-agents`, `sales-automation`, `revenue-operations`, `laravel`, `open-source`, `agentic-ai`, `workflow-automation`, `lead-generation`, `email-automation`, `mcp`, `shared-hosting`, `php`

**Homepage:** published Enverif docs/site URL.

## 25. Release architecture

Use one release workflow.

1. PR/main CI verifies source.
2. `VERSION` is the only release version source.
3. Release workflow checks out the exact successful commit.
4. Composer production dependencies are installed.
5. Frontend assets are built.
6. Full tests/verifiers rerun.
7. Build:
   - source ZIP
   - shared-hosting ZIP with real `vendor/` and built assets
   - websites/docs ZIP
   - checksum file
8. Inspect every archive after creation.
9. Create immutable `vX.Y.Z` release only after successful gates.
10. Deploy Pages from verified site build.

No workflow hard-codes an unrelated version. Dependency lockfile changes are reviewed through normal commits/PRs instead of an Action silently rewriting `main` as part of release preparation.

## 26. Acceptance journeys

### Journey A — first shared-host user

1. Upload shared-host ZIP.
2. Visit `/install` with an empty MySQL database.
3. Installer loads without sessions/cache tables.
4. Create owner/workspace.
5. Configure an OpenAI/Claude/Gemini/DeepSeek connection using dropdowns.
6. Finish install.
7. Login redirects to working `/` chat.
8. System Health provides cron command.
9. Cron heartbeat turns healthy.
10. User sends first message and receives final response.

### Journey B — chat-led prospect workflow

1. New chat.
2. Select Research Agent.
3. Select model + Deep effort.
4. Type `@Apollo @Google Maps @Lead Qualifier`.
5. Upload ICP CSV/PDF.
6. Ask for qualified prospects.
7. Enverif shows operational tool statuses.
8. Leads are created with run provenance.
9. Final response summarizes outcome and links created leads/run.
10. Thread persists in history and resumes later with same defaults.

### Journey C — outbound workflow

1. Build manual/scheduled workflow.
2. Research plugin node.
3. Skill execution node.
4. Agent personalization node.
5. Lead/campaign node.
6. Gmail/Outlook send node.
7. Send waits for approval unless autonomous external writes enabled.
8. Execution survives queue restart/delay.
9. Workflow run inspector shows each node result.

### Journey D — extension contributor

1. Clone repository.
2. Follow developer setup.
3. Create plugin with icon, developer URL, configuration schema, action risk.
4. Run extension validation/tests.
5. Preview icon/metadata in catalog.
6. Submit PR using documented checklist.

## 27. Definition of done for Enverif 1.3.1

Enverif 1.3.1 is complete only when all of the following are true:

- Fatal trait collisions are impossible and regression-tested.
- Installer completes from empty DB and recovers partial/stale states.
- Root/subfolder Apache routing is integration-tested.
- Chat history is persistent, searchable, renameable, archivable, and deletable.
- Thread-persistent agent/model/effort defaults and per-message overrides work.
- `@` mentions are structured and workspace-safe.
- Attachments and agent avatars work without public storage symlink dependency.
- Agent runs return final messages under database queue and Redis queue.
- Plugin catalog renders real icons and linked developer attribution.
- Skills have meaningful workflow execution semantics.
- MCP is documented and usable through the common risk model.
- Workflow creation, validation, test run, agent/plugin/skill execution, approvals, delays, retries, and final output work.
- Gmail, Outlook, and SMTP send/reply behavior is approval-first by default.
- Schedules and bounded shared-host cron execute agents/workflows with health visibility.
- Settings centralizes models/integrations/profile/security/runtime.
- All EN/FR/NL UI keys remain in parity.
- Complete user/operator/developer/contributor docs are published.
- GitHub README, metadata, topics, release guide, issue/PR templates, Pages, and release workflow are accurate.
- Full CI and browser E2E gates pass against generated release artifacts.
- The shared-hosting ZIP contains real Composer production dependencies and built frontend assets.
- No known P0/P1 bugs remain open.

