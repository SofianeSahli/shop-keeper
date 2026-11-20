<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->binary('supplier_id', length: 16, fixed: true);
            $table->binary('delivery_id', length: 16, fixed: true)->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->string('method', 10);
            $table->date('payment_date');
            $table->string('reference', 50)->nullable();
            $table->char('sort_key', 32)->storedAs('lower(hex(`id`))');
            $table->timestamp('created_at')->nullable();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->restrictOnDelete();
            $table->foreign('delivery_id')->references('id')->on('deliveries')->restrictOnDelete();
            $table->index(['shop_id', 'payment_date', 'sort_key']);
            $table->index(['shop_id', 'supplier_id', 'payment_date', 'sort_key'], 'supplier_payments_supplier_list_index');
        });

        Schema::create('supplier_payment_cancellations', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->binary('supplier_payment_id', length: 16, fixed: true)->unique();
            $table->timestamp('created_at')->nullable();
            $table->foreign('supplier_payment_id')->references('id')->on('supplier_payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_cancellations');
        Schema::dropIfExists('supplier_payments');
    }
};
