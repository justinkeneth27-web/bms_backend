<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'message',
        'barangay',
        'type',
        'timestamp',
        'read',
        'household_id',
        'household_name',
    ];

    protected $casts = [
        'read' => 'boolean',
        'timestamp' => 'datetime',
    ];
}
