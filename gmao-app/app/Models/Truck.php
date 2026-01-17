<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class Truck extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'site_id',
        'code',
        'numero',
        'registration_number',
        'brand',
        'model',
        'year',
        'vin',
        'fuel_type',
        'transmission',
        'engine_power',
        'payload_capacity',
        'gross_weight',
        'mileage',
        'fuel_tank_capacity',
        'average_consumption',
        'status',
        'acquisition_date',
        'acquisition_cost',
        'insurance_expiry',
        'technical_inspection_expiry',
        'last_maintenance_date',
        'last_maintenance_mileage',
        'next_maintenance_mileage',
        'notes',
        'photo',
        'is_active',
        'current_driver_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'engine_power' => 'integer',
        'payload_capacity' => 'decimal:2',
        'gross_weight' => 'decimal:2',
        'mileage' => 'integer',
        'fuel_tank_capacity' => 'decimal:2',
        'average_consumption' => 'decimal:2',
        'acquisition_date' => 'date',
        'acquisition_cost' => 'decimal:2',
        'insurance_expiry' => 'date',
        'technical_inspection_expiry' => 'date',
        'last_maintenance_date' => 'date',
        'last_maintenance_mileage' => 'integer',
        'next_maintenance_mileage' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'status_label',
        'fuel_type_label',
        'display_name',
        'alerts',
        'pending_maintenances_count',
    ];

    // ===== RELATIONS =====

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function currentDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'current_driver_id');
    }

    public function driverHistory(): HasMany
    {
        return $this->hasMany(TruckDriverHistory::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function interventionRequests(): HasMany
    {
        return $this->hasMany(InterventionRequest::class);
    }

    public function preventiveMaintenances(): HasMany
    {
        return $this->hasMany(PreventiveMaintenance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TruckDocument::class);
    }

    public function fuelLogs(): HasMany
    {
        return $this->hasMany(TruckFuelLog::class);
    }

    // ===== ACCESSORS =====

    public function getDisplayNameAttribute(): string
    {
        return $this->registration_number . ' - ' . $this->brand . ' ' . $this->model;
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Actif',
            'maintenance' => 'En maintenance',
            'repair' => 'En réparation',
            'out_of_service' => 'Hors service',
            'reserved' => 'Réservé',
        ];

        return $labels[$this->status] ?? 'Inconnu';
    }

    public function getFuelTypeLabelAttribute(): string
    {
        $labels = [
            'diesel' => 'Diesel',
            'gasoline' => 'Essence',
            'electric' => 'Électrique',
            'hybrid' => 'Hybride',
            'lpg' => 'GPL',
            'cng' => 'GNV',
        ];

        return $labels[$this->fuel_type] ?? 'Inconnu';
    }

    /**
     * Nombre de maintenances préventives en attente
     * Corrigé pour utiliser les bonnes colonnes
     */
    public function getPendingMaintenancesCountAttribute(): int
    {
        try {
            // Vérifier si la relation existe et si la table a les bonnes colonnes
            return $this->preventiveMaintenances()
                ->where('is_active', true)
                ->where(function ($query) {
                    // Utiliser next_execution_date qui existe dans la table
                    $query->whereDate('next_execution_date', '<=', now())
                        ->orWhere(function ($q) {
                            // Pour les maintenances basées sur le kilométrage
                            $q->where('frequency_type', 'counter')
                              ->whereColumn('counter_threshold', '<=', 'trucks.mileage');
                        });
                })
                ->count();
        } catch (\Exception $e) {
            // Si erreur (table non migrée, colonnes manquantes), retourner 0
            return 0;
        }
    }

    /**
     * Alertes du camion
     */
    public function getAlertsAttribute(): array
    {
        $alerts = [];

        // Alerte assurance
        if ($this->insurance_expiry) {
            $daysUntilExpiry = now()->diffInDays($this->insurance_expiry, false);
            if ($daysUntilExpiry < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Assurance expirée depuis ' . abs($daysUntilExpiry) . ' jours',
                    'category' => 'insurance'
                ];
            } elseif ($daysUntilExpiry <= 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Assurance expire dans {$daysUntilExpiry} jours",
                    'category' => 'insurance'
                ];
            }
        }

        // Alerte contrôle technique
        if ($this->technical_inspection_expiry) {
            $daysUntilExpiry = now()->diffInDays($this->technical_inspection_expiry, false);
            if ($daysUntilExpiry < 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Contrôle technique expiré depuis ' . abs($daysUntilExpiry) . ' jours',
                    'category' => 'inspection'
                ];
            } elseif ($daysUntilExpiry <= 30) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Contrôle technique expire dans {$daysUntilExpiry} jours",
                    'category' => 'inspection'
                ];
            }
        }

        // Alerte kilométrage maintenance
        if ($this->next_maintenance_mileage && $this->mileage) {
            $kmRemaining = $this->next_maintenance_mileage - $this->mileage;
            if ($kmRemaining <= 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => 'Maintenance kilométrique dépassée de ' . abs($kmRemaining) . ' km',
                    'category' => 'maintenance'
                ];
            } elseif ($kmRemaining <= 1000) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Maintenance dans {$kmRemaining} km",
                    'category' => 'maintenance'
                ];
            }
        }

        // Alerte maintenances préventives en retard
        try {
            $overdueCount = $this->preventiveMaintenances()
                ->where('is_active', true)
                ->whereDate('next_execution_date', '<', now())
                ->count();

            if ($overdueCount > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "{$overdueCount} maintenance(s) préventive(s) en retard",
                    'category' => 'preventive'
                ];
            }
        } catch (\Exception $e) {
            // Ignorer si la table n'existe pas encore
        }

        return $alerts;
    }

    // ===== SCOPES =====

    public function scopeForSite(Builder $query, int $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', 'active');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function scopeInMaintenance(Builder $query): Builder
    {
        return $query->whereIn('status', ['maintenance', 'repair']);
    }

    public function scopeWithExpiredInsurance(Builder $query): Builder
    {
        return $query->whereNotNull('insurance_expiry')
            ->whereDate('insurance_expiry', '<', now());
    }

    public function scopeWithExpiringInsurance(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('insurance_expiry')
            ->whereDate('insurance_expiry', '>=', now())
            ->whereDate('insurance_expiry', '<=', now()->addDays($days));
    }

    public function scopeWithExpiredInspection(Builder $query): Builder
    {
        return $query->whereNotNull('technical_inspection_expiry')
            ->whereDate('technical_inspection_expiry', '<', now());
    }

    public function scopeNeedingMaintenance(Builder $query): Builder
    {
        return $query->whereNotNull('next_maintenance_mileage')
            ->whereColumn('mileage', '>=', 'next_maintenance_mileage');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('numero', 'like', "%{$search}%")
              ->orWhere('registration_number', 'like', value: "%{$search}%")
              ->orWhere('brand', 'like', "%{$search}%")
              ->orWhere('model', 'like', "%{$search}%")
              ->orWhere('vin', 'like', "%{$search}%");
        });
    }

    // ===== METHODS =====

    /**
     * Mettre à jour le kilométrage
     */
    public function updateMileage(int $newMileage, ?string $source = null): bool
    {
        if ($newMileage < $this->mileage) {
            return false; // Ne pas permettre de réduire le kilométrage
        }

        $this->mileage = $newMileage;
        return $this->save();
    }

    /**
     * Assigner un chauffeur
     */
    public function assignDriver(?Driver $driver, ?int $startMileage = null): ?TruckDriverHistory
    {
        // Terminer l'assignation actuelle si existante
        if ($this->current_driver_id) {
            $this->unassignCurrentDriver($startMileage);
        }

        if (!$driver) {
            $this->current_driver_id = null;
            $this->save();
            return null;
        }

        // Créer nouvelle assignation
        $history = $this->driverHistory()->create([
            'driver_id' => $driver->id,
            'site_id' => $this->site_id,
            'start_date' => now(),
            'start_mileage' => $startMileage ?? $this->mileage,
        ]);

        $this->current_driver_id = $driver->id;
        $this->save();

        return $history;
    }

    /**
     * Désassigner le chauffeur actuel
     */
    public function unassignCurrentDriver(?int $endMileage = null): void
    {
        $currentHistory = $this->driverHistory()
            ->whereNull('end_date')
            ->where('driver_id', $this->current_driver_id)
            ->latest()
            ->first();

        if ($currentHistory) {
            $currentHistory->update([
                'end_date' => now(),
                'end_mileage' => $endMileage ?? $this->mileage,
            ]);
        }

        $this->current_driver_id = null;
        $this->save();
    }

    /**
     * Changer le statut
     */
    public function changeStatus(string $status): bool
    {
        $validStatuses = ['active', 'maintenance', 'repair', 'out_of_service', 'reserved'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    /**
     * Vérifier si le camion est disponible
     */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && $this->is_active;
    }

    /**
     * Générer un code unique
     */
    public static function generateCode(int $siteId): string
    {
        $site = Site::find($siteId);
        $prefix = $site ? strtoupper(substr($site->code ?? $site->name, 0, 3)) : 'TRK';
        
        $lastTruck = static::where('site_id', $siteId)
            ->where('code', 'like', "{$prefix}-CAM-%")
            ->orderByRaw('CAST(SUBSTRING(code, -4) AS UNSIGNED) DESC')
            ->first();

        if ($lastTruck && preg_match('/(\d+)$/', $lastTruck->code, $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-CAM-%04d', $prefix, $nextNumber);
    }

    // ===== BOOT =====

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($truck) {
            if (empty($truck->code)) {
                $truck->code = self::generateCode($truck->site_id);
            }
        });
    }
}
