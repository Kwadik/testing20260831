<?php

namespace App\Services\Delivery;

final readonly class ProviderIssueResult
{
    public function __construct(
        public string $status,
        public ?string $code = null,
        public ?string $reason = null,
    ) {
    }

    public static function success(string $code): self
    {
        return new self(
            status: 'ok',
            code: $code,
        );
    }

    public static function error(string $reason): self
    {
        return new self(
            status: 'error',
            reason: $reason,
        );
    }
}
