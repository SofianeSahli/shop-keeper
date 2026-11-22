<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `stock_movements` ADD COLUMN `sequence` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE');

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['shop_product_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['shop_product_id', 'sequence']);
            $table->dropColumn('sequence');
        });
    }
};
