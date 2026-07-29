<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (PermissionName::cases() as $permissionName) {
                Permission::findOrCreate(
                    $permissionName->value,
                    'web',
                );
            }

            foreach (RoleName::cases() as $roleName) {
                $role = Role::findOrCreate(
                    $roleName->value,
                    'web',
                );

                $role->syncPermissions(
                    $roleName->permissionValues(),
                );
            }
        });

        $permissionRegistrar->forgetCachedPermissions();
    }
}
