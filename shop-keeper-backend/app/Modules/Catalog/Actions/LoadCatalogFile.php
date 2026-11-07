<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\CatalogLanguage;
use App\Modules\Catalog\Models\CatalogStatus;
use App\Modules\Catalog\Models\CatalogTermKind;
use App\Modules\Catalog\Models\GlobalProduct;
use App\Modules\Catalog\Models\GlobalProductTerm;
use App\Modules\Catalog\Support\CatalogFileEntry;
use App\Modules\Catalog\Support\CatalogFileError;
use App\Modules\Catalog\Support\CatalogLoadResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LoadCatalogFile
{
    public function __construct(private ReplaceCatalogNames $replaceCatalogNames) {}

    public function handle(string $path): CatalogLoadResult
    {
        $entries = $this->readEntries($path);
        $targets = $this->targetsInDatabase($entries);

        return DB::transaction(function () use ($entries, $targets): CatalogLoadResult {
            $counts = ['added' => 0, 'updated' => 0, 'unchanged' => 0];

            foreach ($entries as $position => $entry) {
                $counts[$this->apply($entry, $targets[$position])]++;
            }

            return new CatalogLoadResult($counts['added'], $counts['updated'], $counts['unchanged']);
        });
    }

    /**
     * @return array<int, CatalogFileEntry>
     */
    private function readEntries(string $path): array
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        $decoded = $contents === false ? null : json_decode($contents, true);

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new CatalogFileError('file.invalid');
        }

        $entries = [];
        $positionsByKey = [];
        $positionsByBarcode = [];

        foreach ($decoded as $index => $rawEntry) {
            $position = $index + 1;

            try {
                $entry = CatalogFileEntry::fromArray($rawEntry);
            } catch (CatalogFileError $error) {
                throw $error->at($position, CatalogFileEntry::keyOf($rawEntry));
            }

            if (isset($positionsByKey[$entry->key])) {
                throw (new CatalogFileError('key.duplicate'))->at($position, $entry->key);
            }

            if ($entry->barcode !== null && isset($positionsByBarcode[$entry->barcode->value])) {
                throw (new CatalogFileError('barcode.duplicate'))->at($position, $entry->key);
            }

            $positionsByKey[$entry->key] = $position;

            if ($entry->barcode !== null) {
                $positionsByBarcode[$entry->barcode->value] = $position;
            }

            $entries[$position] = $entry;
        }

        return $entries;
    }

    /**
     * @param  array<int, CatalogFileEntry>  $entries
     * @return array<int, ?GlobalProduct>
     */
    private function targetsInDatabase(array $entries): array
    {
        $entryList = collect($entries);
        $byKey = GlobalProduct::query()->whereIn('translation_key', $entryList->pluck('key'))->get()->keyBy('translation_key');
        $byBarcode = GlobalProduct::query()
            ->whereIn('barcode', $entryList->pluck('barcode.value')->filter())
            ->get()
            ->keyBy('barcode');

        return $entryList
            ->map(function (CatalogFileEntry $entry, int $position) use ($byKey, $byBarcode): ?GlobalProduct {
                try {
                    return $this->targetOf($entry, $byKey, $byBarcode);
                } catch (CatalogFileError $error) {
                    throw $error->at($position, $entry->key);
                }
            })
            ->all();
    }

    private function targetOf(CatalogFileEntry $entry, Collection $byKey, Collection $byBarcode): ?GlobalProduct
    {
        $productWithKey = $byKey->get($entry->key);

        if ($entry->barcode === null) {
            return $productWithKey;
        }

        $productWithBarcode = $byBarcode->get($entry->barcode->value);

        if ($productWithKey?->barcode !== null && $productWithKey->barcode !== $entry->barcode->value) {
            throw new CatalogFileError('barcode.changed');
        }

        if ($productWithBarcode === null || $productWithBarcode->is($productWithKey)) {
            return $productWithKey;
        }

        if ($productWithBarcode->isVerified()) {
            throw new CatalogFileError('barcode.taken');
        }

        if ($productWithKey !== null) {
            throw new CatalogFileError('key.taken');
        }

        return $productWithBarcode;
    }

    private function apply(CatalogFileEntry $entry, ?GlobalProduct $target): string
    {
        if ($target === null) {
            $product = GlobalProduct::query()->create([
                'translation_key' => $entry->key,
                'status' => CatalogStatus::Verified,
                'barcode' => $entry->barcode?->value,
            ]);
            $this->replaceCatalogNames->handle($product, $entry->names, $entry->spellings);

            return 'added';
        }

        if ($this->isUnchanged($entry, $target)) {
            return 'unchanged';
        }

        $target->fill([
            'translation_key' => $entry->key,
            'status' => CatalogStatus::Verified,
            'barcode' => $entry->barcode?->value ?? $target->barcode,
            'pending_name' => null,
            'pending_language' => null,
        ]);
        $target->touch();
        $this->replaceCatalogNames->handle($target, $entry->names, $entry->spellings);

        return 'updated';
    }

    private function isUnchanged(CatalogFileEntry $entry, GlobalProduct $target): bool
    {
        return $target->isVerified()
            && $target->translation_key === $entry->key
            && ($entry->barcode === null || $entry->barcode->value === $target->barcode)
            && $this->storedNames($target) === $entry->names
            && $this->storedSpellings($target) === $entry->spellings;
    }

    private function storedNames(GlobalProduct $product): array
    {
        $names = $product->names()->pluck('name', 'language');

        return $this->byLanguage(fn (string $language): ?string => $names->get($language));
    }

    private function storedSpellings(GlobalProduct $product): array
    {
        $spellings = $product->terms()
            ->where('kind', CatalogTermKind::Spelling)
            ->orderBy('id')
            ->get()
            ->groupBy('language');

        return $this->byLanguage(fn (string $language): array => $spellings
            ->get($language, collect())
            ->map(fn (GlobalProductTerm $term): string => $term->term)
            ->all());
    }

    private function byLanguage(callable $valueOf): array
    {
        return collect(CatalogLanguage::cases())
            ->mapWithKeys(fn (CatalogLanguage $language): array => [$language->value => $valueOf($language->value)])
            ->all();
    }
}
