<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class QueueSafetyTest extends TestCase
{
    public function test_queue_reservation_outlives_the_longest_agent_job_timeout(): void
    {
        self::assertGreaterThan(900, (int) config('queue.connections.database.retry_after'));
        self::assertGreaterThan(900, (int) config('queue.connections.redis.retry_after'));
    }

    public function test_docker_worker_consumes_agent_and_default_queues(): void
    {
        $compose = (string) file_get_contents(base_path('docker-compose.yml'));

        self::assertStringContainsString('--queue=agents,default', $compose);
    }
}
