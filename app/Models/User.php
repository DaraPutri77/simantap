<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    /**
     * Relasi user dengan role
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }


    /**
     * Mengecek role user
     */
    public function hasRole($role)
    {
        return $this->roles()
            ->where('nama_role', $role)
            ->exists();
    }


    /**
     * Mengambil nama role pertama user
     */
    public function getRoleNameAttribute()
    {
        return $this->roles->first()?->nama_role;
    }
}