<?php

use App\Modules\Purchasing\Tests\Support\PurchasingScenario as Scenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = Scenario::merchantWithShop();
    ['shop' => $this->otherShop, 'token' => $this->otherToken] = Scenario::merchantWithShop();

    $mySupplier = Scenario::supplier($this->shop, ['name' => 'Mine']);
    $this->mySupplierId = $mySupplier->uuid();
    $myProduct = Scenario::product($this->shop);
    $myLine = Scenario::line($myProduct);
    $this->myDelivery = Scenario::delivery([$myLine], ['supplier_id' => $this->mySupplierId]);
    Scenario::send($this, 'POST', 'deliveries', $this->myDelivery)->assertCreated();
    $this->myPayment = Scenario::payment($mySupplier);
    Scenario::send($this, 'POST', 'supplier-payments', $this->myPayment)->assertCreated();

    $foreignSupplier = Scenario::supplier($this->otherShop, ['name' => 'Foreign secret']);
    $this->foreignSupplierId = $foreignSupplier->uuid();
    $foreignProduct = Scenario::product($this->otherShop);
    $foreignLine = Scenario::line($foreignProduct);
    $this->foreignDelivery = Scenario::delivery([$foreignLine], ['supplier_id' => $this->foreignSupplierId, 'reference' => 'SECRET-REF']);
    Scenario::send($this, 'POST', 'deliveries', $this->foreignDelivery, $this->otherToken)->assertCreated();
    $this->foreignPayment = Scenario::payment($foreignSupplier, ['reference' => 'SECRET-PAY']);
    Scenario::send($this, 'POST', 'supplier-payments', $this->foreignPayment, $this->otherToken)->assertCreated();
});

it('answers every route on another shop record like an unknown one', function (string $method, string $path, string $code) {
    $paths = [
        'supplier' => "suppliers/{$this->foreignSupplierId}",
        'delivery' => "deliveries/{$this->foreignDelivery['id']}",
        'delivery cancellation' => "deliveries/{$this->foreignDelivery['id']}/cancellation",
        'payment' => "supplier-payments/{$this->foreignPayment['id']}",
        'payment cancellation' => "supplier-payments/{$this->foreignPayment['id']}/cancellation",
    ];
    $cancellationId = Scenario::newId();
    $body = $method === 'PATCH' ? ['name' => 'Renamed'] : ['id' => $cancellationId];

    $response = Scenario::send($this, $method, $paths[$path], $body)
        ->assertNotFound()
        ->assertExactJson(['error' => ['code' => $code]]);

    $content = $response->getContent();
    expect($content)->not->toContain('SECRET')->not->toContain('Foreign secret');
})->with([
    ['GET', 'supplier', 'supplier.not_found'],
    ['PATCH', 'supplier', 'supplier.not_found'],
    ['GET', 'delivery', 'delivery.not_found'],
    ['POST', 'delivery cancellation', 'delivery.not_found'],
    ['GET', 'payment', 'supplier_payment.not_found'],
    ['POST', 'payment cancellation', 'supplier_payment.not_found'],
]);

it('lists only the shop own records', function (string $path) {
    $response = Scenario::send($this, 'GET', $path)->assertOk()->assertJsonCount(1, 'data');

    $content = $response->getContent();
    expect($content)->not->toContain('SECRET')->not->toContain('Foreign secret');
})->with(['suppliers', 'deliveries', 'supplier-payments']);
