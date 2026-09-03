<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained('parties')->cascadeOnDelete();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('design_status')->default('pending')->after('status');
            $table->timestamp('design_approved_at')->nullable()->after('design_status');
        });

        Schema::table('project_material_lines', function (Blueprint $table) {
            $table->string('room_name')->nullable()->after('title');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('currency', 3)->default('EGP')->after('pack');
            $table->decimal('vat_rate', 5, 2)->default(0)->after('currency');
        });

        Schema::create('delivery_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 14, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('po_line_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('delivery_note_lines');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['currency', 'vat_rate']);
        });

        Schema::table('project_material_lines', function (Blueprint $table) {
            $table->dropColumn('room_name');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['design_status', 'design_approved_at']);
        });

        Schema::dropIfExists('client_accounts');
    }
};
