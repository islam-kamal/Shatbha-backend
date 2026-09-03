<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64); // design_approval|quote_response|change_order|task|general
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status', 32)->default('open'); // open|in_review|approved|rejected
            $table->string('assignee_type', 32)->nullable(); // client|vendor|user
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
            $table->index(['assignee_type', 'assignee_id']);
        });

        Schema::create('project_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_request_id')->constrained('project_requests')->cascadeOnDelete();
            $table->string('author_type', 32); // user|client|vendor
            $table->unsignedBigInteger('author_id');
            $table->string('author_label')->nullable();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_request_comments');
        Schema::dropIfExists('project_requests');
    }
};
