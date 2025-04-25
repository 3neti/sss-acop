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
        Schema::create('identifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('id_type'); // cast to KYCIdType
            $table->string('id_value'); // can be email, mobile, national ID, etc.
            $table->json('meta')->nullable(); // store extra fields per type
            $table->timestamps();

            $table->unique(['id_type', 'id_value']); // enforce uniqueness per type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identifications');
    }
};
