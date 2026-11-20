<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->binary('supplier_id', length: 16, fixed: true)->nullable();
            $table->date('delivery_date');
            $table->string('reference', 50)->nullable();
            $table->unsignedBigInteger('total');
            $table->unsignedSmallInteger('line_count');
            $table->char('sort_key', 32)->storedAs('lower(hex(`id`))');
            $table->timestamp('created_at')->nullable();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->index(['shop_id', 'delivery_date', 'sort_key']);
            $table->index(['shop_id', 'supplier_id', 'delivery_date', 'sort_key'], 'deliveries_supplier_list_index');
        });

        Schema::create('delivery_lines', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->binary('delivery_id', length: 16, fixed: true);
            $table->unsignedSmallInteger('position');
            $table->binary('shop_product_id', length: 16, fixed: true);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at')->nullable();
            $table->foreign('delivery_id')->references('id')->on('deliveries')->restrictOnDelete();
            $table->foreign('shop_product_id')->references('id')->on('shop_products')->restrictOnDelete();
            $table->unique(['delivery_id', 'shop_product_id']);
            $table->unique(['delivery_id', 'position']);
        });

        Schema::create('delivery_cancellations', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->binary('delivery_id', length: 16, fixed: true)->unique();
            $table->timestamp('created_at')->nullable();
            $table->foreign('delivery_id')->references('id')->on('deliveries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_cancellations');
        Schema::dropIfExists('delivery_lines');
        Schema::dropIfExists('deliveries');
    }
};
