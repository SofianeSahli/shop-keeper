<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\CatalogTermKind;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Support\NameComparison;

final class ReplaceCatalogNames
{
    /**
     * @param  array<string, string>  $namesByLanguage
     * @param  array<string, list<string>>  $spellingsByLanguage
     */
    public function handle(GlobalProduct $product, array $namesByLanguage, array $spellingsByLanguage): void
    {
        $product->names()->delete();
        $product->terms()->delete();

        foreach ($namesByLanguage as $language => $name) {
            $product->names()->create(['language' => $language, 'name' => $name]);
            $this->addTerm($product, $language, CatalogTermKind::Name, $name);
        }

        foreach ($spellingsByLanguage as $language => $spellings) {
            foreach ($spellings as $spelling) {
                $this->addTerm($product, $language, CatalogTermKind::Spelling, $spelling);
            }
        }

        $product->unsetRelation('names');
    }

    private function addTerm(GlobalProduct $product, string $language, CatalogTermKind $kind, string $term): void
    {
        $product->terms()->create([
            'language' => $language,
            'kind' => $kind,
            'term' => $term,
            'compared' => NameComparison::compared($term),
        ]);
    }
}
