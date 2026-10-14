<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvacuationCenter extends Model
{
    protected $table = 'evacuation_centers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'barangay',
        'purok',
        'capacity_families',
        'assigned_count',
        'location',
        'manager_name',
        'manager_contact',
        'status',
        'facilities',
    ];

    protected $casts = [
        'location' => 'array',
        'facilities' => 'array',
        'capacity_families' => 'integer',
        'assigned_count' => 'integer',
    ];

    public function households(): HasMany
    {
        return $this->hasMany(Household::class, 'evacuation_center_id', 'id');
    }

    /**
     * Recalculate assigned household count from actual registered households.
     */
    public function recalculateAssignedCount(): int
    {
        $count = $this->households()->count();
        $this->assigned_count = $count;
        if ($this->capacity_families > 0 && $count >= $this->capacity_families) {
            $this->status = 'Full';
        } elseif ($this->status === 'Full' && $count < $this->capacity_families) {
            $this->status = 'Open';
        }
        $this->save();
        return $count;
    }
}
