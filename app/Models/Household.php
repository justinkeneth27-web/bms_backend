<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    protected $table = 'households';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'head_of_family',
        'birthday',
        'age',
        'contact_number',
        'number_of_members',
        'house_number',
        'purok',
        'barangay',
        'complete_address',
        'location',
        'status',
        'status_note',
        'evacuation_center_id',
        'verification_status',
        'special_needs',
        'family_members',
        'last_status_update',
    ];

    protected $casts = [
        'location' => 'array',
        'special_needs' => 'array',
        'family_members' => 'array',
        'birthday' => 'date',
        'age' => 'integer',
        'number_of_members' => 'integer',
        'last_status_update' => 'datetime',
    ];

    public function evacuationCenter(): BelongsTo
    {
        return $this->belongsTo(EvacuationCenter::class, 'evacuation_center_id', 'id');
    }

    public function reliefDistributions(): HasMany
    {
        return $this->hasMany(ReliefDistribution::class, 'household_id', 'id');
    }

    /**
     * Scope query to specific barangay.
     */
    public function scopeForBarangay($query, string $barangay)
    {
        return $query->where('barangay', $barangay);
    }

    /**
     * Scope query by alert status (SAFE, MODERATE, EMERGENCY).
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', strtoupper($status));
    }
}
