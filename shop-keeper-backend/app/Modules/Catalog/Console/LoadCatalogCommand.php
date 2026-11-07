<?php

namespace App\Modules\Catalog\Console;

use App\Modules\Catalog\Actions\LoadCatalogFile;
use App\Modules\Catalog\Support\CatalogFileError;
use Illuminate\Console\Command;

final class LoadCatalogCommand extends Command
{
    public const DEFAULT_FILE = 'app/Modules/Catalog/database/catalog/products.json';

    protected $signature = 'catalog:load {file? : Path of the catalog file, from the project root}';

    protected $description = 'Load the shared product catalog from its JSON file';

    public function handle(LoadCatalogFile $loadCatalogFile): int
    {
        $path = base_path($this->argument('file') ?? self::DEFAULT_FILE);

        try {
            $result = $loadCatalogFile->handle($path);
        } catch (CatalogFileError $error) {
            $this->error('Catalog not loaded, nothing changed. '.$error->describe());

            return self::FAILURE;
        }

        $this->info(sprintf('Catalog loaded: %d added, %d updated, %d unchanged.', $result->added, $result->updated, $result->unchanged));

        return self::SUCCESS;
    }
}
