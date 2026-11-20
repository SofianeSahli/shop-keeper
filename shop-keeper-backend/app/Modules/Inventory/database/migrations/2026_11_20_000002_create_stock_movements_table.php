<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->binary('shop_product_id', length: 16, fixed: true);
            $table->string('kind', 30);
            $table->integer('quantity');
            $table->binary('source_id', length: 16, fixed: true);
            $table->timestamp('created_at')->nullable();
            $table->foreign('shop_product_id')->references('id')->on('shop_products')->restrictOnDelete();
            $table->unique(['kind', 'source_id']);
            $table->index(['shop_id', 'shop_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
