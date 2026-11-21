<?php

use App\Modules\Contacts\Contracts\ContactBalances;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Tests\Support\ContactsScenario;

function fakeContactBalances(array $balanceById = []): object
{
    return new class($balanceById) implements ContactBalances
    {
        public int $calls = 0;

        public function __construct(private array $balanceById) {}

        public function balances(int $shopId, array $contactIds): array
        {
            $this->calls++;
            $zeros = array_fill_keys($contactIds, 0);

            return [...$zeros, ...$this->balanceById];
        }
    };
}

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
});

it('creates a contact with every field', function () {
    $body = ContactsScenario::creation([
        'phone' => '+216 98 123 456',
        'address' => '  Rue de Marseille, Tunis  ',
        'note' => 'Delivers on Mondays',
        'is_supplier' => true,
        'is_customer' => false,
    ]);

    $response = ContactsScenario::send($this, 'POST', 'contacts', $body);

    $response->assertCreated()
        ->assertJsonPath('data.id', $body['id'])
        ->assertJsonPath('data.name', 'Grossiste Ben Ali')
        ->assertJsonPath('data.phone', '+21698123456')
        ->assertJsonPath('data.address', 'Rue de Marseille, Tunis')
        ->assertJsonPath('data.note', 'Delivers on Mondays')
        ->assertJsonPath('data.is_supplier', true)
        ->assertJsonPath('data.is_customer', false)
        ->assertJsonPath('data.balance', 0);
    $data = $response->json('data');
    $keys = array_keys($data);
    expect($keys)->toBe(['id', 'name', 'phone', 'address', 'note', 'is_supplier', 'is_customer', 'balance', 'created_at', 'updated_at']);

    $stored = Contact::query()->whereUuid($body['id'])->sole();
    expect($stored->shop_id)->toBe($this->shop->id);
});

it('creates a contact with a name only', function () {
    $body = ContactsScenario::creation();

    ContactsScenario::send($this, 'POST', 'contacts', $body)
        ->assertCreated()
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.note', null)
        ->assertJsonPath('data.is_supplier', false)
        ->assertJsonPath('data.is_customer', false);
});

it('answers the existing contact when the same id is sent again', function () {
    $body = ContactsScenario::creation();
    ContactsScenario::send($this, 'POST', 'contacts', $body)->assertCreated();
    $repeat = [...$body, 'name' => 'Another name'];

    ContactsScenario::send($this, 'POST', 'contacts', $repeat)
        ->assertOk()
        ->assertJsonPath('data.name', 'Grossiste Ben Ali');

    $contactCount = Contact::query()->count();
    expect($contactCount)->toBe(1);
});

it('refuses a name another contact of the shop has once compared', function () {
    $existing = ContactsScenario::contact($this->shop, ['name' => 'Grossiste Ben Ali']);
    $existingId = $existing->uuid();
    $body = ContactsScenario::creation(['name' => '  grossiste  BEN ali ']);

    ContactsScenario::send($this, 'POST', 'contacts', $body)
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'contact.name_taken', 'existing_contact_id' => $existingId]]);
});

it('allows the same name in another shop', function () {
    ['shop' => $otherShop] = ContactsScenario::merchantWithShop();
    ContactsScenario::contact($otherShop, ['name' => 'Grossiste Ben Ali']);
    $body = ContactsScenario::creation();

    ContactsScenario::send($this, 'POST', 'contacts', $body)->assertCreated();
});

it('refuses an id used by another shop without saying more', function () {
    ['shop' => $otherShop] = ContactsScenario::merchantWithShop();
    $otherContact = ContactsScenario::contact($otherShop);
    $otherId = $otherContact->uuid();
    $body = ContactsScenario::creation(['id' => $otherId]);

    $response = ContactsScenario::send($this, 'POST', 'contacts', $body);

    ContactsScenario::assertFieldError($response, 'id', 'id.taken');
    $response->assertJsonMissingPath('data');
});

it('answers a stable code for each wrong field', function (array $fields, string $field, string $code) {
    $body = [...ContactsScenario::creation(), ...$fields];
    $body = array_filter($body, fn (mixed $value): bool => $value !== 'absent');

    $response = ContactsScenario::send($this, 'POST', 'contacts', $body);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with(fn (): array => [
    'no id' => [['id' => 'absent'], 'id', 'id.required'],
    'id not a uuid' => [['id' => 'not-a-uuid'], 'id', 'id.invalid'],
    'no name' => [['name' => 'absent'], 'name', 'name.required'],
    'blank name' => [['name' => '   '], 'name', 'name.required'],
    'name not text' => [['name' => 123], 'name', 'name.invalid'],
    'name too long' => [['name' => str_repeat('a', 121)], 'name', 'name.too_long'],
    'phone not a number' => [['phone' => 'call me'], 'phone', 'phone.invalid'],
    'address not text' => [['address' => ['street']], 'address', 'address.invalid'],
    'address too long' => [['address' => str_repeat('a', 256)], 'address', 'address.too_long'],
    'note not text' => [['note' => 12], 'note', 'note.invalid'],
    'note too long' => [['note' => str_repeat('a', 256)], 'note', 'note.too_long'],
    'supplier role not a boolean' => [['is_supplier' => 'yes'], 'is_supplier', 'is_supplier.invalid'],
    'customer role not a boolean' => [['is_customer' => 'no'], 'is_customer', 'is_customer.invalid'],
]);

it('reads one contact with its balance', function () {
    $contact = ContactsScenario::contact($this->shop, ['name' => 'Fournisseur Sfax', 'is_supplier' => true]);
    $contactId = $contact->uuid();
    $balances = fakeContactBalances([$contactId => 24000]);
    app()->instance(ContactBalances::class, $balances);

    ContactsScenario::send($this, 'GET', 'contacts/'.$contactId)
        ->assertOk()
        ->assertJsonPath('data.id', $contactId)
        ->assertJsonPath('data.name', 'Fournisseur Sfax')
        ->assertJsonPath('data.is_supplier', true)
        ->assertJsonPath('data.balance', 24000);
});

it('answers not found for an unknown contact or one of another shop', function (string $whose) {
    ['shop' => $otherShop] = ContactsScenario::merchantWithShop();
    $otherContact = ContactsScenario::contact($otherShop);
    $contactId = match ($whose) {
        'other shop' => $otherContact->uuid(),
        'unknown' => ContactsScenario::newId(),
        'not a uuid' => 'not-a-uuid',
    };

    ContactsScenario::send($this, 'GET', 'contacts/'.$contactId)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'contact.not_found']]);
    ContactsScenario::send($this, 'PATCH', 'contacts/'.$contactId, ['name' => 'Taken over'])
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'contact.not_found']]);
})->with(['other shop', 'unknown', 'not a uuid']);

it('changes every field of a contact', function () {
    $contact = ContactsScenario::contact($this->shop, ['name' => 'Old name']);
    $contactId = $contact->uuid();
    $changes = [
        'name' => 'New name',
        'phone' => '0021698123456',
        'address' => 'Sousse',
        'note' => 'Pays by cheque',
        'is_supplier' => true,
        'is_customer' => true,
    ];

    ContactsScenario::send($this, 'PATCH', 'contacts/'.$contactId, $changes)
        ->assertOk()
        ->assertJsonPath('data.name', 'New name')
        ->assertJsonPath('data.phone', '+21698123456')
        ->assertJsonPath('data.address', 'Sousse')
        ->assertJsonPath('data.note', 'Pays by cheque')
        ->assertJsonPath('data.is_supplier', true)
        ->assertJsonPath('data.is_customer', true);

    $stored = $contact->fresh();
    expect([$stored->name, $stored->compared_name])->toBe(['New name', 'new name']);
});

it('clears an optional text with null and leaves the other fields alone', function () {
    $contact = ContactsScenario::contact($this->shop, [
        'name' => 'Keeps its name',
        'phone' => '+21698123456',
        'address' => 'Sousse',
        'note' => 'A note',
        'is_supplier' => true,
    ]);
    $contactId = $contact->uuid();

    ContactsScenario::send($this, 'PATCH', 'contacts/'.$contactId, ['phone' => null, 'address' => null, 'note' => null])
        ->assertOk()
        ->assertJsonPath('data.name', 'Keeps its name')
        ->assertJsonPath('data.phone', null)
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.note', null)
        ->assertJsonPath('data.is_supplier', true);
});

it('lets a contact change the case of its own name', function () {
    $contact = ContactsScenario::contact($this->shop, ['name' => 'ben ali']);
    $contactId = $contact->uuid();

    ContactsScenario::send($this, 'PATCH', 'contacts/'.$contactId, ['name' => 'Ben Ali'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ben Ali');
});

it('refuses a new name another contact has', function () {
    $existing = ContactsScenario::contact($this->shop, ['name' => 'Grossiste Ben Ali']);
    $existingId = $existing->uuid();
    $contact = ContactsScenario::contact($this->shop, ['name' => 'Other']);
    $contactId = $contact->uuid();

    ContactsScenario::send($this, 'PATCH', 'contacts/'.$contactId, ['name' => 'GROSSISTE ben ali'])
        ->assertStatus(409)
        ->assertExactJson(['error' => ['code' => 'contact.name_taken', 'existing_contact_id' => $existingId]]);
});

it('answers a stable code for each wrong change', function (array $changes, string $field, string $code) {
    $contact = ContactsScenario::contact($this->shop);
    $contactId = $contact->uuid();

    $response = ContactsScenario::send($this, 'PATCH', 'contacts/'.$contactId, $changes);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with(fn (): array => [
    'nothing to change' => [[], 'fields', 'fields.required'],
    'id' => [['id' => '0195f0a0-0000-7000-8000-000000000000'], 'id', 'id.prohibited'],
    'null name' => [['name' => null], 'name', 'name.required'],
    'name too long' => [['name' => str_repeat('a', 121)], 'name', 'name.too_long'],
    'phone' => [['phone' => '12'], 'phone', 'phone.invalid'],
    'address too long' => [['address' => str_repeat('a', 256)], 'address', 'address.too_long'],
    'note too long' => [['note' => str_repeat('a', 256)], 'note', 'note.too_long'],
    'supplier role' => [['is_supplier' => 'maybe'], 'is_supplier', 'is_supplier.invalid'],
    'customer role' => [['is_customer' => null], 'is_customer', 'is_customer.invalid'],
]);

it('lists contacts by name, with their balances asked once per page', function () {
    ContactsScenario::contact($this->shop, ['name' => 'Zitouna']);
    $first = ContactsScenario::contact($this->shop, ['name' => 'amen']);
    ContactsScenario::contact($this->shop, ['name' => 'Baraka']);
    ['shop' => $otherShop] = ContactsScenario::merchantWithShop();
    ContactsScenario::contact($otherShop, ['name' => 'Aaa other shop']);
    $firstId = $first->uuid();
    $balances = fakeContactBalances([$firstId => -500]);
    app()->instance(ContactBalances::class, $balances);

    $response = ContactsScenario::send($this, 'GET', 'contacts');

    $response->assertOk()->assertJsonPath('data.0.balance', -500);
    $namesRows = $response->json('data');
    $names = array_column($namesRows, 'name');
    expect($names)->toBe(['amen', 'Baraka', 'Zitouna']);
    expect($balances->calls)->toBe(1);
});

it('lists only suppliers or only customers', function (string $role, array $expectedNames) {
    ContactsScenario::contact($this->shop, ['name' => 'Supplier only', 'is_supplier' => true]);
    ContactsScenario::contact($this->shop, ['name' => 'Customer only', 'is_customer' => true]);
    ContactsScenario::contact($this->shop, ['name' => 'Both', 'is_supplier' => true, 'is_customer' => true]);
    ContactsScenario::contact($this->shop, ['name' => 'Neither']);

    $response = ContactsScenario::send($this, 'GET', 'contacts?role='.$role);

    $response->assertOk();
    $namesRows = $response->json('data');
    $names = array_column($namesRows, 'name');
    expect($names)->toBe($expectedNames);
})->with([
    'suppliers' => ['supplier', ['Both', 'Supplier only']],
    'customers' => ['customer', ['Both', 'Customer only']],
]);

it('pages contacts with a cursor', function () {
    ContactsScenario::contact($this->shop, ['name' => 'Alpha']);
    ContactsScenario::contact($this->shop, ['name' => 'Beta']);
    ContactsScenario::contact($this->shop, ['name' => 'Gamma']);

    $firstPage = ContactsScenario::send($this, 'GET', 'contacts?per_page=2');
    $cursor = $firstPage->json('meta.next_cursor');
    $secondPage = ContactsScenario::send($this, 'GET', 'contacts?per_page=2&cursor='.$cursor);

    $firstNamesRows = $firstPage->json('data');

    $firstNames = array_column($firstNamesRows, 'name');
    $secondNamesRows = $secondPage->json('data');
    $secondNames = array_column($secondNamesRows, 'name');
    expect($firstNames)->toBe(['Alpha', 'Beta']);
    expect($secondNames)->toBe(['Gamma']);
});

it('answers a stable code for wrong list options', function (string $query, string $field, string $code) {
    $response = ContactsScenario::send($this, 'GET', 'contacts?'.$query);

    ContactsScenario::assertFieldError($response, $field, $code);
})->with([
    'role' => ['role=wholesaler', 'role', 'role.invalid'],
    'page size' => ['per_page=101', 'per_page', 'per_page.invalid'],
]);

it('refuses every contact route without a session or without a shop', function (string $method, string $uri) {
    $contact = ContactsScenario::contact($this->shop);
    $contactId = $contact->uuid();
    $path = str_replace('{contact}', $contactId, $uri);
    $body = ContactsScenario::creation();
    $tokenWithoutShop = ContactsScenario::tokenOfMerchantWithoutShop();

    ContactsScenario::sendWithoutSession($this, $method, $path, $body)
        ->assertUnauthorized()
        ->assertExactJson(['error' => ['code' => 'auth.unauthenticated']]);
    ContactsScenario::send($this, $method, $path, $body, $tokenWithoutShop)
        ->assertForbidden()
        ->assertExactJson(['error' => ['code' => 'shop.required']]);
})->with([
    ['GET', 'contacts'],
    ['POST', 'contacts'],
    ['GET', 'contacts/{contact}'],
    ['PATCH', 'contacts/{contact}'],
]);
