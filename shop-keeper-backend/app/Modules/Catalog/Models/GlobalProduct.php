<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\database\factories\GlobalProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['translation_key', 'status', 'barcode', 'pending_name', 'pending_language'])]
#[Hidden(['pending_name', 'pending_language'])]
class GlobalProduct extends Model
{
    use HasFactory;

    public const KEY_PATTERN = '/^products\.[a-z0-9_]+$/';

    public const KEY_MAX_LENGTH = 100;

    public const PENDING_KEY_PREFIX = 'products.barcode_';

    public static function pendingKeyFor(string $barcode): string
    {
        return self::PENDING_KEY_PREFIX.$barcode;
    }

    public function names(): HasMany
    {
        return $this->hasMany(GlobalProductName::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(GlobalProductTerm::class);
    }

    public function isVerified(): bool
    {
        return $this->status === CatalogStatus::Verified;
    }

    public function nameIn(CatalogLanguage $language): ?string
    {
        return $this->names->firstWhere('language', $language->value)?->name;
    }

    public function namesByLanguage(): ?array
    {
        if (! $this->isVerified()) {
            return null;
        }

        return collect(CatalogLanguage::cases())
            ->mapWithKeys(fn (CatalogLanguage $language): array => [$language->value => $this->nameIn($language)])
            ->all();
    }

    protected function casts(): array
    {
        return ['status' => CatalogStatus::class];
    }

    protected static function newFactory(): GlobalProductFactory
    {
        return GlobalProductFactory::new();
    }
}
