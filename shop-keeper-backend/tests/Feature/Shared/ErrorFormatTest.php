<?php

use App\Shared\Errors\ApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

function assertOnlyErrorEnvelope(array $body): void
{
    expect(array_keys($body))->toBe(['error']);
    expect(array_diff(array_keys($body['error']), ['code', 'fields', 'retry_after_seconds']))->toBe([]);
    expect($body['error']['code'])->toMatch('/^[a-z_]+\.[a-z_]+$/');
}

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1/test')->group(function () {
        Route::post('echo', fn () => response()->json(['data' => 'ok']));
        Route::get('protected', fn () => response()->json(['data' => 'ok']))->middleware('auth:sanctum');
        Route::get('explodes', function () {
            throw new RuntimeException('secret internal detail');
        });
        Route::get('limited', fn () => response()->json(['data' => 'ok']))->middleware('throttle:1,1');
        Route::post('validated', function (Request $request) {
            $request->validate(['email' => 'required|email'], ['email.required' => 'email.required']);
        });
    });
});

it('answers an unknown address with request.not_found', function () {
    $response = $this->get('/api/v1/nowhere');

    $response->assertStatus(404)->assertExactJson(['error' => ['code' => 'request.not_found']]);
    assertOnlyErrorEnvelope($response->json());
});

it('answers a wrong method with request.method_not_allowed', function () {
    $response = $this->getJson('/api/v1/test/echo');

    $response->assertStatus(405)->assertExactJson(['error' => ['code' => 'request.method_not_allowed']]);
});

it('answers a form body with request.not_json', function () {
    $response = $this->post('/api/v1/test/echo', ['name' => 'Amira']);

    $response->assertStatus(415)->assertExactJson(['error' => ['code' => 'request.not_json']]);
});

it('answers a malformed JSON body with request.not_json', function () {
    $response = $this->call('POST', '/api/v1/test/echo', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], '{not json');

    $response->assertStatus(415)->assertExactJson(['error' => ['code' => 'request.not_json']]);
});

it('accepts a JSON body and an empty body', function () {
    $this->postJson('/api/v1/test/echo', ['name' => 'Amira'])->assertOk();
    $this->call('POST', '/api/v1/test/echo')->assertOk();
});

it('answers throttling with request.throttled and the seconds left', function () {
    $this->getJson('/api/v1/test/limited')->assertOk();

    $response = $this->getJson('/api/v1/test/limited');

    $response->assertStatus(429)->assertJsonPath('error.code', 'request.throttled');
    expect($response->json('error.retry_after_seconds'))->toBeInt()->toBeGreaterThan(0);
    assertOnlyErrorEnvelope($response->json());
});

it('answers a server failure with server.error and no internal detail', function () {
    $response = $this->getJson('/api/v1/test/explodes');

    $response->assertStatus(500)->assertExactJson(['error' => ['code' => 'server.error']]);
    expect($response->getContent())->not->toContain('secret internal detail');
});

it('answers a missing session with auth.unauthenticated, even with no JSON accept header', function () {
    $this->getJson('/api/v1/test/protected')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    $this->get('/api/v1/test/protected')
        ->assertStatus(401)
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
});

it('answers a validation failure with validation.failed and a code per field', function () {
    $response = $this->postJson('/api/v1/test/validated', []);

    $response->assertStatus(422)->assertExactJson([
        'error' => ['code' => 'validation.failed', 'fields' => ['email' => ['email.required']]],
    ]);
    assertOnlyErrorEnvelope($response->json());
});

it('puts error details beside the code', function () {
    Route::middleware('api')->get('api/v1/test/detailed', function () {
        throw new ApiException('product.name_close', 409, details: [
            'existing_product_id' => 'some-id',
            'matches' => [['catalog_product' => null]],
        ]);
    });

    $this->getJson('/api/v1/test/detailed')
        ->assertStatus(409)
        ->assertExactJson(['error' => [
            'code' => 'product.name_close',
            'existing_product_id' => 'some-id',
            'matches' => [['catalog_product' => null]],
        ]]);
});
