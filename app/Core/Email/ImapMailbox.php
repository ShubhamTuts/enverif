<?php

namespace App\Core\Email;

use App\Models\ConnectorConnection;
use Illuminate\Support\Str;

/**
 * Small bounded IMAP adapter for generic mail read capabilities.
 *
 * ext-imap is optional so existing SMTP-only/shared-hosting installations keep
 * working. IMAP is used only when a connection explicitly configures imap_host.
 */
final class ImapMailbox
{
    private const MAX_RESULTS = 50;
    private const MAX_BODY_BYTES = 20000;

    public function __construct(private readonly ConnectorConnection $connection) {}

    public function configured(): bool
    {
        return trim((string) data_get($this->connection->configuration, 'imap_host', '')) !== '';
    }

    public function runtimeAvailable(): bool
    {
        return function_exists('imap_open') && function_exists('imap_search') && function_exists('imap_fetch_overview');
    }

    public function test(): bool
    {
        $stream = $this->open();
        try {
            return function_exists('imap_check') ? imap_check($stream) !== false : true;
        } finally {
            imap_close($stream);
        }
    }

    /** @param array<string,mixed> $filters @return array<string,mixed> */
    public function search(array $filters): array
    {
        $stream = $this->open();
        try {
            $limit = max(1, min(self::MAX_RESULTS, (int) ($filters['limit'] ?? 20)));
            $criteria = $this->criteria($filters);
            $uids = imap_search($stream, $criteria, SE_UID, 'UTF-8');
            if (!is_array($uids)) $uids = [];
            rsort($uids, SORT_NUMERIC);
            $uids = array_slice($uids, 0, $limit);

            $messages = [];
            foreach ($uids as $uid) {
                $message = $this->message($stream, (int) $uid);
                if ($message) $messages[] = $message;
            }

            return [
                'message_count' => count($messages),
                'messages' => $messages,
                'truncated' => count($uids) >= $limit,
            ];
        } finally {
            imap_close($stream);
        }
    }

    /** @return array<string,mixed> */
    public function read(int $uid): array
    {
        if ($uid <= 0) throw new \InvalidArgumentException('A positive IMAP message UID is required.');
        $stream = $this->open();
        try {
            $message = $this->message($stream, $uid);
            if (!$message) throw new \RuntimeException('The requested IMAP message could not be found.');
            return $message;
        } finally {
            imap_close($stream);
        }
    }

    /** @return array<string,mixed> */
    public function thread(int $uid): array
    {
        $target = $this->read($uid);
        $subject = $this->normalizedSubject((string) ($target['subject'] ?? ''));
        $messageId = trim((string) ($target['message_id'] ?? ''));
        $inReplyTo = trim((string) ($target['in_reply_to'] ?? ''));
        $references = trim((string) ($target['references'] ?? ''));

        $result = $this->search([
            'subject' => $subject,
            'limit' => self::MAX_RESULTS,
        ]);
        $messages = array_values(array_filter((array) ($result['messages'] ?? []), function ($candidate) use ($subject, $messageId, $inReplyTo, $references, $uid): bool {
            if (!is_array($candidate)) return false;
            if ((int) ($candidate['id'] ?? 0) === $uid) return true;
            $candidateSubject = $this->normalizedSubject((string) ($candidate['subject'] ?? ''));
            if ($subject !== '' && $candidateSubject === $subject) return true;
            $haystack = implode(' ', [
                (string) ($candidate['message_id'] ?? ''),
                (string) ($candidate['in_reply_to'] ?? ''),
                (string) ($candidate['references'] ?? ''),
            ]);
            foreach ([$messageId, $inReplyTo] as $needle) if ($needle !== '' && str_contains($haystack, $needle)) return true;
            if ($references !== '') {
                foreach (preg_split('/\s+/', $references) ?: [] as $needle) if ($needle !== '' && str_contains($haystack, $needle)) return true;
            }
            return false;
        }));
        usort($messages, fn (array $a, array $b) => strcmp((string) ($a['received_at'] ?? ''), (string) ($b['received_at'] ?? '')));

        return [
            'thread_id' => $messageId !== '' ? $messageId : 'imap:'.$uid,
            'message_count' => count($messages),
            'messages' => $messages,
        ];
    }

    /** @return resource|\IMAP\Connection */
    private function open()
    {
        if (!$this->configured()) throw new \RuntimeException('IMAP is not configured for this mail connection.');
        if (!$this->runtimeAvailable()) throw new \RuntimeException('The PHP IMAP extension is required to read this mailbox. SMTP sending remains available.');

        $host = trim((string) data_get($this->connection->configuration, 'imap_host', ''));
        if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) throw new \InvalidArgumentException('Invalid IMAP host.');
        $port = max(1, min(65535, (int) data_get($this->connection->configuration, 'imap_port', 993)));
        $encryption = strtolower((string) data_get($this->connection->configuration, 'imap_encryption', 'ssl'));
        if (!in_array($encryption, ['ssl', 'tls', 'none'], true)) throw new \InvalidArgumentException('Invalid IMAP encryption mode.');
        $mailbox = trim((string) data_get($this->connection->configuration, 'imap_mailbox', 'INBOX')) ?: 'INBOX';
        if (str_contains($mailbox, '{') || str_contains($mailbox, '}') || str_contains($mailbox, "\0")) throw new \InvalidArgumentException('Invalid IMAP mailbox name.');
        $verify = (string) data_get($this->connection->configuration, 'imap_verify_peer', 'verify') !== 'noverify';

        $flags = '/imap';
        if ($encryption !== 'none') $flags .= '/'.$encryption;
        $flags .= $verify ? '/validate-cert' : '/novalidate-cert';
        $dsn = sprintf('{%s:%d%s}%s', $host, $port, $flags, $mailbox);

        $username = (string) ($this->connection->credential('imap_username') ?: $this->connection->credential('username') ?: '');
        $password = (string) ($this->connection->credential('imap_password') ?: $this->connection->credential('password') ?: '');
        if ($username === '' || $password === '') throw new \RuntimeException('IMAP username and password are required.');

        $stream = @imap_open($dsn, $username, $password, OP_READONLY, 1);
        if ($stream === false) {
            $error = function_exists('imap_last_error') ? (string) imap_last_error() : '';
            throw new \RuntimeException('Unable to connect to IMAP mailbox'.($error !== '' ? ': '.$error : '.'));
        }
        return $stream;
    }

    /** @param array<string,mixed> $filters */
    private function criteria(array $filters): string
    {
        $criteria = [];
        $query = trim((string) ($filters['query'] ?? ''));
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        $subject = trim((string) ($filters['subject'] ?? ''));
        if ($query !== '') $criteria[] = 'TEXT "'.$this->quote($query).'"';
        if ($from !== '') $criteria[] = 'FROM "'.$this->quote($from).'"';
        if ($to !== '') $criteria[] = 'TO "'.$this->quote($to).'"';
        if ($subject !== '') $criteria[] = 'SUBJECT "'.$this->quote($subject).'"';
        if (!empty($filters['unread'])) $criteria[] = 'UNSEEN';
        foreach (['since' => 'SINCE', 'before' => 'BEFORE'] as $key => $operator) {
            $raw = trim((string) ($filters[$key] ?? ''));
            if ($raw === '') continue;
            $timestamp = strtotime($raw);
            if ($timestamp === false) throw new \InvalidArgumentException("Invalid {$key} date.");
            $criteria[] = $operator.' "'.date('d-M-Y', $timestamp).'"';
        }
        return $criteria === [] ? 'ALL' : implode(' ', $criteria);
    }

    private function quote(string $value): string
    {
        return str_replace(["\\", '"', "\r", "\n"], ['\\\\', '\\"', ' ', ' '], mb_substr($value, 0, 500));
    }

    /** @param resource|\IMAP\Connection $stream @return array<string,mixed>|null */
    private function message($stream, int $uid): ?array
    {
        $overview = imap_fetch_overview($stream, (string) $uid, FT_UID);
        if (!is_array($overview) || !isset($overview[0])) return null;
        $row = $overview[0];
        $headers = (string) @imap_fetchheader($stream, $uid, FT_UID);
        [$plain, $html] = $this->body($stream, $uid);
        $text = $plain !== '' ? $plain : trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $truncated = strlen($text) > self::MAX_BODY_BYTES || strlen($html) > self::MAX_BODY_BYTES;

        return [
            'id' => $uid,
            'thread_id' => null,
            'message_id' => trim((string) ($row->message_id ?? $this->header($headers, 'Message-ID'))),
            'in_reply_to' => trim((string) ($row->in_reply_to ?? $this->header($headers, 'In-Reply-To'))),
            'references' => trim((string) ($row->references ?? $this->header($headers, 'References'))),
            'from' => $this->decode((string) ($row->from ?? '')),
            'to' => $this->decode((string) ($row->to ?? '')),
            'cc' => $this->decode((string) ($row->cc ?? '')),
            'subject' => $this->decode((string) ($row->subject ?? '')),
            'sent_at' => isset($row->date) ? (string) $row->date : null,
            'received_at' => isset($row->udate) ? gmdate(DATE_ATOM, (int) $row->udate) : null,
            'unread' => isset($row->seen) ? !(bool) $row->seen : null,
            'snippet' => Str::limit(trim($text), 1000, '…'),
            'text' => Str::limit($text, self::MAX_BODY_BYTES, '…'),
            'html' => $html !== '' ? Str::limit($html, self::MAX_BODY_BYTES, '…') : null,
            'truncated' => $truncated,
        ];
    }

    /** @param resource|\IMAP\Connection $stream @return array{string,string} */
    private function body($stream, int $uid): array
    {
        $structure = @imap_fetchstructure($stream, $uid, FT_UID);
        if (!$structure) {
            $raw = (string) @imap_body($stream, $uid, FT_UID | FT_PEEK);
            return [Str::limit($raw, self::MAX_BODY_BYTES, '…'), ''];
        }

        $plain = '';
        $html = '';
        $this->collectParts($stream, $uid, $structure, '', $plain, $html);
        return [$plain, $html];
    }

    /** @param resource|\IMAP\Connection $stream */
    private function collectParts($stream, int $uid, object $structure, string $partNumber, string &$plain, string &$html): void
    {
        $parts = (array) ($structure->parts ?? []);
        if ($parts !== []) {
            foreach ($parts as $index => $part) {
                if (!is_object($part)) continue;
                $number = $partNumber === '' ? (string) ($index + 1) : $partNumber.'.'.($index + 1);
                $this->collectParts($stream, $uid, $part, $number, $plain, $html);
                if (strlen($plain) >= self::MAX_BODY_BYTES && strlen($html) >= self::MAX_BODY_BYTES) return;
            }
            return;
        }

        if ((int) ($structure->type ?? -1) !== 0) return;
        $subtype = strtoupper((string) ($structure->subtype ?? 'PLAIN'));
        if (!in_array($subtype, ['PLAIN', 'HTML'], true)) return;
        $raw = $partNumber === ''
            ? (string) @imap_body($stream, $uid, FT_UID | FT_PEEK)
            : (string) @imap_fetchbody($stream, $uid, $partNumber, FT_UID | FT_PEEK);
        $decoded = $this->decodeTransfer($raw, (int) ($structure->encoding ?? 0));
        if ($subtype === 'PLAIN' && $plain === '') $plain = $decoded;
        if ($subtype === 'HTML' && $html === '') $html = $decoded;
    }

    private function decodeTransfer(string $value, int $encoding): string
    {
        return match ($encoding) {
            3 => (base64_decode($value, true) ?: ''),
            4 => quoted_printable_decode($value),
            default => $value,
        };
    }

    private function decode(string $value): string
    {
        if ($value === '') return '';
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') return $decoded;
        }
        return $value;
    }

    private function header(string $headers, string $name): string
    {
        if ($headers === '') return '';
        if (preg_match('/^'.preg_quote($name, '/').':\s*(.+(?:\r?\n[ \t].+)*)/mi', $headers, $match) !== 1) return '';
        return preg_replace('/\r?\n[ \t]+/', ' ', trim($match[1])) ?: '';
    }

    private function normalizedSubject(string $subject): string
    {
        $subject = trim($subject);
        do {
            $before = $subject;
            $subject = preg_replace('/^(?:re|fw|fwd)\s*:\s*/i', '', $subject) ?? $subject;
        } while ($before !== $subject);
        return trim($subject);
    }
}
