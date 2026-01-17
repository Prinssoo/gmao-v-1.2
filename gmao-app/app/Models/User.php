<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_site_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    /**
     * Site actuel de l'utilisateur
     */
    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    /**
     * Ordres de travail assignés à cet utilisateur
     */
    public function assignedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'assigned_to');
    }

    /**
     * Ordres de travail demandés par cet utilisateur
     */
    public function requestedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'requested_by');
    }

    /**
     * Ordres de travail complétés par cet utilisateur
     */
    public function completedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'completed_by');
    }

    /**
     * Demandes d'intervention créées par cet utilisateur
     */
    public function interventionRequests(): HasMany
    {
        return $this->hasMany(InterventionRequest::class, 'requested_by');
    }

    /**
     * Notifications de l'utilisateur
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Maintenances préventives créées par cet utilisateur
     */
    public function createdPreventiveMaintenances(): HasMany
    {
        return $this->hasMany(PreventiveMaintenance::class, 'created_by');
    }

    /**
     * Maintenances préventives assignées à cet utilisateur
     */
    public function assignedPreventiveMaintenances(): HasMany
    {
        return $this->hasMany(PreventiveMaintenance::class, 'assigned_to');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Filtrer par site actuel de l'utilisateur connecté
     */
    public function scopeForCurrentSite($query)
    {
        return $query->where('current_site_id', auth()->user()?->current_site_id);
    }

    /**
     * Filtrer les techniciens uniquement
     */
    public function scopeTechnicians($query)
    {
        return $query->role(['Technicien', 'technician']);
    }

    /**
     * Filtrer les utilisateurs actifs
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Vérifier si l'utilisateur est un super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(['super-admin', 'SuperAdmin']);
    }

    /**
     * Vérifier si l'utilisateur est un technicien
     */
    public function isTechnician(): bool
    {
        return $this->hasRole(['Technicien', 'technician']);
    }

    /**
     * Vérifier si l'utilisateur peut accéder à un site spécifique
     */
    public function canAccessSite(int $siteId): bool
    {
        return $this->isSuperAdmin() || $this->current_site_id === $siteId;
    }

    /**
     * Nombre d'OT actifs assignés
     */
    public function getActiveWorkOrdersCountAttribute(): int
    {
        return $this->assignedWorkOrders()
            ->whereIn('status', ['pending', 'assigned', 'in_progress', 'on_hold'])
            ->count();
    }

    /**
     * Nombre de notifications non lues
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->notifications()->whereNull('read_at')->count();
    }
}
