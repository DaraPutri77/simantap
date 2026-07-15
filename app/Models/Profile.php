<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;


class Profile extends Model
{

    protected $fillable=[
        'user_id',
        'nama_lengkap',
        'foto',
        'alamat',
        'no_hp'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

}