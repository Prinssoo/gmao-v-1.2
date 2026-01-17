<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

class CompleteWorkOrderRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        return $this->user()->can('workorder:close');
    }

    /**
     * Règles de validation.
     */
    public function rules(): array
    {
        $workOrder = $this->route('workOrder');
        $rules = [
            'work_performed' => ['required', 'string', 'max:5000'],
            'root_cause' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
        ];

        // Kilométrage requis pour les camions
        if ($workOrder && $workOrder->asset_type === 'truck') {
            $rules['mileage_at_intervention'] = ['required', 'integer', 'min:0'];
        } else {
            $rules['mileage_at_intervention'] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * Messages d'erreur personnalisés.
     */
    public function messages(): array
    {
        return [
            'work_performed.required' => 'La description du travail effectué est obligatoire.',
            'work_performed.max' => 'La description du travail ne peut pas dépasser 5000 caractères.',
            'mileage_at_intervention.required' => 'Le kilométrage est obligatoire pour les interventions sur camion.',
            'mileage_at_intervention.min' => 'Le kilométrage ne peut pas être négatif.',
        ];
    }
}
