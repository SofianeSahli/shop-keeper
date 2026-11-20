<?php

use App\Modules\Purchasing\Models\DeliveryCancellation;
use App\Modules\Purchasing\Tests\Support\PurchasingScenario as Scenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = Scenario::merchantWithShop();
    $this->supplier = Scenario::supplier($this->shop);
    $this->supplierId = $this->supplier->uuid();
    $this->tomatoes = Scenario::product($this->shop, ['purchase_price' => 1100]);
    $this->harissa = Scenario::product($this->shop);
});

function deliverThenCancelLater(object $test, array $lines, array $fields = []): array
{
    $body = Scenario::delivery($lines, $fields);
    Scenario::send($test, 'POST', 'deliveries', $body)->assertCreated();

    return $body;
}

function cancelDelivery(object $test, string $deliveryId, ?string $cancellationId = null, ?string $token = null)
{
    $id = $cancellationId ?? Scenario::newId();

    return Scenario::send($test, 'POST', "deliveries/{$deliveryId}/cancellation", ['id' => $id], $token);
}

it('cancels a delivery and takes its stock back', function () {
    $tomatoLine = Scenario::line($this->tomatoes, quantity: 24, unitPrice: 1200);
    $harissaLine = Scenario::line($this->harissa, quantity: 5);
    $delivery = deliverThenCancelLater($this, [$tomatoLine, $harissaLine], ['supplier_id' => $this->supplierId]);

    $response = cancelDelivery($this, $delivery['id'])->assertCreated()->assertJsonPath('data.id', $delivery['id']);

    $cancelledAt = $response->json('data.cancelled_at');
    expect($cancelledAt)->not->toBeNull();

    $tomatoStock = Scenario::stockOf($this->tomatoes);
    $harissaStock = Scenario::stockOf($this->harissa);
    $tomatoMovements = Scenario::movementSumOf($this->tomatoes);
    $purchasePrice = $this->tomatoes->fresh()->purchase_price;
    expect([$tomatoStock, $harissaStock, $tomatoMovements, $purchasePrice])->toBe([0, 0, 0, 1100]);

    Scenario::send($this, 'GET', "suppliers/{$this->supplierId}")->assertJsonPath('data.balance', 0);
});

it('changes nothing when cancelled again', function () {
    $line = Scenario::line($this->tomatoes, quantity: 3);
    $delivery = deliverThenCancelLater($this, [$line]);
    cancelDelivery($this, $delivery['id'])->assertCreated();

    cancelDelivery($this, $delivery['id'])->assertOk()->assertJsonPath('data.id', $delivery['id']);

    $tomatoStock = Scenario::stockOf($this->tomatoes);
    $cancellationCount = DeliveryCancellation::query()->count();
    expect([$tomatoStock, $cancellationCount])->toBe([0, 1]);
});

it('takes back only that delivery when the product was delivered again', function () {
    $firstLine = Scenario::line($this->tomatoes, quantity: 10);
    $first = deliverThenCancelLater($this, [$firstLine]);
    $secondLine = Scenario::line($this->tomatoes, quantity: 4);
    deliverThenCancelLater($this, [$secondLine]);

    cancelDelivery($this, $first['id'])->assertCreated();

    $tomatoStock = Scenario::stockOf($this->tomatoes);
    expect($tomatoStock)->toBe(4);
});

it('keeps a payment recorded with the delivery', function () {
    $line = Scenario::line($this->tomatoes, quantity: 10, unitPrice: 5000);
    $paymentId = Scenario::newId();
    $payment = ['id' => $paymentId, 'amount' => 20000, 'method' => 'cash'];
    $delivery = deliverThenCancelLater($this, [$line], ['supplier_id' => $this->supplierId, 'payment' => $payment]);

    cancelDelivery($this, $delivery['id'])->assertCreated()->assertJsonPath('data.payment.cancelled_at', null);

    Scenario::send($this, 'GET', "suppliers/{$this->supplierId}")->assertJsonPath('data.balance', -20000);
});

it('has no way to change or delete a delivery', function (string $method) {
    $line = Scenario::line($this->tomatoes);
    $delivery = deliverThenCancelLater($this, [$line]);

    Scenario::send($this, $method, "deliveries/{$delivery['id']}", ['reference' => 'X'])->assertStatus(405);
})->with(['PATCH', 'PUT', 'DELETE']);

it('refuses a missing or invalid cancellation id', function () {
    $line = Scenario::line($this->tomatoes);
    $delivery = deliverThenCancelLater($this, [$line]);

    $missing = Scenario::send($this, 'POST', "deliveries/{$delivery['id']}/cancellation", []);
    Scenario::assertFieldError($missing, 'id', 'id.required');
    $invalid = cancelDelivery($this, $delivery['id'], 'nope');
    Scenario::assertFieldError($invalid, 'id', 'id.invalid');
});

it('answers another shop delivery as not found and refuses another shop cancellation id', function () {
    ['shop' => $otherShop, 'token' => $otherToken] = Scenario::merchantWithShop();
    $foreignProduct = Scenario::product($otherShop);
    $foreignLine = Scenario::line($foreignProduct, quantity: 7);
    $foreignDelivery = Scenario::delivery([$foreignLine]);
    Scenario::send($this, 'POST', 'deliveries', $foreignDelivery, $otherToken)->assertCreated();

    cancelDelivery($this, $foreignDelivery['id'])
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => 'delivery.not_found']]);

    $foreignStock = Scenario::stockOf($foreignProduct);
    expect($foreignStock)->toBe(7);

    $foreignCancellationId = Scenario::newId();
    cancelDelivery($this, $foreignDelivery['id'], $foreignCancellationId, $otherToken)->assertCreated();
    $myLine = Scenario::line($this->tomatoes);
    $myDelivery = deliverThenCancelLater($this, [$myLine]);
    $reused = cancelDelivery($this, $myDelivery['id'], $foreignCancellationId);
    Scenario::assertFieldError($reused, 'id', 'id.taken');
});

it('refuses a request with no session and a merchant with no shop', function () {
    $line = Scenario::line($this->tomatoes);
    $delivery = deliverThenCancelLater($this, [$line]);
    $cancellationId = Scenario::newId();

    Scenario::sendWithoutSession($this, 'POST', "deliveries/{$delivery['id']}/cancellation", ['id' => $cancellationId])->assertUnauthorized();

    $noShopToken = Scenario::tokenOfMerchantWithoutShop();
    cancelDelivery($this, $delivery['id'], null, $noShopToken)->assertForbidden();
});
