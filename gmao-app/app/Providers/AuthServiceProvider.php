<?php

namespace App\Providers;

use App\Models\WorkOrder;
use App\Models\InterventionRequest;
use App\Models\PreventiveMaintenance;
use App\Models\Equipment;
use App\Models\Truck;
use App\Models\Driver;
use App\Policies\WorkOrderPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        WorkOrder::class => WorkOrderPolicy::class,
        // Ajouter d'autres policies ici au fur et à mesure
        // InterventionRequest::class => InterventionRequestPolicy::class,
        // Equipment::class => EquipmentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
