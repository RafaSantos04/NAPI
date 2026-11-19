<?php

namespace App\Domain\ApiInventory;

use App\Domain\ApiInventory\Exceptions\OpenApiParseException;
use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class OpenApiParser
{
    // Also the import route's body limit (ADR-0014, decision 13).
    public const MAX_BYTES = 2 * 1024 * 1024;

    // symfony/yaml takes seconds per MB; 512 KB keeps the worst case measured
    // well inside the 5 s budget (ADR-0014). JSON decodes 2 MB in a fraction.
    private const MAX_YAML_BYTES = 512 * 1024;

    private const MAX_DEPTH = 64;

    private const MAX_OPERATIONS = 2000;

    private const MAX_REF_EXPANSIONS = 10000;

    // YAML aliases copy subtrees by value: a few hundred bytes can decode
    // into millions of nodes (ADR-0014, "billion laughs").
    private const MAX_NODES = 500000;

    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    private int $refExpansions = 0;

    private int $nodes = 0;

    /** @var array<string, bool> false while the target is being walked, true once walked */
    private array $walkedReferences = [];

    /** @var array<string, mixed> */
    private array $document = [];

    public function parse(string $rawDocument, string $format): OpenApiDocument
    {
        $this->refExpansions = 0;
        $this->nodes = 0;
        $this->walkedReferences = [];
        if (strlen($rawDocument) > self::MAX_BYTES) {
            throw $this->error('limit_exceeded', '', 'The document exceeds the 2 MB limit.');
        }
        if ($format === 'yaml' && strlen($rawDocument) > self::MAX_YAML_BYTES) {
            throw $this->error('limit_exceeded', '', 'A YAML document exceeds the 512 KB limit; send it as JSON, up to 2 MB.');
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
        // The patch number is bounded so the value fits the version's
        // openapi_version column (FIND-039).
        if (! is_string($version) || preg_match('/^3\.(?:0|1)\.\d{1,9}$/D', $version) !== 1) {
            throw $this->error('unsupported_version', '/openapi', 'Only OpenAPI 3.0.x and 3.1.x are supported.');
        }

        $info = $decoded['info'] ?? null;
        if (! is_array($info) || array_is_list($info)) {
            throw $this->error('malformed', '/info', 'The info field must be an object.');
        }
        // The lengths of the version's columns: a longer value is the
        // client's error (422), not a database one (FIND-039).
        foreach (['title' => 255, 'version' => 100] as $field => $maxLength) {
            if (! is_string($info[$field] ?? null) || trim($info[$field]) === '') {
                throw $this->error('malformed', '/info/'.$field, 'This field must be a non-empty string.');
            }
            if (mb_strlen($info[$field]) > $maxLength) {
                throw $this->error('limit_exceeded', '/info/'.$field, "This field exceeds {$maxLength} characters.");
            }
        }

        $paths = $decoded['paths'] ?? null;
        if (! is_array($paths) || (array_is_list($paths) && $paths !== [])) {
            throw $this->error('malformed', '/paths', 'The paths field must be an object.');
        }

        $this->validateTree($decoded, '', 0);

        $endpoints = [];
        $templates = [];
        foreach ($paths as $path => $pathItem) {
            $pathPointer = '/paths/'.$this->escape((string) $path);
            if (! is_string($path) || ! str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#')) {
                throw $this->error('malformed', $pathPointer, 'A path must start with / and contain no query or fragment.');
            }
            $template = self::pathTemplate($path);
            if (isset($templates[$template])) {
                throw $this->error('malformed', $pathPointer, 'Paths that differ only in parameter names are identical.');
            }
            $templates[$template] = true;
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

    /**
     * OpenAPI treats /pets/{id} and /pets/{petId} as the same path; the
     * api_endpoints_identity_unique index applies the same rule.
     */
    public static function pathTemplate(string $path): string
    {
        return (string) preg_replace('/\{[^}]*\}/', '{}', $path);
    }

    private function decode(string $rawDocument, string $format): mixed
    {
        try {
            return match ($format) {
                'json' => json_decode($rawDocument, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR),
                // Without objects, constants or custom tags, and refusing them
                // instead of reading them as null (FIND-040).
                'yaml' => Yaml::parse($rawDocument, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE),
                default => throw $this->error('malformed', '', 'The document format must be JSON or YAML.'),
            };
        } catch (JsonException|ParseException $exception) {
            // Parser diagnostics can include source text; the API receives only our stable message.
            throw $this->error('malformed', '', 'The document could not be parsed.');
        }
    }

    private function validateTree(mixed $node, string $pointer, int $depth): void
    {
        $this->countNode($pointer);
        if ($depth > self::MAX_DEPTH) {
            throw $this->error('limit_exceeded', $pointer, 'The document exceeds the nesting limit.');
        }
        if (! is_array($node)) {
            return;
        }
        foreach ($node as $key => $value) {
            if ($key === '$ref') {
                $childPointer = $pointer.'/$ref';
                if (! is_string($value) || ! str_starts_with($value, '#/')) {
                    throw $this->error('external_ref', $childPointer, 'Only local references are supported.');
                }
                if (++$this->refExpansions > self::MAX_REF_EXPANSIONS) {
                    throw $this->error('limit_exceeded', $childPointer, 'The document exceeds the reference expansion limit.');
                }
                $walked = $this->walkedReferences[$value] ?? null;
                if ($walked === false) {
                    throw $this->error('ref_cycle', $childPointer, 'A reference cycle is not supported.');
                }
                // Each target is walked once: walking it again per reference
                // turns a large schema referenced many times into minutes.
                if ($walked === null) {
                    $this->walkedReferences[$value] = false;
                    // The target is reported by its JSON pointer, like every other node.
                    $this->validateTree($this->resolveReference($value, $childPointer), substr($value, 1), $depth + 1);
                    $this->walkedReferences[$value] = true;
                }

                continue;
            }
            if (! is_array($value)) {
                // Scalars need no pointer unless they break the limit.
                $this->countNode($pointer);

                continue;
            }
            $this->validateTree($value, $pointer.'/'.$this->escape((string) $key), $depth + 1);
        }
    }

    private function countNode(string $pointer): void
    {
        if (++$this->nodes > self::MAX_NODES) {
            throw $this->error('limit_exceeded', $pointer, 'The document exceeds the node limit.');
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
