<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_material_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('project_material_lines', 'track_status')) {
                $table->string('track_status')->default('required')->after('unit_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_material_lines', function (Blueprint $table) {
            if (Schema::hasColumn('project_material_lines', 'track_status')) {
                $table->dropColumn('track_status');
            }
        });
    }
};
