<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Profile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class DatabaseSeeder extends Seeder
{

    public function run(): void
    {

        $this->call([
            RoleSeeder::class,
        ]);


        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@simantap.com',
            'password' => Hash::make('password'),
        ]);


        $adminRole = Role::where('nama_role','Admin')->first();


        if($adminRole){

            $admin->roles()->sync([
                $adminRole->id
            ]);

        }


        Profile::create([
            'user_id' => $admin->id,
            'nama_lengkap' => 'Administrator SIMANTAP',
            'alamat' => 'Kantor SIMANTAP',
            'no_hp' => '08123456789',
        ]);

    }

}