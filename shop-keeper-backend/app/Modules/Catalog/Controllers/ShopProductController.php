<?php

namespace App\Modules\Catalog\Controllers;

use App\Modules\Catalog\Actions\CreateShopProduct;
use App\Modules\Catalog\Actions\UpdateShopProduct;
use App\Modules\Catalog\Controllers\Requests\CreateShopProductRequest;
use App\Modules\Catalog\Controllers\Requests\ListProductsRequest;
use App\Modules\Catalog\Controllers\Requests\UpdateShopProductRequest;
use App\Modules\Catalog\Controllers\Resources\ShopProductResource;
use App\Modules\Catalog\Models\ShopProduct;
use App\Shared\CurrentShop;
use App\Shared\Errors\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class ShopProductController
{
    public function index(ListProductsRequest $request, CurrentShop $currentShop): AnonymousResourceCollection
    {
        return ShopProductResource::collection(
            ShopProduct::query()
                ->ofShop($currentShop->shopId)
                ->with('globalProduct.names')
                ->orderBy('compared_name')
                ->orderBy('identity_key')
                ->cursorPaginate($request->pageSize())
                ->withQueryString(),
        );
    }

    public function store(CreateShopProductRequest $request, CurrentShop $currentShop, CreateShopProduct $createShopProduct): JsonResponse
    {
        $created = $createShopProduct->handle($request->newShopProduct($currentShop->shopId));

        return (new ShopProductResource($created->product))
            ->response()
            ->setStatusCode($created->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function show(string $product, CurrentShop $currentShop): ShopProductResource
    {
        $foundProduct = $this->productOfShop($currentShop, $product);
        Gate::authorize('view', $foundProduct);

        return new ShopProductResource($foundProduct);
    }

    public function update(
        UpdateShopProductRequest $request,
        string $product,
        CurrentShop $currentShop,
        UpdateShopProduct $updateShopProduct,
    ): ShopProductResource {
        $foundProduct = $this->productOfShop($currentShop, $product);
        Gate::authorize('update', $foundProduct);

        return new ShopProductResource($updateShopProduct->handle($foundProduct, $request->changes()));
    }

    private function productOfShop(CurrentShop $currentShop, string $productId): ShopProduct
    {
        return ShopProduct::query()
            ->ofShop($currentShop->shopId)
            ->whereUuid($productId)
            ->with('globalProduct.names')
            ->first() ?? throw new ApiException('product.not_found', Response::HTTP_NOT_FOUND);
    }
}
