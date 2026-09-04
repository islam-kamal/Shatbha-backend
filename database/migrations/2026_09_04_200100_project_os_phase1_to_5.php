<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. Enrich projects table ──────────────────────────────────────────
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'lifecycle_status')) {
                $table->string('lifecycle_status')->default('contracted')->after('status');
            }
            if (! Schema::hasColumn('projects', 'next_action')) {
                $table->string('next_action')->nullable()->after('lifecycle_status');
            }
            if (! Schema::hasColumn('projects', 'next_action_label_ar')) {
                $table->string('next_action_label_ar')->nullable()->after('next_action');
            }
            if (! Schema::hasColumn('projects', 'progress_design')) {
                $table->unsignedTinyInteger('progress_design')->default(0);
            }
            if (! Schema::hasColumn('projects', 'progress_procurement')) {
                $table->unsignedTinyInteger('progress_procurement')->default(0);
            }
            if (! Schema::hasColumn('projects', 'progress_execution')) {
                $table->unsignedTinyInteger('progress_execution')->default(0);
            }
            if (! Schema::hasColumn('projects', 'progress_finance')) {
                $table->unsignedTinyInteger('progress_finance')->default(0);
            }
            if (! Schema::hasColumn('projects', 'contract_value')) {
                $table->decimal('contract_value', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('projects', 'actual_cost')) {
                $table->decimal('actual_cost', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('projects', 'committed_cost')) {
                $table->decimal('committed_cost', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('projects', 'forecast_cost')) {
                $table->decimal('forecast_cost', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('projects', 'execution_unlocked')) {
                $table->boolean('execution_unlocked')->default(false);
            }
        });

        // ─── 2. leads ─────────────────────────────────────────────────────────
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('site_address')->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->string('property_type')->nullable();
            $table->string('finish_type')->nullable();
            $table->decimal('budget_expected', 15, 2)->nullable();
            $table->date('start_expected')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('party_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->timestamps();
        });

        // ─── 3. site_visits ───────────────────────────────────────────────────
        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('lead_id');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->json('checklist_json')->nullable();
            $table->text('notes')->nullable();
            $table->json('photos_json')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        // ─── 4. proposals ─────────────────────────────────────────────────────
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('lead_id');
            $table->string('title');
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->decimal('markup_pct', 8, 2)->nullable();
            $table->json('scope_json')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        // ─── 5. contracts ─────────────────────────────────────────────────────
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->string('title');
            $table->text('scope_text')->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->text('payment_terms')->nullable();
            $table->date('start_date')->nullable();
            $table->date('expected_completion')->nullable();
            $table->unsignedSmallInteger('warranty_months')->default(12);
            $table->text('exclusions')->nullable();
            $table->text('change_order_policy')->nullable();
            $table->string('status')->default('draft');
            $table->dateTime('signed_at')->nullable();
            $table->timestamps();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        // ─── 6. payment_installments ──────────────────────────────────────────
        Schema::create('payment_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->string('label');
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('receipt_ref')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ─── 7. design_versions ───────────────────────────────────────────────
        Schema::create('design_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id');
            $table->unsignedSmallInteger('version_no')->default(1);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });

        // ─── 8. client_selections ─────────────────────────────────────────────
        Schema::create('client_selections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id');
            $table->string('category')->nullable();
            $table->string('title');
            $table->json('options_json')->nullable();
            $table->string('selected_option')->nullable();
            $table->string('status')->default('pending');
            $table->date('due_date')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        // ─── 9. change_orders ─────────────────────────────────────────────────
        Schema::create('change_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price_delta', 15, 2)->nullable();
            $table->integer('days_delta')->nullable();
            $table->string('status')->default('requested');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->text('client_comment')->nullable();
            $table->timestamps();
        });

        // ─── 10. daily_site_logs ──────────────────────────────────────────────
        Schema::create('daily_site_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id');
            $table->date('log_date');
            $table->unsignedSmallInteger('workers_count')->default(0);
            $table->text('contractors_text')->nullable();
            $table->text('work_completed')->nullable();
            $table->text('materials_received')->nullable();
            $table->text('problems')->nullable();
            $table->json('photos_json')->nullable();
            $table->text('notes')->nullable();
            $table->text('tomorrow_plan')->nullable();
            $table->string('delay_reason')->nullable();
            $table->timestamps();
        });

        // ─── 11. warranty_claims ──────────────────────────────────────────────
        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id');
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->unsignedBigInteger('assigned_vendor_id')->nullable();
            $table->dateTime('visit_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });

        // ─── 12. project_audit_events ─────────────────────────────────────────
        Schema::create('project_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('project_id');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event_type');
            $table->string('summary');
            $table->json('meta_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_audit_events');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('daily_site_logs');
        Schema::dropIfExists('change_orders');
        Schema::dropIfExists('client_selections');
        Schema::dropIfExists('design_versions');
        Schema::dropIfExists('payment_installments');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('proposals');
        Schema::dropIfExists('site_visits');
        Schema::dropIfExists('leads');

        Schema::table('projects', function (Blueprint $table) {
            $cols = [
                'lifecycle_status', 'next_action', 'next_action_label_ar',
                'progress_design', 'progress_procurement', 'progress_execution', 'progress_finance',
                'contract_value', 'actual_cost', 'committed_cost', 'forecast_cost', 'execution_unlocked',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('projects', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
