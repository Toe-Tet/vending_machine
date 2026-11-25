<?php

declare(strict_types=1);

namespace App\Core;

/** A one-time message for the next page rendered, e.g. after a redirect. */
final readonly class Flash
{
    private const string KEY = '_flash';

    public function __construct(private Session $session)
    {
    }

    public function put(string $message): void
    {
        $this->session->set(self::KEY, $message);
    }

    /** The pending message, removed so it shows only once. */
    public function pull(): ?string
    {
        $message = $this->session->get(self::KEY);
        $this->session->remove(self::KEY);

        return is_string($message) ? $message : null;
    }
}
