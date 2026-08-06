# Visual workflows

Workflows provide durable n8n/Make-style revenue automation without bypassing Enverif's policy, approval and audit layers.

## Nodes

The workflow runtime supports triggers, agents, skills, connector/plugin actions, conditions, delays, lead operations, campaign operations, approvals and outputs. Definitions are validated before execution for missing/disabled agents, skills, connector actions and invalid graph structure.

## Live run vs Test run

**Live run** executes normal side effects subject to policy and approvals. **Test run** (`dry_run`) walks the same graph and records node input/output while simulating external or mutating actions. Use Test run to validate mappings and branches before activation.

## Branch determinism and runtime readiness

A condition node must have exactly one explicit `true` edge and one explicit `false` edge. Duplicate outgoing edges from the same node/port are rejected. This makes runtime branching deterministic rather than depending on array order.

Before a workflow can execute, agent and skill executor nodes must resolve to an active executor with an enabled model connection. Connector/plugin nodes must resolve to an enabled connection and a declared action. Activation remains blocked when runtime validation finds a missing dependency.

## Durable execution snapshots

At run creation Enverif stores the workflow definition, settings and version in the `WorkflowRun` context. Later edits to the visual workflow do not silently mutate a run that is queued, delayed, awaiting approval or being retried.

## Inspection

Open a workflow run to inspect:

- mode and status;
- current node;
- per-node input;
- per-node output;
- error information;
- timing and terminal result.

This is the primary diagnostic surface for a workflow that did not produce the expected business result.

## Retry and resume

A failed workflow is terminal and should be **retried**, which creates a new run linked with `retry_of`. Resume is for resumable non-terminal states such as a delayed/waiting execution. This prevents a misleading no-op 'resume' on a failed terminal run.

## Scheduling and shared hosting

Interactive manual runs, test runs, retries and resumes in Shared/Compatibility mode schedule a bounded post-response queue kick using the same runtime lock as `enverif:tick`. This improves immediate feedback without weakening durable queue semantics.

Scheduled workflows use the same due-schedule dispatcher as agents. In Shared Hosting Mode the once-per-minute `enverif:tick` command dispatches due work and drains a bounded amount of database queue work. A signed Web Cron can invoke the same tick service when CLI cron is unavailable.
