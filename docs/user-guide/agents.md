# Agents

An Enverif agent is a workspace-scoped execution policy: mission/instructions, default model and effort, skills, connector permissions, memory/delegation behavior and bounded runtime limits.

## Identity and avatar

Agents can have a private JPG/PNG/WebP avatar. The file is stored on the private disk and streamed through an authorized route. Deleting the agent removes its private avatar file. The avatar appears in chat, agent cards and `@agent` selections.

## Model and effort

An agent can specify a model connection, model override and default **Fast / Standard / Deep** effort. Chat threads may inherit those defaults, and an individual message may override them without modifying the agent.

## Immutable run snapshot

When a new `AgentRun` starts, Enverif snapshots the mutable fields that affect execution:

- agent ID/name/instructions;
- model connection/model/default effort;
- max steps/runtime/cost;
- capability policy;
- active skill IDs;
- attached connector IDs and their allowed actions.

The run uses that snapshot for the remainder of execution. Editing the agent, removing a skill or changing connector permissions does not retroactively change an in-flight run. Explicitly tagged connectors/skills for a chat turn are stored separately in that run's context.

## Limits and permissions

Run limits stop runaway execution by step count, wall-clock runtime or configured cost. Capability decisions use explicit deny > explicit allow > risk defaults, with ancestor delegation policy forming a strict ceiling. Destructive and secret-bearing actions remain approval-sensitive even when otherwise enabled.

## Delegation

Delegated agents receive fresh child runs with their own state and the parent's policy ceiling chain. Parent runs wait on persisted child state and resume after child completion. Cancellation cascades through descendants.
