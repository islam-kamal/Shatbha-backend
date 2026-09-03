<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_boards', function (Blueprint $table) {
            $table->string('style')->nullable()->after('title');
            $table->text('designer_notes')->nullable()->after('style');
        });

        Schema::table('inspiration_items', function (Blueprint $table) {
            $table->string('room')->nullable()->after('design_board_id');
            $table->string('category')->nullable()->after('room');
            $table->text('notes')->nullable()->after('tags');
        });

        Schema::rename('floor_plans', 'design_plans');

        Schema::table('design_plans', function (Blueprint $table) {
            $table->string('type')->default('floor')->after('project_id');
            $table->string('title')->nullable()->after('type');
            $table->unsignedInteger('version')->default(1)->after('room');
            $table->string('status')->default('draft')->after('version');
            $table->foreignId('inspiration_item_id')
                ->nullable()
                ->after('media_id')
                ->constrained('inspiration_items')
                ->nullOnDelete();
        });

        // Backfill title from room where missing
        DB::table('design_plans')
            ->whereNull('title')
            ->update(['title' => DB::raw("COALESCE(room, 'مخطط')")]);

        Schema::create('design_plan_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_plan_id')->constrained('design_plans')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_account_id')->nullable()->constrained('client_accounts')->nullOnDelete();
            $table->string('author_label')->nullable();
            $table->text('body');
            $table->timestamps();
        });

        Schema::table('boq_lines', function (Blueprint $table) {
            $table->foreignId('inspiration_item_id')
                ->nullable()
                ->after('rate')
                ->constrained('inspiration_items')
                ->nullOnDelete();
            $table->foreignId('design_plan_id')
                ->nullable()
                ->after('inspiration_item_id')
                ->constrained('design_plans')
                ->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('design_submitted_at')->nullable()->after('design_approved_at');
            $table->text('design_reject_reason')->nullable()->after('design_submitted_at');
        });

        // Existing projects that were "pending" by default: treat as draft until company submits.
        DB::table('projects')
            ->where('design_status', 'pending')
            ->whereNull('design_approved_at')
            ->update(['design_status' => 'draft']);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['design_submitted_at', 'design_reject_reason']);
        });

        Schema::table('boq_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('design_plan_id');
            $table->dropConstrainedForeignId('inspiration_item_id');
        });

        Schema::dropIfExists('design_plan_comments');

        Schema::table('design_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inspiration_item_id');
            $table->dropColumn(['type', 'title', 'version', 'status']);
        });

        Schema::rename('design_plans', 'floor_plans');

        Schema::table('inspiration_items', function (Blueprint $table) {
            $table->dropColumn(['room', 'category', 'notes']);
        });

        Schema::table('design_boards', function (Blueprint $table) {
            $table->dropColumn(['style', 'designer_notes']);
        });
    }
};
