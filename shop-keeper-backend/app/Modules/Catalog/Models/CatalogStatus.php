<?php

namespace App\Modules\Catalog\Models;

enum CatalogStatus: string
{
    case Verified = 'verified';
    case Pending = 'pending';
}
