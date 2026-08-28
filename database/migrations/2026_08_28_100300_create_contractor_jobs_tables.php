<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contractor_id')->constrained('parties')->cascadeOnDelete();
            $table->string('title');
            $table->decimal('qty', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('job_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('contractor_jobs')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_payments');
        Schema::dropIfExists('contractor_jobs');
    }
};
