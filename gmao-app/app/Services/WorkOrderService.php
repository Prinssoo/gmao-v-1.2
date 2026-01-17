<?php

namespace App\Services;

use App\Models\WorkOrder;
use App\Models\User;
use App\Models\Equipment;
use App\Models\Truck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkOrderService
{
    /**
     * Créer un nouvel ordre de travail
     */
    public function create(array $data, User $user): WorkOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $workOrder = WorkOrder::create([
                'site_id' => $user->current_site_id,
                'code' => $this->generateCode($user->current_site_id),
                'asset_type' => $data['asset_type'],
                'equipment_id' => $data['equipment_id'] ?? null,
                'truck_id' => $data['truck_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'priority' => $data['priority'],
                'status' => $data['assigned_to'] ? 'assigned' : 'pending',
                'requested_by' => $user->id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'scheduled_start' => $data['scheduled_start'] ?? null,
                'scheduled_end' => $data['scheduled_end'] ?? null,
                'estimated_duration' => $data['estimated_duration'] ?? null,
            ]);

            // Mettre à jour le statut de l'équipement/camion si urgent
            if ($data['priority'] === 'urgent') {
                $this->markAssetUnderMaintenance($workOrder);
            }

            return $workOrder->load(['equipment', 'truck', 'assignedTo', 'requestedBy']);
        });
    }

    /**
     * Démarrer un ordre de travail
     */
    public function start(WorkOrder $workOrder, User $user): WorkOrder
    {
        if (!in_array($workOrder->status, ['pending', 'assigned', 'on_hold'])) {
            throw new \Exception('Cet ordre de travail ne peut pas être démarré.');
        }

        $workOrder->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'assigned_to' => $workOrder->assigned_to ?? $user->id,
        ]);

        $this->markAssetUnderMaintenance($workOrder);

        return $workOrder->fresh();
    }

    /**
     * Mettre en pause un ordre de travail
     */
    public function pause(WorkOrder $workOrder, ?string $reason = null): WorkOrder
    {
        if ($workOrder->status !== 'in_progress') {
            throw new \Exception('Seul un OT en cours peut être mis en pause.');
        }

        $workOrder->update([
            'status' => 'on_hold',
            'paused_at' => now(),
            'technician_notes' => $workOrder->technician_notes . ($reason ? "\n[Pause] $reason" : ''),
        ]);

        return $workOrder->fresh();
    }

    /**
     * Reprendre un ordre de travail
     */
    public function resume(WorkOrder $workOrder): WorkOrder
    {
        if ($workOrder->status !== 'on_hold') {
            throw new \Exception('Seul un OT en pause peut être repris.');
        }

        $workOrder->update([
            'status' => 'in_progress',
            'resumed_at' => now(),
        ]);

        return $workOrder->fresh();
    }

    /**
     * Compléter un ordre de travail
     */
    public function complete(WorkOrder $workOrder, array $data, User $user): WorkOrder
    {
        if (!in_array($workOrder->status, ['in_progress', 'assigned'])) {
            throw new \Exception('Cet ordre de travail ne peut pas être complété.');
        }

        return DB::transaction(function () use ($workOrder, $data, $user) {
            // Calculer la durée réelle
            $actualDuration = null;
            if ($workOrder->started_at) {
                $actualDuration = now()->diffInMinutes($workOrder->started_at);
            }

            $workOrder->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $user->id,
                'actual_duration' => $actualDuration,
                'work_performed' => $data['work_performed'],
                'root_cause' => $data['root_cause'] ?? null,
                'diagnosis' => $data['diagnosis'] ?? null,
                'technician_notes' => $data['technician_notes'] ?? $workOrder->technician_notes,
                'mileage_at_intervention' => $data['mileage_at_intervention'] ?? null,
            ]);

            // Mettre à jour le kilométrage du camion si applicable
            if (isset($data['mileage_at_intervention']) && $workOrder->truck_id) {
                $workOrder->truck->update(['mileage' => $data['mileage_at_intervention']]);
            }

            // Remettre l'actif en service
            $this->markAssetOperational($workOrder);

            return $workOrder->fresh(['equipment', 'truck', 'assignedTo', 'completedBy']);
        });
    }

    /**
     * Annuler un ordre de travail
     */
    public function cancel(WorkOrder $workOrder, string $reason, User $user): WorkOrder
    {
        if ($workOrder->status === 'completed') {
            throw new \Exception('Un OT complété ne peut pas être annulé.');
        }

        $workOrder->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'technician_notes' => $workOrder->technician_notes . "\n[Annulé par {$user->name}] $reason",
        ]);

        // Remettre l'actif en service si nécessaire
        $this->markAssetOperational($workOrder);

        return $workOrder->fresh();
    }

    /**
     * Assigner un technicien
     */
    public function assign(WorkOrder $workOrder, int $userId): WorkOrder
    {
        $workOrder->update([
            'assigned_to' => $userId,
            'status' => $workOrder->status === 'pending' ? 'assigned' : $workOrder->status,
        ]);

        return $workOrder->fresh(['assignedTo']);
    }

    /**
     * Générer un code unique pour l'OT
     */
    protected function generateCode(int $siteId): string
    {
        $prefix = 'OT';
        $date = now()->format('Ymd');
        $count = WorkOrder::where('site_id', $siteId)
            ->whereDate('created_at', today())
            ->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $count);
    }

    /**
     * Marquer l'actif comme en maintenance
     */
    protected function markAssetUnderMaintenance(WorkOrder $workOrder): void
    {
        if ($workOrder->equipment_id) {
            $workOrder->equipment?->update(['status' => 'under_maintenance']);
        } elseif ($workOrder->truck_id) {
            $workOrder->truck?->update(['status' => 'maintenance']);
        }
    }

    /**
     * Remettre l'actif en service
     */
    protected function markAssetOperational(WorkOrder $workOrder): void
    {
        // Vérifier s'il n'y a pas d'autres OT actifs sur cet actif
        $hasOtherActiveWorkOrders = WorkOrder::where('id', '!=', $workOrder->id)
            ->where(function ($q) use ($workOrder) {
                $q->where('equipment_id', $workOrder->equipment_id)
                  ->orWhere('truck_id', $workOrder->truck_id);
            })
            ->whereIn('status', ['in_progress', 'on_hold'])
            ->exists();

        if (!$hasOtherActiveWorkOrders) {
            if ($workOrder->equipment_id) {
                $workOrder->equipment?->update(['status' => 'operational']);
            } elseif ($workOrder->truck_id) {
                $workOrder->truck?->update(['status' => 'available']);
            }
        }
    }

    /**
     * Calculer les statistiques des OT
     */
    public function getStats(int $siteId): array
    {
        $query = WorkOrder::where('site_id', $siteId);

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'on_hold' => (clone $query)->where('status', 'on_hold')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            'urgent' => (clone $query)->where('priority', 'urgent')
                ->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'overdue' => (clone $query)->where('scheduled_end', '<', now())
                ->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'completed_this_month' => (clone $query)->where('status', 'completed')
                ->whereMonth('completed_at', now()->month)->count(),
            'avg_completion_time' => (clone $query)->where('status', 'completed')
                ->whereNotNull('actual_duration')
                ->avg('actual_duration'),
        ];
    }
}
