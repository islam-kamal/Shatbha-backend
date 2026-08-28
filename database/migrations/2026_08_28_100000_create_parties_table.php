<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // customer | contractor
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('kind')->nullable(); // agreement | supervision
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('agreement_estimate', 14, 2)->default(0);
            $table->unsignedTinyInteger('supervision_percent')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
