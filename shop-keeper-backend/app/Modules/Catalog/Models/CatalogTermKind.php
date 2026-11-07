<?php

namespace App\Modules\Catalog\Models;

enum CatalogTermKind: string
{
    case Name = 'name';
    case Spelling = 'spelling';
}
