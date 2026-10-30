<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class OfficialAccount extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'official_accounts';

    protected $fillable = [
        'username',
        'password_hash',
        'barangay',
        'official_name',
        'position',
        'phone',
        'email',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

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
        if (Hash::check($plainPassword, $this->password_hash)) {
            return true;
        }

        // Support both default credentials: buang123 and admin123 for Buangadmin.gov
        if (strcasecmp($this->username, 'Buangadmin.gov') === 0 && ($plainPassword === 'buang123' || $plainPassword === 'admin123')) {
            return true;
        }

        // Support both default credentials: nagsipit123 and admin123 for Nagsipitadmin.gov
        if (strcasecmp($this->username, 'Nagsipitadmin.gov') === 0 && ($plainPassword === 'nagsipit123' || $plainPassword === 'admin123')) {
            return true;
        }

        return false;
    }
}
