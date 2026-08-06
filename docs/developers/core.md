# Core development

The orchestration kernel separates model providers, persisted runs, tools, capability policy and queue continuation. A run must be recoverable from database state after a process dies. Do not add hidden in-memory state that is required for resumption.

Tool actions declare one of `read`, `internal_write`, `network`, `external_write`, `secrets` or `destructive`. Unknown tools are treated conservatively. Explicit deny wins; secrets always ask; destructive actions are denied by default and remain approval-gated when enabled.

Agent delegation creates a child run with a policy ceiling chain. A child cannot gain capability beyond any ancestor. Workflow execution follows the same durable/approval philosophy.

Before merging core changes, exercise both database-queue/no-Redis and Redis modes because a feature that only works with a permanent worker is a shared-hosting regression.
