<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->binary('transaction_id', length: 16, fixed: true);
            $table->unsignedSmallInteger('position');
            $table->binary('shop_product_id', length: 16, fixed: true);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at')->nullable();
            $table->foreign('transaction_id')->references('id')->on('transactions')->restrictOnDelete();
            $table->foreign('shop_product_id')->references('id')->on('shop_products')->restrictOnDelete();
            $table->unique(['transaction_id', 'shop_product_id']);
            $table->unique(['transaction_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_lines');
    }
};
