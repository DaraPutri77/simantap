<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;


class RoleSeeder extends Seeder
{
    public function run(): void
    {

        $roles = [
            'Admin',
            'Operator',
            'User',
        ];


        foreach ($roles as $role) {

            Role::firstOrCreate([
                'nama_role' => $role
            ]);

        }

    }
}