<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Group permissions
            'groups.view',
            'groups.create',
            'groups.update',
            'groups.delete',
            'groups.join',
            'groups.leave',
            
            // Group member permissions
            'groups.members.view',
            'groups.members.add',
            'groups.members.remove',
            'groups.members.update',
            
            // Turn permissions
            'turns.view',
            'turns.create',
            'turns.update',
            'turns.delete',
            'turns.assign',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles
        $memberRole = Role::create(['name' => 'member']);
        $adminRole = Role::create(['name' => 'admin']);

        // Assign permissions to roles
        $memberRole->givePermissionTo([
            'groups.view',
            'groups.create',
            'groups.join',
            'groups.leave',
            'groups.members.view',
            'turns.view',
        ]);

        $adminRole->givePermissionTo(Permission::all());
    }
}
