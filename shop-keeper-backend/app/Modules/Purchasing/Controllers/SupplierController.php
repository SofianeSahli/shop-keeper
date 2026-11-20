<?php

namespace App\Modules\Purchasing\Controllers;

use App\Modules\Purchasing\Actions\CreateSupplier;
use App\Modules\Purchasing\Actions\FindSupplierOfShop;
use App\Modules\Purchasing\Actions\UpdateSupplier;
use App\Modules\Purchasing\Controllers\Requests\CreateSupplierRequest;
use App\Modules\Purchasing\Controllers\Requests\ListRequest;
use App\Modules\Purchasing\Controllers\Requests\UpdateSupplierRequest;
use App\Modules\Purchasing\Controllers\Resources\SupplierResource;
use App\Modules\Purchasing\Models\Supplier;
use App\Shared\CurrentShop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final class SupplierController
{
    public function index(ListRequest $request, CurrentShop $currentShop): AnonymousResourceCollection
    {
        $pageSize = $request->pageSize();
        $suppliers = Supplier::query()
            ->ofShop($currentShop->shopId)
            ->withBalance()
            ->orderBy('compared_name')
            ->orderBy('identity_key')
            ->cursorPaginate($pageSize)
            ->withQueryString();

        return SupplierResource::collection($suppliers);
    }

    public function store(CreateSupplierRequest $request, CurrentShop $currentShop, CreateSupplier $createSupplier): JsonResponse
    {
        $newSupplier = $request->newSupplier($currentShop->shopId);
        $created = $createSupplier->handle($newSupplier);
        $status = $created->wasCreated ? Response::HTTP_CREATED : Response::HTTP_OK;

        return (new SupplierResource($created->record))->response()->setStatusCode($status);
    }

    public function show(string $supplier, CurrentShop $currentShop, FindSupplierOfShop $findSupplierOfShop): SupplierResource
    {
        $foundSupplier = $findSupplierOfShop->handle($currentShop->shopId, $supplier);
        Gate::authorize('view', $foundSupplier);

        return new SupplierResource($foundSupplier);
    }

    public function update(
        UpdateSupplierRequest $request,
        string $supplier,
        CurrentShop $currentShop,
        FindSupplierOfShop $findSupplierOfShop,
        UpdateSupplier $updateSupplier,
    ): SupplierResource {
        $foundSupplier = $findSupplierOfShop->handle($currentShop->shopId, $supplier);
        Gate::authorize('update', $foundSupplier);

        $changes = $request->changes();
        $updatedSupplier = $updateSupplier->handle($foundSupplier, $changes);

        return new SupplierResource($updatedSupplier);
    }
}
