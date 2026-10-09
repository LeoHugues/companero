<?php

namespace App\Notification;

/**
 * One notification for the phone. Its key says what it is about and when: the app shows each
 * key once, so the same reminder comes back the next day, not every quarter of an hour.
 */
final readonly class Notice
{
    public function __construct(
        public string $key,
        public string $title,
        public string $body,
        /** The page it opens, on this server. */
        public string $path = '/',
    ) {
    }

    /** @return array{key: string, title: string, body: string, path: string} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'title' => $this->title, 'body' => $this->body, 'path' => $this->path];
    }
}
