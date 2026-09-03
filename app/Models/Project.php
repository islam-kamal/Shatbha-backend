<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'title',
        'site_address',
        'status',
        'design_status',
        'design_approved_at',
        'design_submitted_at',
        'design_reject_reason',
        'start_date',
        'end_date',
        'budget_planned',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget_planned' => 'decimal:2',
            'design_approved_at' => 'datetime',
            'design_submitted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function materialLines(): HasMany
    {
        return $this->hasMany(ProjectMaterialLine::class);
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function designBoards(): HasMany
    {
        return $this->hasMany(DesignBoard::class);
    }

    public function floorPlans(): HasMany
    {
        return $this->hasMany(DesignPlan::class);
    }

    public function designPlans(): HasMany
    {
        return $this->hasMany(DesignPlan::class);
    }

    public function boqLines(): HasMany
    {
        return $this->hasMany(BoqLine::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class);
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function deliveryMilestones(): HasMany
    {
        return $this->hasMany(DeliveryMilestone::class);
    }

    public function snagItems(): HasMany
    {
        return $this->hasMany(SnagItem::class);
    }

    public function handoverChecklists(): HasMany
    {
        return $this->hasMany(HandoverChecklist::class);
    }

    public function signOffs(): HasMany
    {
        return $this->hasMany(SignOff::class);
    }

    public function contractorJobs(): HasMany
    {
        return $this->hasMany(ContractorJob::class);
    }
}
