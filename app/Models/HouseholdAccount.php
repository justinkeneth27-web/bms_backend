<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class HouseholdAccount extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'household_accounts';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'household_id',
        'email',
        'password_hash',
        'head_of_family',
        'barangay',
        'contact_number',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * Associated household record.
     */
    public function household()
    {
        return $this->belongsTo(Household::class, 'household_id', 'id');
    }

    /**
     * Get the name of the password attribute for the user.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Verify credentials strictly against the hashed backend database password.
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return Hash::check($plainPassword, $this->password_hash);
    }
}
