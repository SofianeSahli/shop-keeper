<?php

use App\Modules\Purchasing\Models\Supplier;
use App\Modules\Purchasing\Tests\Support\PurchasingScenario as Scenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = Scenario::merchantWithShop();
});

it('creates a supplier with a normalized phone and a zero balance', function () {
    $id = Scenario::newId();

    Scenario::send($this, 'POST', 'suppliers', ['id' => $id, 'name' => ' Grossiste Ben Ali ', 'phone' => '+216 98 123 456'])
        ->assertCreated()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.name', 'Grossiste Ben Ali')
        ->assertJsonPath('data.phone', '+21698123456')
        ->assertJsonPath('data.balance', 0);
});

it('answers a repeated creation with the same supplier', function () {
    $id = Scenario::newId();

    Scenario::send($this, 'POST', 'suppliers', ['id' => $id, 'name' => 'Grossiste Ben Ali'])->assertCreated();
    Scenario::send($this, 'POST', 'suppliers', ['id' => $id, 'name' => 'Other name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Grossiste Ben Ali');

    $supplierCount = Supplier::query()->count();
    expect($supplierCount)->toBe(1);
});

it('refuses the same name in another case and points to the existing supplier', function () {
    $existing = Scenario::supplier($this->shop, ['name' => 'Grossiste Ben Ali']);
    $existingId = $existing->uuid();
    $id = Scenario::newId();

    Scenario::send($this, 'POST', 'suppliers', ['id' => $id, 'name' => 'grossiste ben ali'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'supplier.name_taken', 'existing_supplier_id' => $existingId]]);
});

it('allows the same name in another shop', function () {
    ['shop' => $otherShop] = Scenario::merchantWithShop();
    Scenario::supplier($otherShop, ['name' => 'Grossiste Ben Ali']);
    $id = Scenario::newId();

    Scenario::send($this, 'POST', 'suppliers', ['id' => $id, 'name' => 'Grossiste Ben Ali'])->assertCreated();
});

$nameTooLong = str_repeat('a', 121);

it('refuses wrong fields with stable codes', function (array $fields, string $field, string $code) {
    $id = Scenario::newId();

    Scenario::send($this, 'POST', 'suppliers', ['id' => $id, 'name' => 'Ben Ali', ...$fields])
        ->assertStatus(422)
        ->assertJsonPath("error.fields.{$field}", [$code]);
})->with([
    'no id' => [['id' => null], 'id', 'id.required'],
    'bad id' => [['id' => 'abc'], 'id', 'id.invalid'],
    'no name' => [['name' => '   '], 'name', 'name.required'],
    'long name' => [['name' => $nameTooLong], 'name', 'name.too_long'],
    'bad phone' => [['phone' => '12345'], 'phone', 'phone.invalid'],
]);

it('refuses an id held by another shop without revealing it', function () {
    ['shop' => $otherShop] = Scenario::merchantWithShop();
    $foreign = Scenario::supplier($otherShop, ['name' => 'Secret supplier']);
    $foreignId = $foreign->uuid();

    $response = Scenario::send($this, 'POST', 'suppliers', ['id' => $foreignId, 'name' => 'Mine'])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.id', ['id.taken']);

    $body = $response->getContent();
    expect($body)->not->toContain('Secret supplier');
});

it('lists suppliers by name in pages', function () {
    Scenario::supplier($this->shop, ['name' => 'Zitouna']);
    Scenario::supplier($this->shop, ['name' => 'Ben Ali']);
    Scenario::supplier($this->shop, ['name' => 'Moulin']);
    ['shop' => $otherShop] = Scenario::merchantWithShop();
    Scenario::supplier($otherShop, ['name' => 'Autre']);

    $firstPage = Scenario::send($this, 'GET', 'suppliers?per_page=2')->assertOk();
    $firstNames = $firstPage->json('data.*.name');
    expect($firstNames)->toBe(['Ben Ali', 'Moulin']);

    $cursor = $firstPage->json('meta.next_cursor');
    $secondPage = Scenario::send($this, 'GET', "suppliers?per_page=2&cursor={$cursor}")->assertOk();
    $secondNames = $secondPage->json('data.*.name');
    expect($secondNames)->toBe(['Zitouna']);
});

it('reads, renames and clears the phone of a supplier', function () {
    $supplier = Scenario::supplier($this->shop, ['name' => 'Ben Ali', 'phone' => '+21698123456']);
    $supplierId = $supplier->uuid();

    Scenario::send($this, 'GET', "suppliers/{$supplierId}")->assertOk()->assertJsonPath('data.name', 'Ben Ali');
    Scenario::send($this, 'PATCH', "suppliers/{$supplierId}", ['name' => 'Grossiste Ben Ali', 'phone' => null])
        ->assertOk()
        ->assertJsonPath('data.name', 'Grossiste Ben Ali')
        ->assertJsonPath('data.phone', null);
});

it('refuses a rename onto another supplier name and an empty change', function () {
    $supplier = Scenario::supplier($this->shop, ['name' => 'Ben Ali']);
    $supplierId = $supplier->uuid();
    $other = Scenario::supplier($this->shop, ['name' => 'Moulin']);
    $otherId = $other->uuid();

    Scenario::send($this, 'PATCH', "suppliers/{$supplierId}", ['name' => 'MOULIN'])
        ->assertStatus(409)
        ->assertJsonPath('error.existing_supplier_id', $otherId);
    Scenario::send($this, 'PATCH', "suppliers/{$supplierId}", [])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.fields', ['fields.required']);
    Scenario::send($this, 'PATCH', "suppliers/{$supplierId}", ['name' => 'BEN ALI'])->assertOk();
});

it('answers another shop supplier as not found on read and change', function () {
    ['shop' => $otherShop] = Scenario::merchantWithShop();
    $foreign = Scenario::supplier($otherShop, ['name' => 'Secret']);
    $foreignId = $foreign->uuid();

    Scenario::send($this, 'GET', "suppliers/{$foreignId}")
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'supplier.not_found']]);
    Scenario::send($this, 'PATCH', "suppliers/{$foreignId}", ['name' => 'Hacked'])->assertNotFound();

    $foreignName = $foreign->fresh()->name;
    expect($foreignName)->toBe('Secret');
});

it('refuses a request with no session and a merchant with no shop', function () {
    $this->getJson('/api/v1/suppliers')
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);

    $noShopToken = Scenario::tokenOfMerchantWithoutShop();
    $id = Scenario::newId();
    Scenario::send($this, 'POST', 'suppliers', ['id' => $id, 'name' => 'X'], $noShopToken)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
});
