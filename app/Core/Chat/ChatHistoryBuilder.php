<?php

namespace App\Core\Chat;

final class ChatHistoryBuilder
{
    /** @param list<array{role:mixed,content:mixed}> $transcript @return list<array{role:string,content:string}> */
    public static function fromTranscript(array $transcript, int $limit = 12): array
    {
        $limit = max(0, min(40, $limit));
        if ($limit === 0) return [];

        $history = [];
        foreach ($transcript as $message) {
            $role = (string) ($message['role'] ?? '');
            if (!in_array($role, ['user', 'assistant'], true)) continue;
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') continue;
            $history[] = ['role' => $role, 'content' => function_exists('mb_substr') ? mb_substr($content, 0, 20000) : substr($content, 0, 20000)];
        }

        $history = array_slice($history, -$limit);
        while ($history && $history[0]['role'] === 'user' && count($history) > 1) {
            // Prefer context that begins with an assistant answer when truncating a prior exchange.
            array_shift($history);
        }
        return array_values($history);
    }
}
