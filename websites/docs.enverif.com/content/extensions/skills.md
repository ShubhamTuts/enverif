# Skill development

Skills are reviewable `SKILL.md` packages that add focused operating instructions and capabilities to agents without creating a new PHP plugin.

A skill declares frontmatter metadata, instructions and requested capabilities. Enverif parses and security-scans the package, records provenance/source ref and SHA-256, and can install from trusted HTTPS Git sources. Untrusted HTTP sources and repository-path masquerading are rejected.

## Runtime semantics

- globally seeded built-in skills can be used by any workspace;
- workspace skills are scoped to that workspace;
- an agent can attach active skills as defaults;
- chat `@skill` context can add an active skill for one run;
- new agent runs snapshot their default active skill IDs so later agent edits do not rewrite an in-flight run;
- skill instructions are prompt context, not a permission bypass: tools still pass through the capability policy and approvals.

## Authoring checklist

Keep one skill focused on one operating job. State inputs, desired output, boundaries and when tools should/should not be used. Avoid embedding credentials, API keys or customer-specific secrets. Do not instruct an agent to evade Enverif approvals or capability decisions.

A contributed skill should include its license/provenance and pass the standalone parser/security tests before submission.
