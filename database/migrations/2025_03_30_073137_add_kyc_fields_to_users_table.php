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
            $table->string('id_value')->unique()->after('id');
            $table->string('id_type')->nullable()->after('id_value');
            $table->string('mobile')->nullable()->after('id_type');
            $table->string('country')->nullable()->after('mobile');
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
            $table->dropColumn('id_value');
            $table->dropColumn('id_type');
            $table->dropColumn('mobile');
            $table->dropColumn('country');
            $table->dropColumn('birthdate');
            $table->string('email')->change();
        });
    }
};
