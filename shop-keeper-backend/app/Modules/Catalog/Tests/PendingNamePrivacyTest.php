<?php

use App\Modules\Catalog\database\factories\GlobalProductFactory;
use App\Modules\Catalog\Tests\Support\CatalogScenario;
use Illuminate\Support\Str;

const TYPED_SECRET_NAME = 'Zarboutella secrète';

it('never shows the name another shop typed for a pending barcode', function () {
    ['token' => $firstToken] = CatalogScenario::merchantWithShop();
    ['shop' => $secondShop, 'token' => $secondToken] = CatalogScenario::merchantWithShop();
    $barcode = GlobalProductFactory::randomBarcode();
    postProduct($this, ['barcode' => $barcode, 'name' => TYPED_SECRET_NAME, 'language' => 'fr'], $firstToken)->assertCreated();
    $ownProduct = CatalogScenario::shopProduct($secondShop, ['name' => 'Gaufrette maison']);
    app('auth')->forgetGuards();

    $responses = [
        $this->withToken($secondToken)->getJson("/api/v1/catalog/barcodes/{$barcode}")->assertOk(),
        $this->withToken($secondToken)->getJson('/api/v1/products/suggestions?q='.rawurlencode('Zarboutella'))->assertOk(),
        $this->withToken($secondToken)->getJson('/api/v1/products/suggestions?q=zarb')->assertOk(),
        postProduct($this, ['name' => 'Zarboutella secret'], $secondToken),
        postProduct($this, ['barcode' => $barcode, 'language' => 'fr'], $secondToken)->assertStatus(422),
        postProduct($this, ['barcode' => $barcode, 'name' => 'Gaufrette', 'language' => 'fr'], $secondToken)->assertCreated(),
        $this->withToken($secondToken)->getJson('/api/v1/products')->assertOk(),
        $this->withToken($secondToken)->getJson("/api/v1/products/{$ownProduct->uuid()}")->assertOk(),
        $this->withToken($secondToken)->patchJson("/api/v1/products/{$ownProduct->uuid()}", ['name' => 'Zarboutella secrete', 'confirm_different' => true])->assertOk(),
        $this->withToken($secondToken)->getJson('/api/v1/products/'.Str::uuid7())->assertStatus(404),
    ];

    foreach ($responses as $response) {
        expect($response->getContent())->not->toContain('Zarboutella secrète')
            ->and($response->getContent())->not->toContain(json_encode(TYPED_SECRET_NAME));
    }
});
