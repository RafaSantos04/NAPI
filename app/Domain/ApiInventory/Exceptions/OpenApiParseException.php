<?php

namespace App\Domain\ApiInventory\Exceptions;

use DomainException;
use Illuminate\Http\JsonResponse;

final class OpenApiParseException extends DomainException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $pointer,
        string $message,
    ) {
        parent::__construct($message);
    }

    /** @return array{message: string, errors: list<array{pointer: string, message: string}>} */
    public function responseBody(): array
    {
        return [
            'message' => 'The OpenAPI document is invalid.',
            'errors' => [['pointer' => $this->pointer, 'message' => $this->getMessage()]],
        ];
    }

    public function render(): JsonResponse
    {
        return response()->json($this->responseBody(), 422);
    }
}
