<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

function postmanFile(string $name): array
{
    $path = base_path('postman/'.$name);
    $contents = file_get_contents($path);

    return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
}

function requestsInCollection(array $items): array
{
    return collect($items)
        ->flatMap(function (array $item): array {
            if (isset($item['item'])) {
                return requestsInCollection($item['item']);
            }

            $path = pathOfCollectionRequest($item['request']);

            return [$item['request']['method'].' '.$path];
        })
        ->sort()
        ->values()
        ->all();
}

function pathOfCollectionRequest(array $request): string
{
    $pathWithQuery = str_replace('{{base_url}}/', '', $request['url']['raw']);
    $path = strtok($pathWithQuery, '?');

    return preg_replace(['/\{\{(\w+)_id\}\}/', '/\{\{barcode\}\}/'], ['{$1}', '{barcode}'], $path);
}

function apiRoutes(): array
{
    $routes = Route::getRoutes()->getRoutes();

    return collect($routes)
        ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(function (LaravelRoute $route): array {
            $methods = $route->methods();

            return collect($methods)
                ->reject(fn (string $method) => $method === 'HEAD')
                ->map(fn (string $method) => $method.' '.$route->uri())
                ->all();
        })
        ->sort()
        ->values()
        ->all();
}

it('has exactly one request in the collection for every API route, and no other', function () {
    $collection = postmanFile('shop-keeper.postman_collection.json');
    $requests = requestsInCollection($collection['item']);
    $routes = apiRoutes();

    expect($requests)->toBe($routes);
});

it('defines every variable of the collection in the environment', function () {
    $collection = postmanFile('shop-keeper.postman_collection.json');
    $environment = postmanFile('shop-keeper.postman_environment.json');
    $collectionText = json_encode($collection);
    preg_match_all('/\{\{(\w+)\}\}/', $collectionText, $matches);
    $usedVariables = array_unique($matches[1]);
    $definedVariables = array_column($environment['values'], 'key');
    $undefinedVariables = array_diff($usedVariables, $definedVariables);

    expect($undefinedVariables)->toBe([]);
});
