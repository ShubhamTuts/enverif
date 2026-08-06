# Security Policy

## Reporting a vulnerability

Please do not open a public issue for an exploitable vulnerability. Use GitHub's private vulnerability reporting feature for the repository when available, or contact the project maintainers through the security contact published on the project website.

Include affected versions, impact, reproduction steps and a minimal proof of concept. Avoid accessing data that is not yours while testing.

## Security boundaries

Enverif treats the following as security-critical:

- workspace isolation and route binding;
- encrypted model and connector credentials;
- agent capability decisions and approval resumption;
- skill source provenance and package scanning;
- plugin source review and manifest validation;
- MCP endpoint/token handling;
- webhook signatures;
- hash-linked audit history;
- queue job deduplication and bounded agent runs.

## Operational guidance

Use HTTPS in production, rotate default database credentials, protect the application key, isolate MySQL/Redis from the public internet, run queue workers as an unprivileged user, back up the database and `storage`, and review every third-party PHP plugin before installation.

Do not place secrets in agent instructions, skill bodies, lead notes, campaign templates or source control. Enverif encrypts connector/model credentials, but it cannot make copied secrets safe once a model or external tool has received them.

## Responsible revenue automation

Operators are responsible for authorization, privacy, anti-spam compliance and third-party terms. Default approval behavior is intentionally conservative for external writes and destructive actions.
