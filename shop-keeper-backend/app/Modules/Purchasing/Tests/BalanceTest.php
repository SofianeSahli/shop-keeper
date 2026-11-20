<?php

use App\Modules\Purchasing\Tests\Support\PurchasingScenario as Scenario;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = Scenario::merchantWithShop();
});

it('keeps balances and stock equal to their records after any series of changes', function () {
    $benAli = Scenario::supplier($this->shop);
    $benAliId = $benAli->uuid();
    $moulin = Scenario::supplier($this->shop);
    $moulinId = $moulin->uuid();
    $tomatoes = Scenario::product($this->shop);
    $flour = Scenario::product($this->shop);

    $tomatoLine = Scenario::line($tomatoes, quantity: 30, unitPrice: 1000);
    $flourLine = Scenario::line($flour, quantity: 5, unitPrice: 20000);
    $first = Scenario::delivery([$tomatoLine, $flourLine], ['supplier_id' => $benAliId]);
    $secondLine = Scenario::line($tomatoes, quantity: 12, unitPrice: 1100);
    $second = Scenario::delivery([$secondLine], ['supplier_id' => $benAliId]);
    $flourAgain = Scenario::line($flour, quantity: 8, unitPrice: 19000);
    $chequeId = Scenario::newId();
    $cheque = ['id' => $chequeId, 'amount' => 50000, 'method' => 'cheque'];
    $fromMoulin = Scenario::delivery([$flourAgain], ['supplier_id' => $moulinId, 'payment' => $cheque]);
    $marketLine = Scenario::line($tomatoes, quantity: 3, unitPrice: 900);
    $atMarket = Scenario::delivery([$marketLine]);

    foreach ([$first, $second, $fromMoulin, $atMarket] as $delivery) {
        Scenario::send($this, 'POST', 'deliveries', $delivery)->assertCreated();
    }

    $kept = Scenario::payment($benAli, ['amount' => 40000]);
    $cancelled = Scenario::payment($benAli, ['amount' => 15000]);
    Scenario::send($this, 'POST', 'supplier-payments', $kept)->assertCreated();
    Scenario::send($this, 'POST', 'supplier-payments', $cancelled)->assertCreated();

    $paymentCancellationId = Scenario::newId();
    $deliveryCancellationId = Scenario::newId();
    Scenario::send($this, 'POST', "supplier-payments/{$cancelled['id']}/cancellation", ['id' => $paymentCancellationId])->assertCreated();
    Scenario::send($this, 'POST', "deliveries/{$second['id']}/cancellation", ['id' => $deliveryCancellationId])->assertCreated();

    $benAliBalance = Scenario::send($this, 'GET', "suppliers/{$benAliId}")->json('data.balance');
    $moulinBalance = Scenario::send($this, 'GET', "suppliers/{$moulinId}")->json('data.balance');
    expect($benAliBalance)->toBe(130000 - 40000);
    expect($moulinBalance)->toBe(152000 - 50000);

    $tomatoStock = Scenario::stockOf($tomatoes);
    $tomatoMovements = Scenario::movementSumOf($tomatoes);
    $flourStock = Scenario::stockOf($flour);
    $flourMovements = Scenario::movementSumOf($flour);
    expect([$tomatoStock, $tomatoMovements])->toBe([33, 33]);
    expect([$flourStock, $flourMovements])->toBe([13, 13]);

    $suppliers = Scenario::send($this, 'GET', 'suppliers')->json('data');
    $listedBalances = array_column($suppliers, 'balance', 'id');
    expect($listedBalances)->toEqual([$benAliId => 90000, $moulinId => 102000]);
});
