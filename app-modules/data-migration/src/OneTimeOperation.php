<?php

namespace AdvisingApp\DataMigration;

use AdvisingApp\DataMigration\Enums\OperationType;

abstract class OneTimeOperation
{
    /**
     * The type to determine where it will be run. OperationType::Tenant or OperationType::Landlord.
     */
    protected OperationType $type = OperationType::Tenant;

    /**
     * Determine if the operation is being processed asynchronously.
     */
    protected bool $async = true;

    /**
     * The queue that the job will be dispatched to.
     */
    protected string $queue = 'default';

    /**
     * A tag name, that this operation can be filtered by.
     */
    protected ?string $tag = null;

    /**
     * Process the operation.
     */
    abstract public function process(): void;

    public function isAsync(): bool
    {
        return $this->async;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function getType(): OperationType
    {
        return $this->type;
    }
}
