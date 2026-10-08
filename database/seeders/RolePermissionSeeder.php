<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'stock.manage', 'movement.create', 'movement.approve',
            'sale.create', 'sale.approve', 'purchase.manage',
            'rate.update', 'ledger.view', 'ledger.manage',
            'employee.manage', 'user.manage', 'role.manage',
            'audit.view', 'discount.manage', 'referral.manage', 'customer.manage',
            'orders.manage', 'exchange.manage', 'website.manage',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $roles = [
            'owner' => $permissions, // everything

            'manager' => [
                'stock.manage', 'movement.create', 'movement.approve',
                'sale.create', 'sale.approve', 'purchase.manage',
                'discount.manage', 'audit.view', 'referral.manage', 'customer.manage',
                'orders.manage', 'exchange.manage', 'website.manage',
            ],

            'accountant' => [
                'sale.create', 'sale.approve', 'purchase.manage', 'ledger.view',
            ],

            'counter_staff' => [
                'stock.manage', 'movement.create', 'sale.create', 'customer.manage',
                'orders.manage', 'exchange.manage',
            ],

            'karigar_handler' => [
                'movement.create',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
