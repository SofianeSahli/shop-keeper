<?php

namespace App\Modules\Catalog\Controllers\Requests;

use App\Modules\Catalog\Controllers\Requests\Rules\PriceInMillimes;
use App\Modules\Catalog\Models\ShopProduct;
use App\Modules\Catalog\Support\ShopProductChanges;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateShopProductRequest extends FormRequest
{
    private const CHANGEABLE_FIELDS = ['name', 'sale_price', 'purchase_price', 'low_stock_level'];

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.ShopProduct::NAME_MAX_LENGTH],
            'sale_price' => ['sometimes', ...PriceInMillimes::rules(required: true)],
            'purchase_price' => ['sometimes', ...PriceInMillimes::rules(required: false)],
            'low_stock_level' => ['sometimes', 'nullable', 'integer:strict', 'min:0', 'max:'.ShopProduct::MAX_LOW_STOCK_LEVEL],
            'confirm_different' => ['sometimes', 'boolean'],
            'id' => ['prohibited'],
            'barcode' => ['prohibited'],
            'catalog_product_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'name.required',
            'name.string' => 'name.invalid',
            'name.max' => 'name.too_long',
            'low_stock_level.integer' => 'low_stock_level.integer',
            'low_stock_level.min' => 'low_stock_level.negative',
            'low_stock_level.max' => 'low_stock_level.too_high',
            'confirm_different.boolean' => 'confirm_different.boolean',
            'id.prohibited' => 'id.prohibited',
            'barcode.prohibited' => 'barcode.prohibited',
            'catalog_product_id.prohibited' => 'catalog_product_id.prohibited',
            ...PriceInMillimes::messages('sale_price'),
            ...PriceInMillimes::messages('purchase_price'),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(self::CHANGEABLE_FIELDS)) {
                $validator->errors()->add('fields', 'fields.required');
            }
        }];
    }

    public function changes(): ShopProductChanges
    {
        $name = $this->validated('name');
        $salePrice = $this->validated('sale_price');
        $changesPurchasePrice = $this->has('purchase_price');
        $purchasePrice = $this->validated('purchase_price');
        $confirmedDifferent = $this->boolean('confirm_different');
        $changesLowStockLevel = $this->has('low_stock_level');
        $lowStockLevel = $this->validated('low_stock_level');

        return new ShopProductChanges(
            name: $name,
            salePrice: $salePrice,
            changesPurchasePrice: $changesPurchasePrice,
            purchasePrice: $purchasePrice,
            confirmedDifferent: $confirmedDifferent,
            changesLowStockLevel: $changesLowStockLevel,
            lowStockLevel: $lowStockLevel,
        );
    }
}
