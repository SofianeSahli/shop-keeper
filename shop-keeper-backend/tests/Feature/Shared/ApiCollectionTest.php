<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

function collectionFilePath(): string
{
    return base_path('postman/shop-keeper.postman_collection.json');
}

function requestsInCollection(array $items): array
{
    return collect($items)
        ->flatMap(fn (array $item) => isset($item['item'])
            ? requestsInCollection($item['item'])
            : [$item['request']['method'].' '.pathOfCollectionRequest($item['request'])])
        ->unique()
        ->sort()
        ->values()
        ->all();
}

function pathOfCollectionRequest(array $request): string
{
    $path = strtok(str_replace('{{base_url}}/', '', $request['url']['raw']), '?');

    return preg_replace(['/\{\{(\w+)_id\}\}/', '/\{\{barcode\}\}/'], ['{$1}', '{barcode}'], $path);
}

function apiRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (LaravelRoute $route) => collect($route->methods())
            ->reject(fn (string $method) => $method === 'HEAD')
            ->map(fn (string $method) => $method.' '.$route->uri()))
        ->sort()
        ->values()
        ->all();
}

it('has a request in the collection for every API route, and no other', function () {
    $collection = json_decode(file_get_contents(collectionFilePath()), true, flags: JSON_THROW_ON_ERROR);

    expect(requestsInCollection($collection['item']))->toBe(apiRoutes());
});
