<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->binary('id', length: 16, fixed: true)->primary();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete();
            $table->string('kind', 20);
            $table->date('adjustment_date');
            $table->unsignedSmallInteger('line_count');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['shop_id', 'adjustment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
