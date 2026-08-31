<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('title');
            $table->string('site_address')->nullable();
            $table->string('status')->default('planning'); // planning|in_progress|delivered|handed_over
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget_planned', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('vendor_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // contractor|supplier
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->string('service_area')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('member_type'); // user|vendor
            $table->unsignedBigInteger('member_id');
            $table->string('role')->default('member');
            $table->timestamps();
            $table->unique(['project_id', 'member_type', 'member_id']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->string('tag')->nullable(); // photo|floor_plan|portfolio|sign_off
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->string('unit')->default('قطعة');
            $table->string('pack_unit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 14, 2);
            $table->date('effective_from')->nullable();
            $table->timestamps();
        });

        Schema::create('project_material_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->decimal('qty', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_account_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('work_type')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_account_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('draft'); // draft|sent|accepted|rejected
            $table->text('notes')->nullable();
            $table->foreignId('contractor_job_id')->nullable()->constrained('contractor_jobs')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('qty', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_account_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('design_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('لوحة الإلهام');
            $table->timestamps();
        });

        Schema::create('inspiration_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_board_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('tags')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('floor_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('room')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('boq_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('room')->nullable();
            $table->string('trade')->nullable();
            $table->string('description');
            $table->decimal('qty', 14, 2)->default(1);
            $table->string('unit')->default('م²');
            $table->decimal('rate', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('todo'); // todo|doing|done
            $table->date('due_date')->nullable();
            $table->string('assignee_type')->nullable(); // user|vendor
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->timestamps();
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('target_date')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });

        Schema::create('timeline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('event_date');
            $table->string('kind')->default('note'); // note|delay|milestone
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->decimal('planned', 14, 2)->default(0);
            $table->decimal('committed', 14, 2)->default(0);
            $table->decimal('actual', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_account_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft|approved|sent|partial|received
            $table->date('ordered_on')->nullable();
            $table->timestamps();
        });

        Schema::create('po_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 14, 2)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('received_qty', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->date('received_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind'); // in|out|transfer|adjust
            $table->decimal('qty', 14, 2);
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->date('delivered_on')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });

        Schema::create('snag_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('open'); // open|fixed|closed
            $table->timestamps();
        });

        Schema::create('handover_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('item');
            $table->boolean('is_checked')->default(false);
            $table->timestamps();
        });

        Schema::create('sign_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('signed_by')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('contractor_jobs', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('customer_entries', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $t) => $t->dropConstrainedForeignId('project_id'));
        Schema::table('customer_entries', fn (Blueprint $t) => $t->dropConstrainedForeignId('project_id'));
        Schema::table('contractor_jobs', fn (Blueprint $t) => $t->dropConstrainedForeignId('project_id'));

        Schema::dropIfExists('sign_offs');
        Schema::dropIfExists('handover_checklists');
        Schema::dropIfExists('snag_items');
        Schema::dropIfExists('delivery_milestones');
        Schema::dropIfExists('delivery_notes');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('po_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('timeline_events');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('boq_lines');
        Schema::dropIfExists('floor_plans');
        Schema::dropIfExists('inspiration_items');
        Schema::dropIfExists('design_boards');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quote_requests');
        Schema::dropIfExists('portfolio_items');
        Schema::dropIfExists('project_material_lines');
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('media');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('vendor_accounts');
        Schema::dropIfExists('projects');
    }
};
