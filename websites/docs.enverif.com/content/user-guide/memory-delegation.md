# Memory and delegation

Enverif agents can carry useful operational context between runs and delegate focused work to other configured agents without losing auditability or policy control.

## Durable memory

Memory is explicit application state stored in MySQL, not hidden model state. Each memory belongs to one workspace and one agent and records:

- a stable key and text value;
- optional tags and an importance score;
- the source run that created or last updated it;
- when it was last used.

At the start of a run Enverif injects a bounded set of the agent's highest-value recent memories into the system context. The agent can also use `memory.search` when it needs older or tagged information.

### Memory tools

| Tool | Risk | Purpose |
|---|---|---|
| `memory.search` | `read` | Search the current agent's durable memory. |
| `memory.remember` | `internal_write` | Create or update a non-secret fact or decision. |
| `memory.forget` | `destructive` | Delete a memory by exact key. |

Credential-like values are rejected by the memory input guard. Store facts such as ICP decisions, research exclusions, preferred reporting formats or an already-processed domain. Do not store passwords, API keys, OAuth tokens, cookies or private credentials.

Operators can also review, add, update and delete memory from an agent's detail page.

## Agent delegation

`agents.delegate` starts a real child run. It does not simulate a sub-agent in the parent model context.

The lifecycle is:

1. The parent selects an active agent returned by `agents.list`.
2. Enverif persists a child `agent_runs` row with `parent_run_id` and delegation context.
3. The parent tool step and run enter `waiting_child`.
4. Redis executes the child through the same durable orchestrator as any other run.
5. If the child needs approval, its own run pauses normally.
6. When the child finishes, fails or is cancelled, Enverif wakes the parent.
7. The child's terminal result is inserted as the original `agents.delegate` tool result and the parent resumes.

The run detail screen links both directions so operators can inspect the full execution tree.

## Delegation safety

A child can never receive a more permissive capability policy than its ancestors. Enverif evaluates the child's configured policy together with every ancestor policy ceiling and uses the strictest decision for each tool call.

This means a specialist agent configured for autonomous outbound cannot silently send on behalf of a parent that requires approval for external writes. Explicit allow patterns also cannot turn `secrets` or `destructive` operations into silent actions.

Delegation cycles are rejected and nesting is bounded by `ENVERIF_MAX_DELEGATION_DEPTH`, which defaults to `3`.

## Scheduled agents

Scheduled runs use the same agent, memory, capability and delegation rules as manually started runs. A useful recurring design is:

1. research new candidates;
2. search memory for domains already processed;
3. enrich and score leads;
4. remember durable deduplication or qualification decisions;
5. delegate specialist research where useful;
6. prepare or execute connector actions under the configured approval policy;
7. finish with a concise operational report.

This makes recurring agents stateful without granting them hidden or unbounded autonomy.
