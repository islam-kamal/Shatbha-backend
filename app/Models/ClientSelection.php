<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSelection extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'category', 'title',
        'options_json', 'selected_option', 'status', 'due_date', 'approved_at',
    ];

    // Flutter model reads 'item_name' and 'client_note'
    protected $appends = ['item_name', 'client_note'];

    public function getItemNameAttribute(): ?string
    {
        return $this->attributes['title'] ?? null;
    }

    public function getClientNoteAttribute(): ?string
    {
        return $this->attributes['selected_option'] ?? null;
    }

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'due_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
