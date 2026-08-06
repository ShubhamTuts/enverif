# Runtime modes and queue execution

Enverif is durable-first: MySQL stores authoritative agent/workflow state, while the queue determines when the next resumable step executes.

**Performance Mode** uses Redis queue/cache and persistent workers. **Shared Hosting Mode** uses database queue/cache and `enverif:tick`. **Compatibility Mode** uses the database runtime plus a web-cron fallback for hosts without CLI cron.

`enverif:tick` uses a distributed cache lock, dispatches due schedules, drains `agents,default` queues within `ENVERIF_TICK_BUDGET`, stores a heartbeat, releases its lock and exits. Starting it concurrently is safe; only the lock owner processes a tick.

When a scheduler heartbeat is stale, recurring work and delayed workflow continuation should be considered unavailable until the cron/worker is restored. Manual browser requests must not be used as a substitute for background execution.

## Web Cron in Compatibility Mode

When the hosting control panel cannot execute PHP CLI at all, install Enverif in **Compatibility Mode**. The installer generates a random server-side Web Cron secret. Enverif never displays that secret directly; **Settings → System health** displays a derived 64-character bearer-token URL:

```text
https://example.com/enverif/system/web-cron/<derived-token>
```

Configure a trusted HTTPS cron provider to issue a `GET` request once per minute. Successful calls deliberately return only:

```json
{"ok":true}
```

The endpoint is disabled outside Web Cron mode, rate-limited, and still enters the same cross-process `enverif:runtime:tick` lock as CLI ticks. Treat the URL as a password because access logs or third-party cron dashboards can retain it. Prefer CLI cron whenever the host provides it.

A second HMAC endpoint at `/system/cron?ts=...&nonce=...&sig=...` remains available for programmatic schedulers that can calculate a fresh timestamped signature and need replay protection stronger than a bearer URL.
