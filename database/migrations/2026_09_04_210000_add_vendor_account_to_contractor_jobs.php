<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractor_jobs', function (Blueprint $table) {
            $table->foreignId('vendor_account_id')
                ->nullable()
                ->after('contractor_id')
                ->constrained('vendor_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contractor_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendor_account_id');
        });
    }
};
