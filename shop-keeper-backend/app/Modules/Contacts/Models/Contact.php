<?php

namespace App\Modules\Contacts\Models;

use App\Modules\Catalog\Support\NameComparison;
use App\Modules\Contacts\database\factories\ContactFactory;
use App\Shared\Database\HasBinaryUuidKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'shop_id', 'name', 'phone', 'address', 'note', 'is_supplier', 'is_customer'])]
class Contact extends Model
{
    use HasBinaryUuidKey, HasFactory;

    public const NAME_MAX_LENGTH = 120;

    public const TEXT_MAX_LENGTH = 255;

    public function scopeOfShop(Builder $query, int $shopId): void
    {
        $query->where('shop_id', $shopId);
    }

    public function scopeWithRole(Builder $query, ?ContactRole $role): void
    {
        if ($role === null) {
            return;
        }

        $roleColumn = $role->column();
        $query->where($roleColumn, true);
    }

    protected static function booted(): void
    {
        static::saving(function (Contact $contact): void {
            $contact->compared_name = NameComparison::compared($contact->name);
            $contact->identity_key = NameComparison::identityKey($contact->name);
        });
    }

    protected function casts(): array
    {
        return [
            'is_supplier' => 'boolean',
            'is_customer' => 'boolean',
        ];
    }

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }
}
