<?php

use App\Domain\ApiInventory\Exceptions\OpenApiParseException;
use App\Domain\ApiInventory\OpenApiParser;
use Symfony\Component\Yaml\Yaml;

function openApiJson(array $overrides = []): string
{
    return json_encode([
        'openapi' => '3.1.0',
        'info' => ['title' => 'Orders', 'version' => '1.2'],
        'paths' => ['/orders/{id}' => ['get' => ['operationId' => 'showOrder', 'summary' => 'Show order', 'responses' => ['200' => ['description' => 'OK']]]]],
        ...$overrides,
    ], JSON_THROW_ON_ERROR);
}

it('normalizes JSON operations without changing paths', function () {
    $document = (new OpenApiParser)->parse(openApiJson(), 'json');

    expect($document->format)->toBe('json')
        ->and($document->openApiVersion)->toBe('3.1.0')
        ->and($document->title)->toBe('Orders')
        ->and($document->infoVersion)->toBe('1.2')
        ->and($document->endpoints)->toBe([[
            'path' => '/orders/{id}',
            'method' => 'GET',
            'operation_id' => 'showOrder',
            'summary' => 'Show order',
        ]]);
});

it('parses YAML and resolves escaped local references', function () {
    $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Orders
  version: '1'
paths:
  /orders:
    get:
      responses:
        '200':
          $ref: '#/components/responses/ok~1response'
components:
  responses:
    ok/response:
      description: OK
YAML;

    $document = (new OpenApiParser)->parse($yaml, 'yaml');

    expect($document->endpoints)->toBe([[
        'path' => '/orders',
        'method' => 'GET',
        'operation_id' => null,
        'summary' => null,
    ]]);
});

it('normalizes operations from a local path item reference', function () {
    $body = openApiJson([
        'paths' => ['/orders' => ['$ref' => '#/components/pathItems/orders']],
        'components' => ['pathItems' => ['orders' => [
            'post' => ['responses' => ['201' => ['description' => 'Created']]],
        ]]],
    ]);

    expect((new OpenApiParser)->parse($body, 'json')->endpoints)->toBe([[
        'path' => '/orders',
        'method' => 'POST',
        'operation_id' => null,
        'summary' => null,
    ]]);
});

it('rejects malformed input without exposing its contents', function (string $body, string $format, string $reason, string $pointer) {
    try {
        (new OpenApiParser)->parse($body, $format);
        test()->fail('Expected a parser error.');
    } catch (OpenApiParseException $exception) {
        expect($exception->reason)->toBe($reason)
            ->and($exception->pointer)->toBe($pointer)
            ->and($exception->responseBody()['message'])->toBe('The OpenAPI document is invalid.')
            ->and($exception->responseBody())->not->toContain($body);
    }
})->with([
    'invalid JSON' => ['{"secret":"client secret",', 'json', 'malformed', ''],
    'invalid YAML tag' => ['!php/object:O:8:"stdClass":0:{}', 'yaml', 'malformed', ''],
    'invalid UTF-8' => ["\xFF", 'json', 'malformed', ''],
    'Swagger 2' => [openApiJson(['openapi' => null, 'swagger' => '2.0']), 'json', 'unsupported_version', '/swagger'],
    'unsupported version' => [openApiJson(['openapi' => '3.2.0']), 'json', 'unsupported_version', '/openapi'],
    'external reference' => [openApiJson(['components' => ['schemas' => ['Order' => ['$ref' => 'https://example.com/schema']]]]), 'json', 'external_ref', '/components/schemas/Order/$ref'],
    'missing reference' => [openApiJson(['components' => ['schemas' => ['Order' => ['$ref' => '#/components/schemas/Missing']]]]), 'json', 'malformed', '/components/schemas/Order/$ref'],
    'reference cycle' => [openApiJson(['components' => ['schemas' => ['Order' => ['$ref' => '#/components/schemas/Order']]]]), 'json', 'ref_cycle', '#/components/schemas/Order/$ref'],
    'invalid path' => [openApiJson(['paths' => ['orders' => ['get' => ['responses' => []]]]]), 'json', 'malformed', '/paths/orders'],
    'unknown path item field' => [openApiJson(['paths' => ['/orders' => ['GET' => ['responses' => []]]]]), 'json', 'malformed', '/paths/~1orders/GET'],
    'missing responses' => [openApiJson(['paths' => ['/orders' => ['get' => []]]]), 'json', 'malformed', '/paths/~1orders/get/responses'],
]);

it('limits document bytes before decoding', function () {
    $body = str_repeat(' ', 2 * 1024 * 1024 + 1);

    expect(fn () => (new OpenApiParser)->parse($body, 'json'))
        ->toThrow(OpenApiParseException::class, 'The document exceeds the 2 MB limit.');
});

it('limits the number of operations', function () {
    $paths = [];
    for ($index = 0; $index < 2001; $index++) {
        $paths['/orders/'.$index] = ['get' => ['responses' => ['200' => ['description' => 'OK']]]];
    }

    expect(fn () => (new OpenApiParser)->parse(openApiJson(['paths' => $paths]), 'json'))
        ->toThrow(OpenApiParseException::class, 'The document exceeds 2,000 operations.');
});

it('limits YAML nesting and reference expansion', function () {
    $nested = ['value' => 'end'];
    for ($depth = 0; $depth < 65; $depth++) {
        $nested = ['child' => $nested];
    }
    $parser = new OpenApiParser;

    expect(fn () => $parser->parse(Yaml::dump([
        'openapi' => '3.1.0',
        'info' => ['title' => 'Orders', 'version' => '1'],
        'paths' => [],
        'x-nested' => $nested,
    ], 80), 'yaml'))->toThrow(OpenApiParseException::class, 'The document exceeds the nesting limit.');

    $references = [];
    for ($index = 0; $index < 10001; $index++) {
        $references[] = ['$ref' => '#/components/schemas/Order'];
    }

    expect(fn () => $parser->parse(openApiJson([
        'components' => ['schemas' => ['Order' => ['type' => 'object']]],
        'x-references' => $references,
    ]), 'json'))->toThrow(OpenApiParseException::class, 'The document exceeds the reference expansion limit.');
});
