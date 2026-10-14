<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'timestamp',
        'action_type',
        'actor_name',
        'actor_role',
        'barangay',
        'target',
        'details',
        'is_working_hours',
        'work_shift_tag',
        'terminal',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'is_working_hours' => 'boolean',
    ];

    /**
     * Create an activity log entry with automatic timestamp and shift determination.
     */
    public static function record(
        string $actionType,
        string $actorName,
        string $actorRole,
        string $barangay,
        string $target,
        string $details,
        ?string $terminal = null
    ): self {
        $now = Carbon::now('Asia/Manila');
        $hour = $now->hour;
        // Standard working hours: 08:00 to 17:00 (8 AM - 5 PM)
        $isWorkingHours = ($hour >= 8 && $hour < 17);
        $shiftTag = $isWorkingHours
            ? 'Standard Working Hours (8:00 AM - 5:00 PM)'
            : 'After-Hours / Night Shift Operations';

        $id = sprintf(
            'LOG-%s-%s-%s',
            strtoupper(substr($barangay, 0, 3)),
            $now->format('Ymd'),
            substr(md5(uniqid('', true)), 0, 6)
        );

        return self::create([
            'id' => $id,
            'timestamp' => $now,
            'action_type' => $actionType,
            'actor_name' => $actorName,
            'actor_role' => $actorRole,
            'barangay' => $barangay,
            'target' => $target,
            'details' => $details,
            'is_working_hours' => $isWorkingHours,
            'work_shift_tag' => $shiftTag,
            'terminal' => $terminal ?? 'Main Barangay Hall Terminal',
        ]);
    }
}
