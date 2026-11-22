<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->binary('stock_adjustment_id', length: 16, fixed: true);
            $table->unsignedSmallInteger('position');
            $table->binary('shop_product_id', length: 16, fixed: true);
            $table->integer('quantity');
            $table->unsignedInteger('counted_quantity')->nullable();
            $table->integer('shown_quantity')->nullable();
            $table->string('loss_reason', 10)->nullable();
            $table->string('note', 255)->nullable();
            $table->binary('opening_product_id', length: 16, fixed: true)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreign('stock_adjustment_id')->references('id')->on('stock_adjustments')->restrictOnDelete();
            $table->foreign('shop_product_id')->references('id')->on('shop_products')->restrictOnDelete();
            $table->unique(['stock_adjustment_id', 'shop_product_id'], 'stock_adjustment_lines_product_unique');
            $table->unique(['stock_adjustment_id', 'position']);
            $table->unique('opening_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
    }
};
