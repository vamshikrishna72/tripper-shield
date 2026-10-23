<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case RECEIVED = 'RECEIVED';
    case VALIDATED = 'VALIDATED';
    case SUBMITTED = 'SUBMITTED';
    case PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    case ISSUED = 'ISSUED';
    case FAILED = 'FAILED';
    case NEEDS_RECONCILIATION = 'NEEDS_RECONCILIATION';

    /**
     * Check if status is terminal.
     */
    public function isTerminal(): bool
    {
        return $this === self::ISSUED;
    }

    /**
     * Get allowed next statuses.
     *
     * @return array<PolicyStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::RECEIVED => [
                self::VALIDATED,
                self::FAILED,
            ],
            self::VALIDATED => [
                self::SUBMITTED,
                self::FAILED,
            ],
            self::SUBMITTED => [
                self::ISSUED,
                self::PENDING_CONFIRMATION,
                self::NEEDS_RECONCILIATION,
                self::FAILED,
            ],
            self::PENDING_CONFIRMATION => [
                self::ISSUED,
                self::FAILED,
                self::NEEDS_RECONCILIATION,
            ],
            self::NEEDS_RECONCILIATION => [
                self::ISSUED,
                self::FAILED,
                self::SUBMITTED,
            ],
            self::FAILED => [
                self::VALIDATED,
                self::SUBMITTED,
            ],
            self::ISSUED => [], // Terminal: never regress
        };
    }

    /**
     * Determine if a transition to the target status is valid.
     */
    public function canTransitionTo(PolicyStatus $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}
