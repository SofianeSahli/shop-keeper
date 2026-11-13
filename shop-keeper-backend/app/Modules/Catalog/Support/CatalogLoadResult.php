<?php

namespace App\Modules\Catalog\Support;

final readonly class CatalogLoadResult
{
    public function __construct(
        public int $added,
        public int $updated,
        public int $unchanged,
    ) {}
}
