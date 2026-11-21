<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->string('direction', 10);
            $table->binary('contact_id', length: 16, fixed: true)->nullable();
            $table->binary('transaction_id', length: 16, fixed: true)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('method', 10);
            $table->date('payment_date');
            $table->string('reference', 50)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->char('sort_key', 32)->storedAs('lower(hex(`id`))');
            $table->timestamp('created_at')->nullable();
            $table->foreign('contact_id')->references('id')->on('contacts')->restrictOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->restrictOnDelete();
            $table->index(['shop_id', 'payment_date', 'sort_key'], 'payments_date_list_index');
            $table->index(['shop_id', 'contact_id', 'payment_date', 'sort_key'], 'payments_contact_list_index');
            $table->index(['transaction_id']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_contact_or_transaction CHECK (contact_id IS NOT NULL OR transaction_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
