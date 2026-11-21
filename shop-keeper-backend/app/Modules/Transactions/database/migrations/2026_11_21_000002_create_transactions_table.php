<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->string('kind', 20);
            $table->binary('contact_id', length: 16, fixed: true)->nullable();
            $table->date('transaction_date');
            $table->string('reference', 50)->nullable();
            $table->unsignedBigInteger('total');
            $table->unsignedSmallInteger('line_count');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->char('sort_key', 32)->storedAs('lower(hex(`id`))');
            $table->timestamp('created_at')->nullable();
            $table->foreign('contact_id')->references('id')->on('contacts')->restrictOnDelete();
            $table->index(['shop_id', 'transaction_date', 'sort_key'], 'transactions_date_list_index');
            $table->index(['shop_id', 'contact_id', 'transaction_date', 'sort_key'], 'transactions_contact_list_index');
            $table->index(['shop_id', 'kind', 'transaction_date', 'sort_key'], 'transactions_kind_list_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
