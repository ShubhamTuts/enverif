# Leads and campaigns

Enverif includes a lightweight revenue operations workspace so research agents have durable business records to update instead of leaving valuable findings inside chat transcripts.

## Leads

A lead can store identity, company, role, website, LinkedIn URL, location, qualification status, score, source, original source URL and an evidence-grounded research summary.

First-party tools available to agents are:

- `leads.search` — read matching workspace leads;
- `leads.upsert` — create or update a lead as an internal write;
- `leads.add_activity` — append research, notes, draft outreach, meetings or other operational history.

Every query is constrained to the active workspace. Agents should keep source URLs and separate verified facts from assumptions.

## Campaigns

Campaigns organize an ordered outreach or revenue sequence. Each step stores its action, channel, delay, optional template and whether the step requires approval. Operators can attach or remove workspace leads from the campaign and track each member's current step and state.

Connector actions remain governed by the agent capability policy even when a campaign describes the intended sequence. A campaign is not permission to send.

Typical flow:

1. research and qualify leads;
2. add selected leads to a campaign;
3. generate personalized material;
4. request approval when an external-write step requires it;
5. execute through the configured connector or MCP tool;
6. record the resulting lead activity and outcome.

## External systems

Enverif deliberately keeps CRM and outbound delivery behind connectors, plugins or MCP rather than coupling the core database to one vendor. A contributor can add a CRM, mailbox, sequencer or enrichment provider using the connector contract while inheriting the same workspace scoping, encrypted credential storage, tool-risk metadata and approval engine.
