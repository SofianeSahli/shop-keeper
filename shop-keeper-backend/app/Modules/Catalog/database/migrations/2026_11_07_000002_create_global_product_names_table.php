<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_product_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_product_id')->constrained('global_products')->cascadeOnDelete();
            $table->char('language', 2);
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['global_product_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_product_names');
    }
};
