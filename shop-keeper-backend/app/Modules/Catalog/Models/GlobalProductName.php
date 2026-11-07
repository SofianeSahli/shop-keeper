<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['global_product_id', 'language', 'name'])]
class GlobalProductName extends Model
{
    public const NAME_MAX_LENGTH = 120;
}
