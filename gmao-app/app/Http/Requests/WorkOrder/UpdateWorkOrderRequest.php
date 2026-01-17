<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        $workOrder = $this->route('workOrder');
        
        return $this->user()->can('workorder:update') && 
               $this->user()->canAccessSite($workOrder->site_id);
    }

    /**
     * Règles de validation.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['sometimes', 'required', Rule::in(['corrective', 'preventive', 'improvement', 'inspection'])],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
            'estimated_duration' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'work_performed' => ['nullable', 'string', 'max:5000'],
            'root_cause' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
            'mileage_at_intervention' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Messages d'erreur personnalisés.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères.',
            'type.in' => 'Le type d\'intervention n\'est pas valide.',
            'priority.in' => 'La priorité n\'est pas valide.',
            'assigned_to.exists' => 'Le technicien sélectionné n\'existe pas.',
            'scheduled_end.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
            'work_performed.max' => 'Le travail effectué ne peut pas dépasser 5000 caractères.',
            'mileage_at_intervention.min' => 'Le kilométrage ne peut pas être négatif.',
        ];
    }
}
