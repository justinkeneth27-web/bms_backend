<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReliefDistribution extends Model
{
    protected $table = 'relief_distributions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'household_id',
        'recipient_name',
        'barangay',
        'purok',
        'item',
        'qty',
        'location',
        'distributed_at',
        'officer_name',
        'notes',
    ];

    protected $casts = [
        'distributed_at' => 'datetime',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id', 'id');
    }
}
