<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_cancellations', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->binary('payment_id', length: 16, fixed: true)->unique();
            $table->string('reason', 255)->nullable();
            $table->foreignId('cancelled_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_cancellations');
    }
};
