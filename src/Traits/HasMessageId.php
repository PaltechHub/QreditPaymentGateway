<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Traits;

use Qredit\LaravelQredit\Helpers\MessageIdGenerator;

/**
 * Trait for adding message ID generation to request classes.
 */
trait HasMessageId
{
    /**
     * The request type for message ID generation.
     */
    protected string $messageIdType = 'generic';

    /**
     * Whether to use idempotent message ID generation.
     */
    protected bool $useIdempotentMessageId = false;

    /**
     * Custom message ID if provided.
     */
    protected ?string $customMessageId = null;

    /**
     * Generate a message ID for this request.
     *
     * @return string The generated message ID
     */
    protected function generateMessageId(): string
    {
        // Use custom message ID if provided
        if ($this->customMessageId !== null) {
            return $this->customMessageId;
        }

        // Use idempotent generation if enabled
        if ($this->useIdempotentMessageId) {
            return MessageIdGenerator::generateIdempotent(
                $this->messageIdType,
                $this->getIdempotentData()
            );
        }

        // Generate with context if available
        $context = $this->getMessageIdContext();
        if (!empty($context)) {
            return MessageIdGenerator::generate($this->messageIdType, $context);
        }

        // Standard generation
        return MessageIdGenerator::generate($this->messageIdType);
    }

    /**
     * Set a custom message ID for this request.
     *
     * @param string $messageId The custom message ID
     * @return self
     */
    public function withMessageId(string $messageId): self
    {
        $this->customMessageId = $messageId;
        return $this;
    }

    /**
     * Enable idempotent message ID generation.
     *
     * @return self
     */
    public function withIdempotentMessageId(): self
    {
        $this->useIdempotentMessageId = true;
        return $this;
    }

    /**
     * Get data for idempotent message ID generation.
     * Override this method in child classes to provide specific data.
     *
     * @return array
     */
    protected function getIdempotentData(): array
    {
        // Default implementation uses the request body
        return method_exists($this, 'defaultBody') ? $this->defaultBody() : [];
    }

    /**
     * Get context for message ID generation.
     * Override this method in child classes to provide context.
     *
     * @return array
     */
    protected function getMessageIdContext(): array
    {
        return [];
    }

    /**
     * Get the generated message ID.
     *
     * @return string
     */
    public function getMessageId(): string
    {
        return $this->generateMessageId();
    }
}