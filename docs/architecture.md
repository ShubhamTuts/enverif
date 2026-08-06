# Architecture

Enverif follows a durable manage → execute → audit loop.

## Manage

The agent configuration chooses instructions, a model connection, skills, connectors, limits and capability policy. The tool registry assembles first-party tools, connected connector actions and enabled MCP tools into provider-neutral JSON Schema definitions.

## Execute

`AgentOrchestrator` persists a run and user message, dispatches a queue continuation, requests one model turn, persists assistant/tool calls, evaluates every tool against capability policy, executes allowed steps or pauses for approval, persists tool results, then queues the next turn. MySQL is authoritative throughout.

## Audit

Model turns, permission decisions, approval requests and tool completion/failure write hash-linked audit events. Run summaries record token use, optional cost estimates and stop reason.

## Extension boundaries

Model providers, connector drivers, skills and MCP servers have separate contracts. A new connector does not modify the agent loop. A new model provider does not modify tools. Skills provide procedural context but cannot elevate runtime permissions.
