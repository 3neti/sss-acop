<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('deprecated_mobile')->nullable();
            $table->string('country')->nullable()->after('deprecated_mobile');
            $table->date('birthdate')->after('country');
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deprecated_mobile');
            $table->dropColumn('country');
            $table->dropColumn('birthdate');
            $table->string('email')->change();
        });
    }
};
