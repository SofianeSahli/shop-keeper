<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 120)->change();
            $table->string('password')->nullable()->change();
            $table->string('google_id')->nullable()->unique()->after('password');
            $table->string('phone', 16)->nullable()->after('google_id');
            $table->string('terms_version', 20)->after('phone');
            $table->timestamp('terms_accepted_at')->after('terms_version');
            $table->string('pin_hash')->nullable()->after('terms_accepted_at');
            $table->unsignedTinyInteger('pin_failed_attempts')->default(0)->after('pin_hash');
            $table->timestamp('pin_locked_until')->nullable()->after('pin_failed_attempts');
            $table->unsignedTinyInteger('password_failed_attempts')->default(0)->after('pin_locked_until');
            $table->timestamp('password_locked_until')->nullable()->after('password_failed_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn([
                'google_id',
                'phone',
                'terms_version',
                'terms_accepted_at',
                'pin_hash',
                'pin_failed_attempts',
                'pin_locked_until',
                'password_failed_attempts',
                'password_locked_until',
            ]);
            $table->string('name')->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
