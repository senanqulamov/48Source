<?php

namespace App\Support;

use App\Models\RolePermission;
use App\Models\User;

/**
 * Role permissions. Each role holds a set of abilities per module:
 * V view, C create, E edit, A approve. Administrators edit the table in
 * Users & roles; every page and action checks it.
 */
final class Perm
{
    public const ROLES = [
        'admin' => 'Administrator',
        'buyer' => 'Buyer',
        'supplier' => 'Supplier',
        'seller' => 'Seller',
        'market_worker' => 'Market worker',
        'supplier_worker' => 'Supplier field worker',
    ];

    public const MODULES = [
        'rfqs' => 'RFQs',
        'quotes' => 'Quotations',
        'orders' => 'Purchase orders',
        'suppliers' => 'Suppliers',
        'catalog' => 'Products & markets',
        'field' => 'Field assessments',
        'reports' => 'Reports',
        'users' => 'Users & roles',
        'system' => 'System settings',
        'requisitions' => 'Requisitions',
        'contracts' => 'Contracts',
        'invoices' => 'Invoices & receipts',
        'marketplace' => 'Marketplace orders',
        'spend' => 'Spend & savings',
    ];

    public const ABILITIES = ['V' => 'View', 'C' => 'Create', 'E' => 'Edit', 'A' => 'Approve'];

    public const DEFAULTS = [
        'admin' => ['rfqs' => 'VCEA', 'quotes' => 'VCEA', 'orders' => 'VCEA', 'suppliers' => 'VCEA', 'catalog' => 'VCEA', 'field' => 'VCEA', 'reports' => 'VCEA', 'users' => 'VCEA', 'system' => 'VCEA', 'requisitions' => 'VCEA', 'contracts' => 'VCEA', 'invoices' => 'VCEA', 'marketplace' => 'VCEA', 'spend' => 'VCEA'],
        'buyer' => ['rfqs' => 'VCE', 'quotes' => 'VA', 'orders' => 'VCE', 'suppliers' => 'VE', 'catalog' => 'V', 'field' => 'VA', 'reports' => 'V', 'requisitions' => 'VCEA', 'contracts' => 'VCE', 'invoices' => 'VCEA', 'marketplace' => 'VCE', 'spend' => 'V'],
        'supplier' => ['rfqs' => 'V', 'quotes' => 'VCE', 'orders' => 'VE', 'catalog' => 'VCE', 'field' => 'VCE', 'contracts' => 'VE', 'invoices' => 'VC', 'marketplace' => 'VCEA'],
        'seller' => ['catalog' => 'VCE', 'reports' => 'V', 'marketplace' => 'VCEA'],
        'market_worker' => ['catalog' => 'V', 'marketplace' => 'VE'],
        'supplier_worker' => ['rfqs' => 'V', 'quotes' => 'V', 'field' => 'VCE'],
    ];

    /** @var array<string, array<string, string>>|null */
    private static ?array $cache = null;

    public static function matrix(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $m = [];
        foreach (array_keys(self::ROLES) as $role) {
            foreach (array_keys(self::MODULES) as $module) {
                $m[$role][$module] = self::DEFAULTS[$role][$module] ?? '';
            }
        }
        foreach (RolePermission::all() as $row) {
            if (isset($m[$row->role][$row->module])) {
                $m[$row->role][$row->module] = $row->abilities;
            }
        }

        return self::$cache = $m;
    }

    public static function allows(?User $user, string $module, string $ability = 'V'): bool
    {
        if (! $user || $user->status !== 'active') {
            return false;
        }

        return str_contains(self::matrix()[$user->role][$module] ?? '', $ability);
    }

    public static function set(string $role, string $module, string $abilities): void
    {
        $clean = implode('', array_filter(array_keys(self::ABILITIES), fn ($a) => str_contains($abilities, $a)));
        RolePermission::updateOrCreate(['role' => $role, 'module' => $module], ['abilities' => $clean]);
        self::flush();
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
