<?php

namespace App\Modules\Catalog\Controllers;

use App\Modules\Catalog\Actions\SuggestProducts;
use App\Modules\Catalog\Controllers\Requests\SuggestionsRequest;
use App\Modules\Catalog\Controllers\Resources\SuggestionResource;
use App\Shared\CurrentShop;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SuggestionController
{
    public function __invoke(SuggestionsRequest $request, CurrentShop $currentShop, SuggestProducts $suggestProducts): AnonymousResourceCollection
    {
        $search = $request->validated('q');
        $suggestions = $suggestProducts->handle($currentShop->shopId, $search);

        return SuggestionResource::collection($suggestions);
    }
}
