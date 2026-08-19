<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Exceptions;

use Exception;

class QreditException extends Exception
{
    /**
     * The API response data.
     */
    protected array $response = [];

    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = '', int $code = 0, array $response = [], ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    /**
     * Get the API response data.
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}