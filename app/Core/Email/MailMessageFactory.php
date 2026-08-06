<?php

namespace App\Core\Email;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class MailMessageFactory
{
    /** @param array<string,mixed> $arguments */
    public static function create(array $arguments, ?string $defaultFrom = null, ?string $defaultFromName = null): Email
    {
        $to = self::address((string) ($arguments['to'] ?? ''));
        $subject = trim(str_replace(["\r", "\n"], ' ', (string) ($arguments['subject'] ?? '')));
        $body = (string) ($arguments['body'] ?? '');
        if ($subject === '' || $body === '') throw new \InvalidArgumentException('Email subject and body are required.');

        $email = (new Email())->to($to)->subject($subject);
        $from = (string) ($arguments['from'] ?? $defaultFrom ?? '');
        if ($from !== '') $email->from(new Address(self::address($from), trim((string) ($arguments['from_name'] ?? $defaultFromName ?? ''))));
        if (!empty($arguments['reply_to'])) $email->replyTo(self::address((string) $arguments['reply_to']));
        if (!empty($arguments['html'])) $email->html($body)->text(strip_tags($body)); else $email->text($body);

        if (!empty($arguments['message_id'])) {
            $id = trim(str_replace(["\r", "\n"], '', (string) $arguments['message_id']));
            if ($id !== '') {
                $email->getHeaders()->addTextHeader('In-Reply-To', $id);
                $email->getHeaders()->addTextHeader('References', $id);
            }
        }
        return $email;
    }

    private static function address(string $value): string
    {
        $value = trim($value);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('A valid email address is required.');
        return $value;
    }

    public static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
