<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangayConfig extends Model
{
    protected $table = 'barangay_configs';

    protected $fillable = [
        'name',
        'center_lat',
        'center_lng',
        'puroks',
        'hotline',
        'evacuation_lead',
        'alternate_hotline',
        'emergency_radio_channel',
    ];

    protected $casts = [
        'puroks' => 'array',
        'center_lat' => 'float',
        'center_lng' => 'float',
    ];
}
