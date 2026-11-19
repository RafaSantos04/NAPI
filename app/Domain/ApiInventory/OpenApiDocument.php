<?php

namespace App\Domain\ApiInventory;

/** @phpstan-type Endpoint array{path: string, method: string, operation_id: ?string, summary: ?string} */
final readonly class OpenApiDocument
{
    /** @param list<Endpoint> $endpoints */
    public function __construct(
        public string $format,
        public string $openApiVersion,
        public string $title,
        public string $infoVersion,
        public array $endpoints,
    ) {}
}
