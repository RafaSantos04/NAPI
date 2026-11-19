<?php

namespace App\Domain\ApiInventory;

use App\Domain\ApiInventory\Exceptions\OpenApiParseException;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class OpenApiParser
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    private const MAX_DEPTH = 64;

    private const MAX_OPERATIONS = 2000;

    private const MAX_REF_EXPANSIONS = 10000;

    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    private int $refExpansions = 0;

    /** @var array<string, mixed> */
    private array $document = [];

    public function parse(string $rawDocument, string $format): OpenApiDocument
    {
        $this->refExpansions = 0;
        if (strlen($rawDocument) > self::MAX_BYTES) {
            throw $this->error('limit_exceeded', '', 'The document exceeds the 2 MB limit.');
        }
        if (preg_match('//u', $rawDocument) !== 1) {
            throw $this->error('malformed', '', 'The document must be valid UTF-8.');
        }

        $decoded = $this->decode($rawDocument, $format);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw $this->error('malformed', '', 'The document root must be an object.');
        }

        $this->document = $decoded;
        $version = $decoded['openapi'] ?? null;
        if (isset($decoded['swagger']) && ! isset($decoded['openapi'])) {
            throw $this->error('unsupported_version', '/swagger', 'Convert Swagger 2.0 to OpenAPI 3 before importing.');
        }
        if (! is_string($version) || preg_match('/^3\.(?:0|1)\.\d+$/D', $version) !== 1) {
            throw $this->error('unsupported_version', '/openapi', 'Only OpenAPI 3.0.x and 3.1.x are supported.');
        }

        $info = $decoded['info'] ?? null;
        if (! is_array($info) || array_is_list($info)) {
            throw $this->error('malformed', '/info', 'The info field must be an object.');
        }
        foreach (['title', 'version'] as $field) {
            if (! is_string($info[$field] ?? null) || trim($info[$field]) === '') {
                throw $this->error('malformed', '/info/'.$field, 'This field must be a non-empty string.');
            }
        }

        $paths = $decoded['paths'] ?? null;
        if (! is_array($paths) || (array_is_list($paths) && $paths !== [])) {
            throw $this->error('malformed', '/paths', 'The paths field must be an object.');
        }

        $this->validateTree($decoded, '', 0, []);

        $endpoints = [];
        foreach ($paths as $path => $pathItem) {
            $pathPointer = '/paths/'.$this->escape((string) $path);
            if (! is_string($path) || ! str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#')) {
                throw $this->error('malformed', $pathPointer, 'A path must start with / and contain no query or fragment.');
            }
            if (! is_array($pathItem) || ($pathItem !== [] && array_is_list($pathItem))) {
                throw $this->error('malformed', $pathPointer, 'A path item must be an object.');
            }
            while (isset($pathItem['$ref'])) {
                $pathItem = $this->resolveReference($pathItem['$ref'], $pathPointer.'/$ref');
                if (! is_array($pathItem) || ($pathItem !== [] && array_is_list($pathItem))) {
                    throw $this->error('malformed', $pathPointer.'/$ref', 'A path reference must point to an object.');
                }
            }
            foreach ($pathItem as $method => $operation) {
                if (! in_array($method, self::METHODS, true)) {
                    if (! in_array($method, ['summary', 'description', 'servers', 'parameters', '$ref'], true) && ! str_starts_with((string) $method, 'x-')) {
                        throw $this->error('malformed', $pathPointer.'/'.$this->escape((string) $method), 'This path item field is not supported.');
                    }

                    continue;
                }
                $pointer = $pathPointer.'/'.$method;
                if (! is_array($operation) || ($operation !== [] && array_is_list($operation))) {
                    throw $this->error('malformed', $pointer, 'An operation must be an object.');
                }
                if (! is_array($operation['responses'] ?? null) || $operation['responses'] === []) {
                    throw $this->error('malformed', $pointer.'/responses', 'An operation must define responses.');
                }
                foreach (['operationId', 'summary'] as $field) {
                    if (isset($operation[$field]) && ! is_string($operation[$field])) {
                        throw $this->error('malformed', $pointer.'/'.$field, 'This field must be a string.');
                    }
                }
                $endpoints[] = [
                    'path' => $path,
                    'method' => strtoupper($method),
                    'operation_id' => $operation['operationId'] ?? null,
                    'summary' => $operation['summary'] ?? null,
                ];
                if (count($endpoints) > self::MAX_OPERATIONS) {
                    throw $this->error('limit_exceeded', $pointer, 'The document exceeds 2,000 operations.');
                }
            }
        }

        return new OpenApiDocument($format, $version, $info['title'], $info['version'], $endpoints);
    }

    private function decode(string $rawDocument, string $format): mixed
    {
        try {
            return match ($format) {
                'json' => json_decode($rawDocument, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR),
                'yaml' => Yaml::parse($rawDocument, 0),
                default => throw $this->error('malformed', '', 'The document format must be JSON or YAML.'),
            };
        } catch (JsonException|ParseException $exception) {
            // Parser diagnostics can include source text; the API receives only our stable message.
            throw $this->error('malformed', '', 'The document could not be parsed.');
        }
    }

    /** @param list<string> $ancestors */
    private function validateTree(mixed $node, string $pointer, int $depth, array $ancestors): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw $this->error('limit_exceeded', $pointer, 'The document exceeds the nesting limit.');
        }
        if (! is_array($node)) {
            return;
        }
        foreach ($node as $key => $value) {
            $childPointer = $pointer.'/'.$this->escape((string) $key);
            if ($key === '$ref') {
                if (! is_string($value) || ! str_starts_with($value, '#/')) {
                    throw $this->error('external_ref', $childPointer, 'Only local references are supported.');
                }
                if (in_array($value, $ancestors, true)) {
                    throw $this->error('ref_cycle', $childPointer, 'A reference cycle is not supported.');
                }
                if (++$this->refExpansions > self::MAX_REF_EXPANSIONS) {
                    throw $this->error('limit_exceeded', $childPointer, 'The document exceeds the reference expansion limit.');
                }
                $target = $this->resolveReference($value, $childPointer);
                $this->validateTree($target, $value, $depth + 1, [...$ancestors, $value]);

                continue;
            }
            $this->validateTree($value, $childPointer, $depth + 1, $ancestors);
        }
    }

    private function resolveReference(string $reference, string $pointer): mixed
    {
        $target = $this->document;
        foreach (explode('/', substr($reference, 2)) as $encodedPart) {
            if (preg_match('/~(?![01])/', $encodedPart) === 1) {
                throw $this->error('malformed', $pointer, 'The local reference is invalid.');
            }
            $part = str_replace(['~1', '~0'], ['/', '~'], $encodedPart);
            if (! is_array($target) || ! array_key_exists($part, $target)) {
                throw $this->error('malformed', $pointer, 'The local reference does not exist.');
            }
            $target = $target[$part];
        }

        return $target;
    }

    private function escape(string $part): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $part);
    }

    private function error(string $reason, string $pointer, string $message): OpenApiParseException
    {
        return new OpenApiParseException($reason, $pointer, $message);
    }
}
