<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SiteController extends Controller
{
    /**
     * Liste des sites
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('site:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        $query = Site::query();

        // Recherche
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
                
                if (Schema::hasColumn('sites', 'city')) {
                    $q->orWhere('city', 'like', "%{$search}%");
                }
                if (Schema::hasColumn('sites', 'contact_name')) {
                    $q->orWhere('contact_name', 'like', "%{$search}%");
                }
            });
        }

        // Filtre statut
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filtre ville
        if ($request->city && Schema::hasColumn('sites', 'city')) {
            $query->where('city', $request->city);
        }

        // Filtre type
        if ($request->site_type && Schema::hasColumn('sites', 'site_type')) {
            $query->where('site_type', $request->site_type);
        }

        $sites = $query->orderBy('name')
            ->paginate($request->per_page ?? 20);

        return response()->json($sites);
    }

    /**
     * Créer un site
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->can('site:create')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'site_type' => 'nullable|string|in:warehouse,depot,terminal,office,client,supplier,other',
            'capacity' => 'nullable|string|max:100',
            'operating_hours' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = $this->generateSiteCode($validated['site_type'] ?? 'other');
        $validated['is_active'] = $validated['is_active'] ?? true;

        $site = Site::create($validated);

        return response()->json([
            'message' => 'Site créé avec succès',
            'site' => $site,
        ], 201);
    }

    /**
     * Afficher un site
     */
    public function show(Request $request, Site $site): JsonResponse
    {
        if (!$request->user()->can('site:view')) {
            abort(403, 'Accès non autorisé');
        }

        return response()->json($site);
    }

    /**
     * Mettre à jour un site
     */
    public function update(Request $request, Site $site): JsonResponse
    {
        if (!$request->user()->can('site:edit')) {
            abort(403, 'Accès non autorisé');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|email|max:255',
            'site_type' => 'nullable|string|in:warehouse,depot,terminal,office,client,supplier,other',
            'capacity' => 'nullable|string|max:100',
            'operating_hours' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $site->update($validated);

        return response()->json([
            'message' => 'Site mis à jour avec succès',
            'site' => $site->fresh(),
        ]);
    }

    /**
     * Supprimer un site
     */
    public function destroy(Request $request, Site $site): JsonResponse
    {
        if (!$request->user()->can('site:delete')) {
            abort(403, 'Accès non autorisé');
        }

        $site->delete();

        return response()->json([
            'message' => 'Site supprimé avec succès',
        ]);
    }

    /**
     * Activer/Désactiver un site
     */
    public function toggleActive(Request $request, Site $site): JsonResponse
    {
        if (!$request->user()->can('site:edit')) {
            abort(403, 'Accès non autorisé');
        }

        $site->update(['is_active' => !$site->is_active]);

        return response()->json([
            'message' => $site->is_active ? 'Site activé' : 'Site désactivé',
            'site' => $site,
        ]);
    }

    /**
     * Liste simplifiée pour les selects
     */
    public function list(Request $request): JsonResponse
    {
        if (!$request->user()->can('site:view_any')) {
            abort(403, 'Accès non autorisé');
        }

        $sites = Site::query()
            ->select('id', 'code', 'name', 'city', 'site_type', 'is_active')
            ->when($request->active_only, function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('name')
            ->get();

        return response()->json($sites);
    }

    /**
     * Générer un code unique
     */
    private function generateSiteCode(string $type = 'other'): string
    {
        $prefixes = [
            'warehouse' => 'WH',
            'depot' => 'DP',
            'terminal' => 'TM',
            'office' => 'OF',
            'client' => 'CL',
            'supplier' => 'SP',
            'other' => 'ST',
        ];

        $prefix = $prefixes[$type] ?? 'ST';

        $lastSite = Site::where('code', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        if ($lastSite) {
            $lastNumber = (int) preg_replace('/[^0-9]/', '', substr($lastSite->code, 2));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
