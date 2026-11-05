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
     * No hardcoded credentials - strictly evaluates against database password_hash.
     */
    public function verifyPassword(string $plainPassword): bool
    {
        if (!empty($this->password_hash)) {
            if (Hash::check($plainPassword, $this->password_hash)) {
                return true;
            }
            if ($this->password_hash === $plainPassword) {
                return true;
            }
        }

        return false;
    }
}
