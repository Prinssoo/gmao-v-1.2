<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Truck;
use App\Models\TruckDriverHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TruckDriverHistoryController extends Controller
{
    /**
     * Historique global des attributions
     */
    public function index(Request $request): JsonResponse
    {
        $query = TruckDriverHistory::where('site_id', $request->user()->current_site_id)
            ->with([
                'truck:id,code,registration_number,brand,model',
                'driver:id,code,first_name,last_name',
                'assignedBy:id,name',
                'unassignedBy:id,name',
            ]);

        // Filtres
        if ($request->truck_id) {
            $query->where('truck_id', $request->truck_id);
        }

        if ($request->driver_id) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->status === 'active') {
            $query->whereNull('unassigned_at');
        } elseif ($request->status === 'completed') {
            $query->whereNotNull('unassigned_at');
        }

        if ($request->from_date) {
            $query->where('assigned_at', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->where('assigned_at', '<=', $request->to_date . ' 23:59:59');
        }

        $history = $query->orderByDesc('assigned_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($history);
    }

    /**
     * Attribuer un camion à un chauffeur
     */
    public function assign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'truck_id' => 'required|exists:trucks,id',
            'driver_id' => 'required|exists:drivers,id',
            'assignment_reason' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $siteId = $request->user()->current_site_id;
        $truck = Truck::findOrFail($validated['truck_id']);
        $driver = Driver::findOrFail($validated['driver_id']);

        // Vérifier que le camion et le chauffeur appartiennent au même site
        if ($truck->site_id !== $siteId || $driver->site_id !== $siteId) {
            return response()->json(['message' => 'Ressources non autorisées'], 403);
        }

        // Terminer l'attribution actuelle du camion s'il y en a une
        $currentTruckAssignment = TruckDriverHistory::where('truck_id', $truck->id)
            ->whereNull('unassigned_at')
            ->first();

        if ($currentTruckAssignment) {
            $currentTruckAssignment->update([
                'unassigned_at' => now(),
                'end_mileage' => $truck->mileage,
                'unassignment_reason' => 'reassignment',
                'unassigned_by' => $request->user()->id,
            ]);
        }

        // Terminer l'attribution actuelle du chauffeur s'il y en a une
        $currentDriverAssignment = TruckDriverHistory::where('driver_id', $driver->id)
            ->whereNull('unassigned_at')
            ->first();

        if ($currentDriverAssignment) {
            $otherTruck = Truck::find($currentDriverAssignment->truck_id);
            $currentDriverAssignment->update([
                'unassigned_at' => now(),
                'end_mileage' => $otherTruck?->mileage,
                'unassignment_reason' => 'reassignment',
                'unassigned_by' => $request->user()->id,
            ]);

            // Libérer l'ancien camion
            if ($otherTruck) {
                $otherTruck->update([
                    'current_driver_id' => null,
                    'status' => 'available',
                ]);
            }
        }

        // Créer la nouvelle attribution
        $history = TruckDriverHistory::create([
            'site_id' => $siteId,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'assigned_at' => now(),
            'start_mileage' => $truck->mileage,
            'assignment_reason' => $validated['assignment_reason'] ?? 'regular',
            'notes' => $validated['notes'],
            'assigned_by' => $request->user()->id,
        ]);

        // Mettre à jour le camion
        $truck->update([
            'current_driver_id' => $driver->id,
            'status' => 'in_use',
        ]);

        $history->load(['truck', 'driver', 'assignedBy']);

        return response()->json([
            'message' => 'Attribution effectuée',
            'history' => $history,
        ]);
    }

    /**
     * Terminer une attribution
     */
    public function unassign(Request $request, TruckDriverHistory $history): JsonResponse
    {
        if ($history->unassigned_at) {
            return response()->json(['message' => 'Attribution déjà terminée'], 400);
        }

        $validated = $request->validate([
            'end_mileage' => 'nullable|integer|min:' . ($history->start_mileage ?? 0),
            'unassignment_reason' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        $truck = $history->truck;

        // Mettre à jour l'historique
        $history->update([
            'unassigned_at' => now(),
            'end_mileage' => $validated['end_mileage'] ?? $truck->mileage,
            'unassignment_reason' => $validated['unassignment_reason'] ?? 'end_mission',
            'notes' => $history->notes . ($validated['notes'] ? "\n" . $validated['notes'] : ''),
            'unassigned_by' => $request->user()->id,
        ]);

        // Mettre à jour le kilométrage du camion si fourni
        if (isset($validated['end_mileage'])) {
            $truck->update(['mileage' => $validated['end_mileage']]);
        }

        // Libérer le camion
        $truck->update([
            'current_driver_id' => null,
            'status' => 'available',
        ]);

        $history->load(['truck', 'driver', 'unassignedBy']);

        return response()->json([
            'message' => 'Attribution terminée',
            'history' => $history,
        ]);
    }

    /**
     * Historique d'un camion spécifique
     */
    public function truckHistory(Request $request, Truck $truck): JsonResponse
    {
        $history = TruckDriverHistory::where('truck_id', $truck->id)
            ->with([
                'driver:id,code,first_name,last_name',
                'assignedBy:id,name',
                'unassignedBy:id,name',
            ])
            ->orderByDesc('assigned_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($history);
    }

    /**
     * Historique d'un chauffeur spécifique
     */
    public function driverHistory(Request $request, Driver $driver): JsonResponse
    {
        $history = TruckDriverHistory::where('driver_id', $driver->id)
            ->with([
                'truck:id,code,registration_number,brand,model',
                'assignedBy:id,name',
                'unassignedBy:id,name',
            ])
            ->orderByDesc('assigned_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($history);
    }

    /**
     * Attributions actives
     */
    public function activeAssignments(Request $request): JsonResponse
    {
        $assignments = TruckDriverHistory::where('site_id', $request->user()->current_site_id)
            ->whereNull('unassigned_at')
            ->with([
                'truck:id,code,registration_number,brand,model,mileage',
                'driver:id,code,first_name,last_name,phone',
                'assignedBy:id,name',
            ])
            ->orderByDesc('assigned_at')
            ->get();

        return response()->json($assignments);
    }

    /**
     * Statistiques des attributions
     */
    public function stats(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;

        $totalAssignments = TruckDriverHistory::where('site_id', $siteId)->count();
        $activeAssignments = TruckDriverHistory::where('site_id', $siteId)->whereNull('unassigned_at')->count();
        
        $totalDistance = TruckDriverHistory::where('site_id', $siteId)
            ->whereNotNull('start_mileage')
            ->whereNotNull('end_mileage')
            ->selectRaw('SUM(end_mileage - start_mileage) as total')
            ->value('total') ?? 0;

        // Top chauffeurs par distance
        $topDrivers = TruckDriverHistory::where('site_id', $siteId)
            ->whereNotNull('start_mileage')
            ->whereNotNull('end_mileage')
            ->join('drivers', 'truck_driver_history.driver_id', '=', 'drivers.id')
            ->selectRaw('drivers.id, drivers.first_name, drivers.last_name, SUM(end_mileage - start_mileage) as total_distance, COUNT(*) as assignments_count')
            ->groupBy('drivers.id', 'drivers.first_name', 'drivers.last_name')
            ->orderByDesc('total_distance')
            ->limit(5)
            ->get();

        // Top camions par utilisation
        $topTrucks = TruckDriverHistory::where('truck_driver_history.site_id', $siteId)
            ->join('trucks', 'truck_driver_history.truck_id', '=', 'trucks.id')
            ->selectRaw('trucks.id, trucks.code, trucks.registration_number, COUNT(*) as assignments_count')
            ->groupBy('trucks.id', 'trucks.code', 'trucks.registration_number')
            ->orderByDesc('assignments_count')
            ->limit(5)
            ->get();

        return response()->json([
            'total_assignments' => $totalAssignments,
            'active_assignments' => $activeAssignments,
            'total_distance' => $totalDistance,
            'top_drivers' => $topDrivers,
            'top_trucks' => $topTrucks,
        ]);
    }
}
