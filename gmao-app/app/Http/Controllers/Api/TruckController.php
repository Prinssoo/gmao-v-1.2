<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Truck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TruckController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Truck::where('site_id', $request->user()->current_site_id)
            ->with('currentDriver:id,code,first_name,last_name');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('registration_number', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('brand', 'like', "%{$request->search}%")
                  ->orWhere('model', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        $trucks = $query->orderBy('code')->paginate($request->per_page ?? 15);

        return response()->json($trucks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:50',
            'registration_number' => 'required|string|max:50',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'type' => 'nullable|string|max:100',
            'capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'in:tonnes,m3,litres',
            'fuel_type' => 'nullable|string|max:50',
            'mileage' => 'nullable|integer|min:0',
            'status' => 'in:available,in_use,maintenance,out_of_service',
            'registration_date' => 'nullable|date',
            'insurance_expiry_date' => 'nullable|date',
            'technical_inspection_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'current_driver_id' => 'nullable|exists:drivers,id',
        ]);

        $siteId = $request->user()->current_site_id;
        $validated['site_id'] = $siteId;
        $validated['code'] = Truck::generateCode($siteId);
        $validated['created_by'] = $request->user()->id;

        $truck = Truck::create($validated);

        return response()->json([
            'message' => 'Camion créé avec succès',
            'truck' => $truck,
        ], 201);
    }

    public function show(Truck $truck): JsonResponse
    {
        $truck->load(['currentDriver', 'createdBy:id,name']);

        return response()->json($truck);
    }

    public function update(Request $request, Truck $truck): JsonResponse
    {
        $validated = $request->validate([
            'registration_number' => 'required|string|max:50',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'type' => 'nullable|string|max:100',
            'capacity' => 'nullable|numeric|min:0',
            'capacity_unit' => 'in:tonnes,m3,litres',
            'fuel_type' => 'nullable|string|max:50',
            'mileage' => 'nullable|integer|min:0',
            'status' => 'in:available,in_use,maintenance,out_of_service',
            'registration_date' => 'nullable|date',
            'insurance_expiry_date' => 'nullable|date',
            'technical_inspection_date' => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'current_driver_id' => 'nullable|exists:drivers,id',
        ]);

        $truck->update($validated);

        return response()->json([
            'message' => 'Camion mis à jour',
            'truck' => $truck,
        ]);
    }

    public function destroy(Truck $truck): JsonResponse
    {
        $truck->delete();

        return response()->json(['message' => 'Camion supprimé']);
    }

    public function list(Request $request): JsonResponse
    {
        $trucks = Truck::where('site_id', $request->user()->current_site_id)
            ->whereIn('status', ['available', 'in_use'])
            ->orderBy('code')
            ->get(['id', 'code', 'registration_number', 'brand', 'model', 'status']);

        return response()->json($trucks);
    }

    public function assignDriver(Request $request, Truck $truck): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
        ]);

        // Libérer l'ancien camion du chauffeur si nécessaire
        if ($validated['driver_id']) {
            Truck::where('current_driver_id', $validated['driver_id'])
                ->where('id', '!=', $truck->id)
                ->update(['current_driver_id' => null]);
        }

        $truck->update([
            'current_driver_id' => $validated['driver_id'],
            'status' => $validated['driver_id'] ? 'in_use' : 'available',
        ]);

        $truck->load('currentDriver');

        return response()->json([
            'message' => $validated['driver_id'] ? 'Chauffeur assigné' : 'Chauffeur retiré',
            'truck' => $truck,
        ]);
    }

    public function updateMileage(Request $request, Truck $truck): JsonResponse
    {
        $validated = $request->validate([
            'mileage' => 'required|integer|min:' . $truck->mileage,
        ]);

        $truck->update(['mileage' => $validated['mileage']]);

        return response()->json([
            'message' => 'Kilométrage mis à jour',
            'truck' => $truck,
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $trucks = Truck::where('site_id', $request->user()->current_site_id)
            ->where(function ($q) {
                $q->where('insurance_expiry_date', '<=', now()->addDays(30))
                  ->orWhere('technical_inspection_date', '<=', now()->addDays(30))
                  ->orWhere('next_maintenance_date', '<=', now());
            })
            ->with('currentDriver:id,first_name,last_name')
            ->get();

        return response()->json($trucks);
    }

    public function types(Request $request): JsonResponse
    {
        $types = Truck::where('site_id', $request->user()->current_site_id)
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type');

        return response()->json($types);
    }
}
