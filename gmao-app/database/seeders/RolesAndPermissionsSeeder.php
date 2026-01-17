<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Définir toutes les permissions par module
        $permissions = [
            // Sites
            'site:view_any',
            'site:view',
            'site:create',
            'site:edit',
            'site:delete',

            // Users
            'user:view_any',
            'user:view',
            'user:create',
            'user:update',
            'user:delete',
            'user:assign_roles',

            // Roles
            'role:view_any',
            'role:view',
            'role:create',
            'role:update',
            'role:delete',
            'role:attach_permissions',

            // Equipments
            'equipment:view_any',
            'equipment:view',
            'equipment:create',
            'equipment:update',
            'equipment:delete',

            // Locations
            'location:view_any',
            'location:view',
            'location:create',
            'location:update',
            'location:delete',

            // Work Order Requests (Demandes d'intervention)
            'workorder_request:view_any',
            'workorder_request:view',
            'workorder_request:view_own',
            'workorder_request:create',
            'workorder_request:update',
            'workorder_request:delete',
            'workorder_request:approve',

            // Work Orders (Ordres de travail)
            'workorder:view_any',
            'workorder:view',
            'workorder:view_own',
            'workorder:create',
            'workorder:update',
            'workorder:delete',
            'workorder:assign',
            'workorder:start',
            'workorder:log_time',
            'workorder:use_parts',
            'workorder:add_attachments',
            'workorder:comment',
            'workorder:close',
            'workorder:reopen',
            'workorder:approve_close',

            // Parts (Pièces détachées)
            'part:view_any',
            'part:view',
            'part:create',
            'part:update',
            'part:delete',
            'part:link_to_equipment',
            'part:unlink_from_equipment',
            'part:link_to_workorder',
            'part:manage_minmax',
            'part:manage_suppliers',

            // Stock
            'stock:view_any',
            'stock:view',
            'stock:receive',
            'stock:issue',
            'stock:transfer',
            'stock:adjust',
            'stock:inventory',
            'stock:view_valuation',

            // Reports
            'report:view_kpi',
            'report:export',

            // Settings
            'settings:manage_global',

            // Intervention Requests (Demandes d'intervention)
            'intervention_request:view_any',
            'intervention_request:view',
            'intervention_request:create',
            'intervention_request:update',
            'intervention_request:delete',
            'intervention_request:validate',
            'intervention_request:convert',

            // Preventive Maintenance
            'preventive:view_any',
            'preventive:view',
            'preventive:create',
            'preventive:update',
            'preventive:delete',
            'preventive:generate_wo',

        ];

        // Créer toutes les permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Créer les rôles et assigner les permissions

        // 1. SuperAdmin - Toutes les permissions
        $superAdmin = Role::create(['name' => 'SuperAdmin']);
        $superAdmin->givePermissionTo(Permission::all());

        // 2. AdminSite - Gestion complète d'un site
        $adminSite = Role::create(['name' => 'AdminSite']);
        $adminSite->givePermissionTo([
            // Sites (limité)
            'site:view_any',
            'site:view',
            'site:edit',
            // Users
            'user:view_any',
            'user:view',
            'user:create',
            'user:update',
            'user:delete',
            'user:assign_roles',
            // Roles (lecture seule)
            'role:view_any',
            'role:view',
            // Equipments
            'equipment:view_any',
            'equipment:view',
            'equipment:create',
            'equipment:update',
            'equipment:delete',
            // Locations
            'location:view_any',
            'location:view',
            'location:create',
            'location:update',
            'location:delete',
            // Work Order Requests
            'workorder_request:view_any',
            'workorder_request:view',
            'workorder_request:create',
            'workorder_request:update',
            'workorder_request:delete',
            'workorder_request:approve',
            // Work Orders
            'workorder:view_any',
            'workorder:view',
            'workorder:create',
            'workorder:update',
            'workorder:delete',
            'workorder:assign',
            'workorder:start',
            'workorder:log_time',
            'workorder:use_parts',
            'workorder:add_attachments',
            'workorder:comment',
            'workorder:close',
            'workorder:reopen',
            'workorder:approve_close',
            // Parts
            'part:view_any',
            'part:view',
            'part:create',
            'part:update',
            'part:delete',
            'part:link_to_equipment',
            'part:unlink_from_equipment',
            'part:link_to_workorder',
            'part:manage_minmax',
            'part:manage_suppliers',
            // Stock
            'stock:view_any',
            'stock:view',
            'stock:receive',
            'stock:issue',
            'stock:transfer',
            'stock:adjust',
            'stock:inventory',
            'stock:view_valuation',
            // Reports
            'report:view_kpi',
            'report:export',
            // Intervention Requests (Demandes d'intervention)
            'intervention_request:view_any',
            'intervention_request:view',
            'intervention_request:create',
            'intervention_request:update',
            'intervention_request:delete',
            'intervention_request:validate',
            'intervention_request:convert',
            // Preventive Maintenance
            'preventive:view_any',
            'preventive:view',
            'preventive:create',
            'preventive:update',
            'preventive:delete',
            'preventive:generate_wo',


        ]);

        // 3. Planificateur
        $planificateur = Role::create(['name' => 'Planificateur']);
        $planificateur->givePermissionTo([
            // Equipments (lecture)
            'equipment:view_any',
            'equipment:view',
            // Locations (lecture)
            'location:view_any',
            'location:view',
            // Work Order Requests
            'workorder_request:view_any',
            'workorder_request:view',
            'workorder_request:approve',
            // Work Orders
            'workorder:view_any',
            'workorder:view',
            'workorder:create',
            'workorder:update',
            'workorder:assign',
            'workorder:log_time',
            'workorder:use_parts',
            'workorder:close',
            'workorder:reopen',
            // Parts (lecture + liaison)
            'part:view_any',
            'part:view',
            'part:link_to_workorder',
            // Stock (lecture)
            'stock:view_any',
            'stock:view',
            // Reports
            'report:view_kpi',
            // Intervention Requests (Demandes d'intervention)
            'intervention_request:view_any',
            'intervention_request:view',
            'intervention_request:create',
            'intervention_request:validate',
            'intervention_request:convert',
            // Preventive Maintenance
            'preventive:view_any',
            'preventive:view',
            'preventive:create',
            'preventive:update',
            'preventive:generate_wo',
        ]);

        // 4. Technicien
        $technicien = Role::create(['name' => 'Technicien']);
        $technicien->givePermissionTo([
            // Equipments (lecture)
            'equipment:view_any',
            'equipment:view',
            // Locations (lecture)
            'location:view_any',
            'location:view',
            // Work Order Requests (créer DI + voir les siennes)
            'workorder_request:view_own',
            'workorder_request:create',
            // Work Orders (ses OT)
            'workorder:view_own',
            'workorder:view',
            'workorder:start',
            'workorder:log_time',
            'workorder:use_parts',
            'workorder:add_attachments',
            'workorder:comment',
            'workorder:close',
            // Parts (lecture)
            'part:view_any',
            'part:view',
            // Stock (sortie via OT)
            'stock:view',
            'stock:issue',
            // Intervention Requests (Demandes d'intervention)
            'intervention_request:view_any',
            'intervention_request:view',
            'intervention_request:create',
            // Preventive Maintenance
            'preventive:view_any',
            'preventive:view',
        ]);

        // 5. Magasinier
        $magasinier = Role::create(['name' => 'Magasinier']);
        $magasinier->givePermissionTo([
            // Work Orders (lecture pour préparer les kits)
            'workorder:view_any',
            'workorder:view',
            // Parts
            'part:view_any',
            'part:view',
            'part:create',
            'part:update',
            'part:link_to_equipment',
            'part:unlink_from_equipment',
            'part:manage_minmax',
            'part:manage_suppliers',
            // Stock (toutes opérations)
            'stock:view_any',
            'stock:view',
            'stock:receive',
            'stock:issue',
            'stock:transfer',
            'stock:adjust',
            'stock:inventory',
            // Reports
            'report:view_kpi',
            // Intervention Requests (Demandes d'intervention)
            'intervention_request:view_any',
            'intervention_request:view',
            'intervention_request:create',
            'intervention_request:update',
            'intervention_request:delete',
            'intervention_request:validate',
            'intervention_request:convert',
        ]);

        // 6. Lecteur
        $lecteur = Role::create(['name' => 'Lecteur']);
        $lecteur->givePermissionTo([
            'site:view_any',
            'site:view',
            'equipment:view_any',
            'equipment:view',
            'location:view_any',
            'location:view',
            'workorder_request:view_any',
            'workorder_request:view',
            'workorder:view_any',
            'workorder:view',
            'part:view_any',
            'part:view',
            'stock:view_any',
            'stock:view',
            'report:view_kpi',
            'intervention_request:view_any',
            'intervention_request:view',
            // Preventive Maintenance
            'preventive:view_any',
            'preventive:view',
        ]);
    }
}
