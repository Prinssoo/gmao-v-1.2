<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\InterventionRequest;
use App\Models\Notification;
use App\Models\Part;
use App\Models\PreventiveMaintenance;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Données complètes du dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        $userId = $request->user()->id;

        // Cache de 60 secondes pour les données lourdes
        $cacheKey = "dashboard_{$siteId}";

        $data = Cache::remember($cacheKey, 60, function () use ($siteId) {
            return [
                'kpis' => $this->getKPIs($siteId),
                'work_orders' => $this->getWorkOrdersStats($siteId),
                'equipment_status' => $this->getEquipmentStatus($siteId),
                'upcoming_maintenance' => $this->getUpcomingMaintenance($siteId),
                'team_performance' => $this->getTeamPerformance($siteId),
                'monthly_trend' => $this->getMonthlyTrend($siteId),
            ];
        });

        // Données temps réel (non cachées)
        $data['recent_activities'] = $this->getRecentActivities($siteId);
        $data['urgent_items'] = $this->getUrgentItems($siteId);
        $data['notifications'] = $this->getRecentNotifications($siteId, $userId);

        return response()->json($data);
    }

    public function refresh(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;
        Cache::forget("dashboard_{$siteId}");

        return $this->index($request);
    }

    /**
     * KPIs principaux avec tendances
     */
    protected function getKPIs(int $siteId): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $lastMonthStart = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // Ce mois
        $woThisMonth = WorkOrder::where('site_id', $siteId)
            ->whereBetween('created_at', [$startOfMonth, $now])
            ->count();

        $woCompletedThisMonth = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfMonth, $now])
            ->count();

        // Mois dernier
        $woLastMonth = WorkOrder::where('site_id', $siteId)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        $woCompletedLastMonth = WorkOrder::where('site_id', $siteId)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$lastMonthStart, $lastMonthEnd])
            ->count();

        // Équipements
        $totalEquipments = Equipment::where('site_id', $siteId)
            ->whereNotIn('status', ['retired', 'disposed'])
            ->count();

        $operationalEquipments = Equipment::where('site_id', $siteId)
            ->where('status', 'operational')
            ->count();

        // OT en cours
        $pendingWO = WorkOrder::where('site_id', $siteId)
            ->whereIn('status', ['pending', 'approved', 'in_progress', 'on_hold'])
            ->count();

        // Demandes en attente
        $pendingDI = InterventionRequest::where('site_id', $siteId)
            ->whereIn('status', ['submitted', 'under_review'])
            ->count();

        // Stock critique
        $criticalStock = Part::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereRaw('quantity_in_stock <= minimum_stock')
            ->count();

        // Calcul des tendances
        $woTrend = $woLastMonth > 0 ? round((($woThisMonth - $woLastMonth) / $woLastMonth) * 100, 1) : 0;
        $completedTrend = $woCompletedLastMonth > 0 ? round((($woCompletedThisMonth - $woCompletedLastMonth) / $woCompletedLastMonth) * 100, 1) : 0;

        return [
            [
                'key' => 'pending_wo',
                'label' => 'OT en cours',
                'value' => $pendingWO,
                'icon' => '🔧',
                'color' => 'blue',
                'trend' => null,
            ],
            [
                'key' => 'completed_wo',
                'label' => 'OT terminés (mois)',
                'value' => $woCompletedThisMonth,
                'icon' => '✅',
                'color' => 'green',
                'trend' => $completedTrend,
            ],
            [
                'key' => 'availability',
                'label' => 'Disponibilité',
                'value' => $totalEquipments > 0 ? round(($operationalEquipments / $totalEquipments) * 100, 1) : 100,
                'suffix' => '%',
                'icon' => '⚡',
                'color' => 'purple',
                'trend' => null,
            ],
            [
                'key' => 'pending_di',
                'label' => 'DI en attente',
                'value' => $pendingDI,
                'icon' => '📋',
                'color' => 'orange',
                'trend' => null,
            ],
            [
                'key' => 'critical_stock',
                'label' => 'Stock critique',
                'value' => $criticalStock,
                'icon' => '⚠️',
                'color' => $criticalStock > 0 ? 'red' : 'green',
                'trend' => null,
            ],
            [
                'key' => 'equipments',
                'label' => 'Équipements actifs',
                'value' => $operationalEquipments,
                'suffix' => '/' . $totalEquipments,
                'icon' => '⚙️',
                'color' => 'teal',
                'trend' => null,
            ],
        ];
    }

    /**
     * Statistiques OT par statut
     */
    protected function getWorkOrdersStats(int $siteId): array
    {
        $stats = WorkOrder::where('site_id', $siteId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->count])
            ->toArray();

        $labels = [
            'pending' => ['label' => 'En attente', 'color' => '#f39c12'],
            'approved' => ['label' => 'Approuvé', 'color' => '#3498db'],
            'in_progress' => ['label' => 'En cours', 'color' => '#9b59b6'],
            'on_hold' => ['label' => 'En pause', 'color' => '#95a5a6'],
            'completed' => ['label' => 'Terminé', 'color' => '#27ae60'],
            'cancelled' => ['label' => 'Annulé', 'color' => '#e74c3c'],
        ];

        $result = [];
        foreach ($labels as $key => $meta) {
            $result[] = [
                'status' => $key,
                'label' => $meta['label'],
                'count' => $stats[$key] ?? 0,
                'color' => $meta['color'],
            ];
        }

        return $result;
    }

    /**
     * Statut des équipements
     */
    protected function getEquipmentStatus(int $siteId): array
    {
        $stats = Equipment::where('site_id', $siteId)
            ->whereNotIn('status', ['retired', 'disposed'])
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn($item) => [$item->status => $item->count])
            ->toArray();

        $labels = [
            'operational' => ['label' => 'Opérationnel', 'color' => '#27ae60', 'icon' => '✅'],
            'under_maintenance' => ['label' => 'En maintenance', 'color' => '#3498db', 'icon' => '🔧'],
            'stopped' => ['label' => 'Arrêté', 'color' => '#f39c12', 'icon' => '⏸️'],
            'broken' => ['label' => 'En panne', 'color' => '#e74c3c', 'icon' => '❌'],
        ];

        $result = [];
        foreach ($labels as $key => $meta) {
            if (isset($stats[$key]) && $stats[$key] > 0) {
                $result[] = [
                    'status' => $key,
                    'label' => $meta['label'],
                    'count' => $stats[$key],
                    'color' => $meta['color'],
                    'icon' => $meta['icon'],
                ];
            }
        }

        return $result;
    }

    /**
     * Maintenances à venir (7 prochains jours)
     */
    /**
     * Maintenances à venir (7 prochains jours)
     */
    protected function getUpcomingMaintenance(int $siteId): array
    {
        $preventive = PreventiveMaintenance::where('site_id', $siteId)
            ->where('is_active', true)
            ->whereBetween('next_execution_date', [now(), now()->addDays(7)])
            ->with('equipment:id,name,code')
            ->orderBy('next_execution_date')
            ->limit(5)
            ->get()
            ->map(fn($pm) => [
                'id' => $pm->id,
                'title' => $pm->name,
                'equipment' => $pm->equipment?->name,
                'equipment_code' => $pm->equipment?->code,
                'due_date' => $pm->next_execution_date?->format('Y-m-d'),
                'due_date_formatted' => $pm->next_execution_date?->format('d/m'),
                'days_until' => $pm->next_execution_date ? now()->diffInDays($pm->next_execution_date, false) : 0,
                'type' => 'preventive',
            ]);

        $scheduled = WorkOrder::where('site_id', $siteId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotNull('scheduled_start')
            ->whereBetween('scheduled_start', [now(), now()->addDays(7)])
            ->with('equipment:id,name,code')
            ->orderBy('scheduled_start')
            ->limit(5)
            ->get()
            ->map(fn($wo) => [
                'id' => $wo->id,
                'title' => $wo->title,
                'code' => $wo->code,
                'equipment' => $wo->equipment?->name,
                'equipment_code' => $wo->equipment?->code,
                'due_date' => $wo->scheduled_start->format('Y-m-d'),
                'due_date_formatted' => $wo->scheduled_start->format('d/m'),
                'days_until' => now()->diffInDays($wo->scheduled_start, false),
                'type' => 'work_order',
                'priority' => $wo->priority,
            ]);

        return $preventive->concat($scheduled)
            ->sortBy('due_date')
            ->values()
            ->take(8)
            ->toArray();
    }


    /**
     * Activités récentes
     */
    protected function getRecentActivities(int $siteId): array
    {
        $activities = [];

        // OT récents
        $recentWO = WorkOrder::where('site_id', $siteId)
            ->with(['assignedTo:id,name', 'equipment:id,name'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        foreach ($recentWO as $wo) {
            $activities[] = [
                'type' => 'work_order',
                'icon' => $this->getWOStatusIcon($wo->status),
                'title' => $wo->code,
                'description' => $this->getWOActivityDescription($wo),
                'time' => $wo->updated_at->diffForHumans(),
                'timestamp' => $wo->updated_at,
                'link' => "/work-orders/{$wo->id}",
                'color' => $this->getWOStatusColor($wo->status),
            ];
        }

        // DI récentes
        $recentDI = InterventionRequest::where('site_id', $siteId)
            ->with(['requestedBy:id,name', 'equipment:id,name'])
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        foreach ($recentDI as $di) {
            $activities[] = [
                'type' => 'intervention_request',
                'icon' => '📋',
                'title' => $di->code,
                'description' => "Demande: {$di->title}",
                'time' => $di->updated_at->diffForHumans(),
                'timestamp' => $di->updated_at,
                'link' => "/intervention-requests/{$di->id}",
                'color' => 'orange',
            ];
        }

        // Trier par date et limiter
        usort($activities, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($activities, 0, 8);
    }

    /**
     * Éléments urgents
     */
    /**
     * Éléments urgents
     */
    protected function getUrgentItems(int $siteId): array
    {
        $items = [];

        // OT en retard
        $overdueWO = WorkOrder::where('site_id', $siteId)
            ->whereIn('status', ['pending', 'approved', 'in_progress'])
            ->whereNotNull('scheduled_end')
            ->where('scheduled_end', '<', now())
            ->with(['equipment:id,name', 'assignedTo:id,name'])
            ->orderBy('scheduled_end')
            ->limit(5)
            ->get();

        foreach ($overdueWO as $wo) {
            $daysLate = Carbon::parse($wo->scheduled_end)->diffInDays(now());

            $items[] = [
                'type' => 'overdue_wo',
                'icon' => '🚨',
                'title' => $wo->code,
                'subtitle' => $wo->equipment?->name,
                'description' => "En retard de {$daysLate} jour(s)",
                'priority' => $wo->priority,
                'link' => "/work-orders/{$wo->id}",
                'urgency' => 'high',
            ];
        }

        // OT haute priorité non assignés
        $urgentUnassigned = WorkOrder::where('site_id', $siteId)
            ->whereIn('status', ['pending', 'approved'])
            ->whereIn('priority', ['urgent', 'high'])
            ->whereNull('assigned_to')
            ->with('equipment:id,name')
            ->limit(3)
            ->get();

        foreach ($urgentUnassigned as $wo) {
            $items[] = [
                'type' => 'unassigned_wo',
                'icon' => '👤',
                'title' => $wo->code,
                'subtitle' => $wo->equipment?->name,
                'description' => 'Non assigné - Priorité ' . $wo->priority,
                'priority' => $wo->priority,
                'link' => "/work-orders/{$wo->id}",
                'urgency' => 'medium',
            ];
        }

        // Équipements en panne
        $brokenEquipments = Equipment::where('site_id', $siteId)
            ->whereIn('status', ['broken', 'stopped'])
            ->limit(3)
            ->get();

        foreach ($brokenEquipments as $eq) {
            $items[] = [
                'type' => 'equipment_down',
                'icon' => '⚙️',
                'title' => $eq->name,
                'subtitle' => $eq->code,
                'description' => $eq->status === 'broken' ? 'En panne' : 'Arrêté',
                'link' => "/equipments/{$eq->id}",
                'urgency' => $eq->status === 'broken' ? 'high' : 'medium',
            ];
        }

        return array_slice($items, 0, 6);
    }


    /**
     * Performance de l'équipe (ce mois)
     */
    protected function getTeamPerformance(int $siteId): array
    {
        $startOfMonth = now()->startOfMonth();

        return WorkOrder::where('work_orders.site_id', $siteId)
            ->where('work_orders.status', 'completed')
            ->whereBetween('work_orders.completed_at', [$startOfMonth, now()])
            ->whereNotNull('work_orders.assigned_to')
            ->join('users', 'work_orders.assigned_to', '=', 'users.id')
            ->selectRaw('users.id, users.name, COUNT(*) as completed_count')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('completed_count')
            ->limit(5)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'initials' => $this->getInitials($item->name),
                'completed' => $item->completed_count,
            ])
            ->toArray();
    }

    /**
     * Tendance mensuelle (6 derniers mois)
     */
    protected function getMonthlyTrend(int $siteId): array
    {
        $data = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            $created = WorkOrder::where('site_id', $siteId)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $completed = WorkOrder::where('site_id', $siteId)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$start, $end])
                ->count();

            $data[] = [
                'month' => $date->format('M'),
                'month_full' => $date->translatedFormat('F Y'),
                'created' => $created,
                'completed' => $completed,
            ];
        }

        return $data;
    }

    /**
     * Notifications récentes
     */
    protected function getRecentNotifications(int $siteId, int $userId): array
    {
        return Notification::where('site_id', $siteId)
            ->where(function ($q) use ($userId) {
                $q->whereNull('user_id')->orWhere('user_id', $userId);
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($n) => [
                'id' => $n->id,
                'icon' => $n->icon,
                'title' => $n->title,
                'message' => $n->message,
                'color' => $n->color,
                'time' => $n->created_at->diffForHumans(),
                'is_read' => $n->read_at !== null,
                'link' => $n->link,
            ])
            ->toArray();
    }

    // Helpers
    protected function getWOStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => '⏳',
            'approved' => '✓',
            'in_progress' => '🔧',
            'on_hold' => '⏸️',
            'completed' => '✅',
            'cancelled' => '❌',
            default => '📋',
        };
    }

    protected function getWOStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'orange',
            'approved' => 'blue',
            'in_progress' => 'purple',
            'on_hold' => 'gray',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    protected function getWOActivityDescription(WorkOrder $wo): string
    {
        return match ($wo->status) {
            'completed' => "Terminé par {$wo->assignedTo?->name}",
            'in_progress' => "En cours - {$wo->equipment?->name}",
            'approved' => "Approuvé - {$wo->title}",
            'pending' => "Nouvelle demande - {$wo->title}",
            default => $wo->title,
        };
    }

    protected function getInitials(string $name): string
    {
        $parts = explode(' ', $name);
        $initials = '';
        foreach ($parts as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return substr($initials, 0, 2);
    }
}
