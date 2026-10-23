<?php

namespace App\Services\MockInsurer;

readonly class MockInsurerResponse
{
    public function __construct(
        public string $status, // SUCCESS, PENDING, FAILED
        public int $httpCode,
        public ?string $providerReference = null,
        public ?string $policyNumber = null,
        public ?string $errorMessage = null,
        public bool $isRetriable = false,
        public array $rawResponse = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === 'SUCCESS';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }
}
