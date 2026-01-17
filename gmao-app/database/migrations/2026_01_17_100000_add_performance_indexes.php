<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Vérifie si un index existe déjà
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select(
            "SHOW INDEX FROM {$table} WHERE Key_name = ?",
            [$indexName]
        );
        return count($result) > 0;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Index sur work_orders
        Schema::table('work_orders', function (Blueprint $table) {
            if (!$this->indexExists('work_orders', 'wo_site_status_idx')) {
                $table->index(['site_id', 'status'], 'wo_site_status_idx');
            }
            if (!$this->indexExists('work_orders', 'wo_site_type_idx')) {
                $table->index(['site_id', 'type'], 'wo_site_type_idx');
            }
            if (!$this->indexExists('work_orders', 'wo_site_priority_idx')) {
                $table->index(['site_id', 'priority'], 'wo_site_priority_idx');
            }
            if (!$this->indexExists('work_orders', 'wo_scheduled_start_idx')) {
                $table->index('scheduled_start', 'wo_scheduled_start_idx');
            }
        });

        // Index sur trucks
        Schema::table('trucks', function (Blueprint $table) {
            if (!$this->indexExists('trucks', 'trucks_site_status_idx')) {
                $table->index(['site_id', 'status'], 'trucks_site_status_idx');
            }
        });

        // Index sur intervention_requests
        Schema::table('intervention_requests', function (Blueprint $table) {
            if (!$this->indexExists('intervention_requests', 'ir_site_status_idx')) {
                $table->index(['site_id', 'status'], 'ir_site_status_idx');
            }
        });

        // Index sur truck_driver_history
        Schema::table('truck_driver_history', function (Blueprint $table) {
            if (!$this->indexExists('truck_driver_history', 'tdh_site_active_idx')) {
                $table->index(['site_id', 'unassigned_at'], 'tdh_site_active_idx');
            }
        });

        // Index sur preventive_maintenances
        Schema::table('preventive_maintenances', function (Blueprint $table) {
            if (!$this->indexExists('preventive_maintenances', 'pm_site_active_idx')) {
                $table->index(['site_id', 'is_active'], 'pm_site_active_idx');
            }
            if (!$this->indexExists('preventive_maintenances', 'pm_next_execution_idx')) {
                $table->index('next_execution_date', 'pm_next_execution_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            if ($this->indexExists('work_orders', 'wo_site_status_idx')) {
                $table->dropIndex('wo_site_status_idx');
            }
            if ($this->indexExists('work_orders', 'wo_site_type_idx')) {
                $table->dropIndex('wo_site_type_idx');
            }
            if ($this->indexExists('work_orders', 'wo_site_priority_idx')) {
                $table->dropIndex('wo_site_priority_idx');
            }
            if ($this->indexExists('work_orders', 'wo_scheduled_start_idx')) {
                $table->dropIndex('wo_scheduled_start_idx');
            }
        });

        Schema::table('trucks', function (Blueprint $table) {
            if ($this->indexExists('trucks', 'trucks_site_status_idx')) {
                $table->dropIndex('trucks_site_status_idx');
            }
        });

        Schema::table('intervention_requests', function (Blueprint $table) {
            if ($this->indexExists('intervention_requests', 'ir_site_status_idx')) {
                $table->dropIndex('ir_site_status_idx');
            }
        });

        Schema::table('truck_driver_history', function (Blueprint $table) {
            if ($this->indexExists('truck_driver_history', 'tdh_site_active_idx')) {
                $table->dropIndex('tdh_site_active_idx');
            }
        });

        Schema::table('preventive_maintenances', function (Blueprint $table) {
            if ($this->indexExists('preventive_maintenances', 'pm_site_active_idx')) {
                $table->dropIndex('pm_site_active_idx');
            }
            if ($this->indexExists('preventive_maintenances', 'pm_next_execution_idx')) {
                $table->dropIndex('pm_next_execution_idx');
            }
        });
    }
};
