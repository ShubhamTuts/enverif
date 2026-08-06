# Agentic chat workspace

Chat is Enverif's primary operating surface. A chat thread stores conversation context and user-facing defaults, while every submitted user turn creates a separate durable `AgentRun` with its own execution snapshot, tool steps, approvals, cost accounting and terminal result.

## Persistent thread defaults

Each conversation can remember four defaults:

- primary agent;
- model connection/provider account;
- model ID;
- execution effort: **Fast**, **Standard** or **Deep**.

The composer exposes **Keep for this chat** and **Just this message** behavior. Keeping a selection updates the thread defaults. A one-message override is stored with the run but does not silently rewrite the thread.

Fast/Standard/Deep is provider-neutral. Enverif maps effort to supported provider reasoning controls where they exist and uses bounded execution guidance where they do not. It never sends a provider parameter that the selected adapter does not support.

## Searchable history

The sidebar groups active chats into **Today**, **Yesterday**, **Previous 7 days** and **Older**. Search matches conversation titles/content. Threads can be renamed, archived or deleted. Archived threads remain recoverable from the history surface until explicitly deleted.

Deleting a thread removes its Enverif-private attachment files as well as the thread records. Durable audit/run records are governed by their own retention semantics and are not rewritten merely because a title changes.

## Structured @ context

Use the **+** context picker or type `@` in the composer to select structured context:

- `@agent`
- `@plugin`
- `@skill`
- `@workflow`
- `@lead`
- `@campaign`

These selections are IDs stored in run context, not decorative prompt text. Workspace authorization is applied before the IDs are accepted. A tagged plugin grants only its declared actions for that run; a tagged skill adds that skill to prompt context; a workflow/lead/campaign tag supplies scoped business context.

## Attachments

Chat files are stored on the private Laravel disk, not directly under `public/`. Access requires the same authenticated user and workspace. The composer accepts common images and business documents subject to configured MIME/size limits.

- text-like files are supplied as bounded text context;
- supported images are supplied as provider image content;
- files that the current provider cannot ingest remain visible as secure attachment metadata rather than being exposed publicly.

Do not use chat attachment storage as a general document vault. Large knowledge bases should be implemented through a dedicated skill/plugin or indexed source.

## Run status and final responses

While a run is active the chat status can show states such as:

- **Thinking**
- **Using Apollo** / another tool
- **Waiting for approval**
- **Final**

These are operational states, not hidden chain-of-thought. Enverif does not expose private model reasoning. The final assistant record stores execution metadata such as agent, provider/model, effort, run ID and selected context so the answer can be traced back to the durable run.

The **Stop** action cancels the run tree, including delegated child runs, using the same persisted cancellation path as the run console.

## Approvals

External writes such as email send/reply pause in **Approvals** by default. Approval resumes the exact persisted tool step; denial returns a denied tool result to the agent. Autonomous external writes require an explicit agent/workflow policy choice and still remain audited.
