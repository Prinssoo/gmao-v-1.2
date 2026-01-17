<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Index sur work_orders pour améliorer les requêtes par site et statut
        Schema::table('work_orders', function (Blueprint $table) {
            $table->index(['site_id', 'status'], 'wo_site_status_idx');
            $table->index(['site_id', 'type'], 'wo_site_type_idx');
            $table->index(['site_id', 'priority'], 'wo_site_priority_idx');
            $table->index('scheduled_start', 'wo_scheduled_start_idx');
        });

        // Index sur trucks pour améliorer les requêtes par site et statut
        Schema::table('trucks', function (Blueprint $table) {
            $table->index(['site_id', 'status'], 'trucks_site_status_idx');
        });

        // Index sur intervention_requests
        Schema::table('intervention_requests', function (Blueprint $table) {
            $table->index(['site_id', 'status'], 'ir_site_status_idx');
        });

        // Index sur truck_driver_history pour les attributions actives
        Schema::table('truck_driver_history', function (Blueprint $table) {
            $table->index(['site_id', 'unassigned_at'], 'tdh_site_active_idx');
        });

        // Index sur preventive_maintenances
        Schema::table('preventive_maintenances', function (Blueprint $table) {
            $table->index(['site_id', 'is_active'], 'pm_site_active_idx');
            $table->index('next_execution_date', 'pm_next_execution_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex('wo_site_status_idx');
            $table->dropIndex('wo_site_type_idx');
            $table->dropIndex('wo_site_priority_idx');
            $table->dropIndex('wo_scheduled_start_idx');
        });

        Schema::table('trucks', function (Blueprint $table) {
            $table->dropIndex('trucks_site_status_idx');
        });

        Schema::table('intervention_requests', function (Blueprint $table) {
            $table->dropIndex('ir_site_status_idx');
        });

        Schema::table('truck_driver_history', function (Blueprint $table) {
            $table->dropIndex('tdh_site_active_idx');
        });

        Schema::table('preventive_maintenances', function (Blueprint $table) {
            $table->dropIndex('pm_site_active_idx');
            $table->dropIndex('pm_next_execution_idx');
        });
    }
};
