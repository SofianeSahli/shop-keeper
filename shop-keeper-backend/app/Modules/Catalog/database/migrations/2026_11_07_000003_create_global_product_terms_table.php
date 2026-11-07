<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_product_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_product_id')->constrained('global_products')->cascadeOnDelete();
            $table->char('language', 2);
            $table->string('kind', 10);
            $table->string('term', 120);
            $table->string('compared', 255)->collation('utf8mb4_bin')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_product_terms');
    }
};
