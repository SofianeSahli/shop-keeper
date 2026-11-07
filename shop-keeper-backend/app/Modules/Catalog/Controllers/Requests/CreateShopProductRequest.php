<?php

namespace App\Modules\Catalog\Controllers\Requests;

use App\Modules\Catalog\Controllers\Requests\Rules\PriceInMillimes;
use App\Modules\Catalog\Controllers\Requests\Rules\ValidBarcode;
use App\Modules\Catalog\Models\CatalogLanguage;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\Barcode;
use App\Modules\Catalog\Support\NewShopProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateShopProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'uuid'],
            'barcode' => ['nullable', new ValidBarcode],
            'catalog_product_id' => ['nullable', 'integer:strict', Rule::prohibitedIf(fn (): bool => $this->filled('barcode'))],
            'name' => ['nullable', 'string', 'max:'.ShopProduct::NAME_MAX_LENGTH],
            'language' => ['nullable', Rule::enum(CatalogLanguage::class)],
            'sale_price' => PriceInMillimes::rules(required: true),
            'purchase_price' => PriceInMillimes::rules(required: false),
            'confirm_different' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'id.required',
            'id.string' => 'id.invalid',
            'id.uuid' => 'id.invalid',
            'catalog_product_id.integer' => 'catalog_product_id.unknown',
            'catalog_product_id.prohibited' => 'catalog_product_id.prohibited',
            'name.string' => 'name.invalid',
            'name.max' => 'name.too_long',
            'language.Illuminate\Validation\Rules\Enum' => 'language.invalid',
            'confirm_different.boolean' => 'confirm_different.boolean',
            ...PriceInMillimes::messages('sale_price'),
            ...PriceInMillimes::messages('purchase_price'),
        ];
    }

    public function newShopProduct(int $shopId): NewShopProduct
    {
        $barcode = $this->validated('barcode');
        $language = $this->validated('language');

        return new NewShopProduct(
            id: strtolower($this->validated('id')),
            shopId: $shopId,
            barcode: $barcode === null ? null : Barcode::parse($barcode),
            catalogProductId: $this->validated('catalog_product_id'),
            name: $this->validated('name'),
            language: $language === null ? null : CatalogLanguage::from($language),
            salePrice: $this->validated('sale_price'),
            purchasePrice: $this->validated('purchase_price'),
            confirmedDifferent: $this->boolean('confirm_different'),
        );
    }
}
