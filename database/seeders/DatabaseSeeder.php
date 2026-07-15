<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Profile;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
        ]);

        $admin = User::updateOrCreate(
            [
                'email' => 'admin@simantap.com'
            ],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin12345'),
            ]
        );


        Profile::updateOrCreate(
            [
                'user_id' => $admin->id
            ],
            [
                'nama_lengkap' => 'Administrator SIMANTAP',
                'alamat' => 'Kantor Utama',
                'no_hp' => '08123456789',
            ]
        );


        $adminRole = Role::where('nama_role', 'Admin')->first();

        if ($adminRole) {
            $admin->roles()->sync([
                $adminRole->id
            ]);
        }
    }
}