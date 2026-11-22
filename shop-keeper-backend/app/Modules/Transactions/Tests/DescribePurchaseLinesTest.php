<?php

use App\Modules\Contacts\Tests\Support\ContactsScenario;
use App\Modules\Payments\Tests\Support\PaymentsScenario;
use App\Modules\Transactions\Support\DescribePurchaseLines;
use App\Modules\Transactions\Tests\Support\TransactionsScenario;
use Carbon\CarbonImmutable;

beforeEach(function () {
    ['shop' => $this->shop, 'token' => $this->token] = ContactsScenario::merchantWithShop();
    $this->account = $this->shop->owner_id;
    $this->tomatoes = TransactionsScenario::product($this->shop);
    $this->line = TransactionsScenario::line($this->tomatoes, 5);
    $this->purchase = TransactionsScenario::purchase([$this->line], ['transaction_date' => '2026-11-20']);
    TransactionsScenario::record($this, $this->purchase)->assertCreated();
    $this->describePurchaseLines = app(DescribePurchaseLines::class);
});

it('describes a purchase line with its transaction, date and recorder', function () {
    $sources = $this->describePurchaseLines->describe($this->shop->id, [$this->line['id']]);

    $source = $sources[$this->line['id']];
    expect([$source->transactionId, $source->transactionDate, $source->recordedBy, $source->cancelledBy, $source->cancelledOn])
        ->toBe([$this->purchase['id'], '2026-11-20', $this->account, null, null]);
});

it('gives the cancellation day in the shop time zone and the account that cancelled', function () {
    $lateEvening = CarbonImmutable::parse('2026-11-21 23:30:00', 'UTC');
    $this->travelTo($lateEvening);
    $purchaseUri = 'transactions/'.$this->purchase['id'];
    PaymentsScenario::cancel($this, $purchaseUri, ['reason' => 'wrong supplier'])->assertCreated();

    $sources = $this->describePurchaseLines->describe($this->shop->id, [$this->line['id']]);

    $source = $sources[$this->line['id']];
    expect([$source->cancelledBy, $source->cancelledOn])->toBe([$this->account, '2026-11-22']);
});

it('describes nothing for the lines of another shop', function () {
    ['shop' => $otherShop] = ContactsScenario::merchantWithShop();

    $sources = $this->describePurchaseLines->describe($otherShop->id, [$this->line['id']]);

    expect($sources)->toBe([]);
});
