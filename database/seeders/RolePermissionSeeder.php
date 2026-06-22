<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // General
            'view books',

            // Admin / Librarian management
            'manage authors',
            'manage categories',
            'manage books',
            'manage members',
            'manage borrowings',
            'manage reservations',
            'manage fines',
            'manage publisher requests',
            'view reports',

            // Member portal
            'view own borrowings',
            'view own fines',
            'borrow books',
            'return own books',
            'reserve books',
            'cancel own reservations',

            // Publisher / Author
            'request publisher account',
            'view own publisher request',
            'view publisher dashboard',
            'manage own books',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $librarian = Role::firstOrCreate([
            'name' => 'librarian',
            'guard_name' => 'web',
        ]);

        $member = Role::firstOrCreate([
            'name' => 'member',
            'guard_name' => 'web',
        ]);

        $publisher = Role::firstOrCreate([
            'name' => 'publisher',
            'guard_name' => 'web',
        ]);

        $admin->syncPermissions($permissions);

        $librarian->syncPermissions([
            'view books',
            'manage authors',
            'manage categories',
            'manage books',
            'manage members',
            'manage borrowings',
            'manage reservations',
            'manage fines',
            'manage publisher requests',
            'view reports',
        ]);

        $member->syncPermissions([
            'view books',
            'view own borrowings',
            'view own fines',
            'borrow books',
            'return own books',
            'reserve books',
            'cancel own reservations',
            'request publisher account',
            'view own publisher request',
        ]);

        $publisher->syncPermissions([
            'view books',
            'view publisher dashboard',
            'manage own books',
        ]);

        $adminUser = User::firstOrCreate(
            ['email' => 'mahmoud@example.com'],
            [
                'name' => 'Mahmoud',
                'password' => Hash::make('123456'),
            ]
        );

        $adminUser->assignRole('admin');
    }
}
