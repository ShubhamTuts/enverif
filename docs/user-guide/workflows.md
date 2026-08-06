# Visual workflows

Workflows provide durable n8n/Make-style revenue automation without bypassing Enverif's policy, approval and audit layers.

## Nodes

The workflow runtime supports triggers, agents, skills, connector/plugin actions, conditions, delays, lead operations, campaign operations, approvals and outputs. Definitions are validated before execution for missing/disabled agents, skills, connector actions and invalid graph structure.

## Live run vs Test run

**Live run** executes normal side effects subject to policy and approvals. **Test run** (`dry_run`) walks the same graph and records node input/output while simulating external or mutating actions. Use Test run to validate mappings and branches before activation.

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

Scheduled workflows use the same due-schedule dispatcher as agents. In Shared Hosting Mode the once-per-minute `enverif:tick` command dispatches due work and drains a bounded amount of database queue work. A signed Web Cron can invoke the same tick service when CLI cron is unavailable.
