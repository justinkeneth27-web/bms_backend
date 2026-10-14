<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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
        'security_question',
        'security_answer',
    ];

    protected $hidden = [
        'password_hash',
        'security_answer',
        'remember_token',
    ];

    /**
     * Check if plain password matches (or hashed bcrypt password).
     */
    public function verifyPassword(string $plainPassword): bool
    {
        if (password_verify($plainPassword, $this->password_hash)) {
            return true;
        }
        // Fallback for development plain-text seeders
        return $this->password_hash === $plainPassword;
    }
}
