# Schedules

Schedules can target either an active **agent** or an active **workflow**. They use standard five-field cron expressions and an explicit IANA timezone.

The Schedules screen includes a month calendar and a detail page showing target, prompt/input, cron, timezone, next run and last run. Pausing a schedule preserves its configuration but stops future dispatch.

Examples:

```text
0 8 * * 1-5     Weekdays at 08:00
0 9 * * 1       Mondays at 09:00
30 14 * * *     Every day at 14:30
```

Enverif validates cron syntax semantically before saving and calculates `next_run_at`. Dispatch uses an atomic update so multiple schedulers do not enqueue the same due occurrence.

On Redis/VPS use the persistent scheduler/worker runtime. On shared hosting use the once-per-minute `php artisan enverif:tick` command shown under Settings → System health. See [Runtime modes](../operations/runtime.md).
