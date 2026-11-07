<?php

use App\Modules\Catalog\Tests\Support\CatalogScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../app/Modules/*/Tests');

function fakeDeviceId(): string
{
    return (string) Str::uuid();
}

function postProduct(object $test, array $fields, ?string $token = null): TestResponse
{
    app('auth')->forgetGuards();

    return $test->withToken($token ?? $test->token)
        ->postJson('/api/v1/products', CatalogScenario::creation($fields));
}
