<?php

declare(strict_types=1);

namespace Qredit\LaravelQredit\Requests;

use Saloon\Http\Request;

abstract class BaseQreditRequest extends Request
{
    /**
     * Default headers for all Qredit requests.
     */
    protected function defaultHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Client-Type' => config('qredit.client.type', 'MP'),
            'Client-Version' => config('qredit.client.version', '1.0.0'),
            'Authorization' => config('qredit.client.authorization', 'HmacSHA512_O'),
            'Accept-Language' => config('qredit.language', 'EN'),
        ];

        // Add Content-Type for requests with body
        if (in_array($this->method->value, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $headers['Content-Type'] = 'application/json';
        }

        // Remove Authorization header if SDK is enabled
        if (config('qredit.sdk_enabled', false)) {
            unset($headers['Authorization']);
        }

        return $headers;
    }
}