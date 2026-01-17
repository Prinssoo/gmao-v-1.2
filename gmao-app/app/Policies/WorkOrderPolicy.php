<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Exécuté avant toute autre vérification.
     * Donne accès total aux super admins.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Déterminer si l'utilisateur peut voir la liste des OT.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('workorder:view');
    }

    /**
     * Déterminer si l'utilisateur peut voir un OT spécifique.
     */
    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('workorder:view') && 
               $user->canAccessSite($workOrder->site_id);
    }

    /**
     * Déterminer si l'utilisateur peut créer un OT.
     */
    public function create(User $user): bool
    {
        return $user->can('workorder:create');
    }

    /**
     * Déterminer si l'utilisateur peut modifier un OT.
     */
    public function update(User $user, WorkOrder $workOrder): bool
    {
        // Ne peut pas modifier un OT complété ou annulé
        if (in_array($workOrder->status, ['completed', 'cancelled'])) {
            return false;
        }

        return $user->can('workorder:update') && 
               $user->canAccessSite($workOrder->site_id);
    }

    /**
     * Déterminer si l'utilisateur peut supprimer un OT.
     */
    public function delete(User $user, WorkOrder $workOrder): bool
    {
        // Ne peut supprimer que les OT pending
        if ($workOrder->status !== 'pending') {
            return false;
        }

        return $user->can('workorder:delete') && 
               $user->canAccessSite($workOrder->site_id);
    }

    /**
     * Déterminer si l'utilisateur peut démarrer un OT.
     */
    public function start(User $user, WorkOrder $workOrder): bool
    {
        if (!in_array($workOrder->status, ['pending', 'assigned', 'on_hold'])) {
            return false;
        }

        // Le technicien assigné ou quelqu'un avec la permission peut démarrer
        $isAssignedTechnician = $workOrder->assigned_to === $user->id;
        $hasPermission = $user->can('workorder:start');

        return ($isAssignedTechnician || $hasPermission) && 
               $user->canAccessSite($workOrder->site_id);
    }

    /**
     * Déterminer si l'utilisateur peut compléter un OT.
     */
    public function complete(User $user, WorkOrder $workOrder): bool
    {
        if (!in_array($workOrder->status, ['in_progress', 'assigned'])) {
            return false;
        }

        $isAssignedTechnician = $workOrder->assigned_to === $user->id;
        $hasPermission = $user->can('workorder:close');

        return ($isAssignedTechnician || $hasPermission) && 
               $user->canAccessSite($workOrder->site_id);
    }

    /**
     * Déterminer si l'utilisateur peut annuler un OT.
     */
    public function cancel(User $user, WorkOrder $workOrder): bool
    {
        if ($workOrder->status === 'completed') {
            return false;
        }

        return $user->can('workorder:cancel') && 
               $user->canAccessSite($workOrder->site_id);
    }

    /**
     * Déterminer si l'utilisateur peut assigner un technicien à un OT.
     */
    public function assign(User $user, WorkOrder $workOrder): bool
    {
        if (in_array($workOrder->status, ['completed', 'cancelled'])) {
            return false;
        }

        return $user->can('workorder:assign') && 
               $user->canAccessSite($workOrder->site_id);
    }

    /**
     * Déterminer si l'utilisateur peut ajouter des pièces à un OT.
     */
    public function addParts(User $user, WorkOrder $workOrder): bool
    {
        if (in_array($workOrder->status, ['completed', 'cancelled'])) {
            return false;
        }

        return ($user->id === $workOrder->assigned_to || $user->can('workorder:update')) && 
               $user->canAccessSite($workOrder->site_id);
    }
}
