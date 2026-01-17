<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        return $this->user()->can('workorder:create');
    }

    /**
     * Règles de validation.
     */
    public function rules(): array
    {
        return [
            'asset_type' => ['required', Rule::in(['equipment', 'truck'])],
            'equipment_id' => [
                'nullable',
                'required_if:asset_type,equipment',
                'exists:equipments,id',
            ],
            'truck_id' => [
                'nullable',
                'required_if:asset_type,truck',
                'exists:trucks,id',
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(['corrective', 'preventive', 'improvement', 'inspection'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after_or_equal:scheduled_start'],
            'estimated_duration' => ['nullable', 'integer', 'min:1', 'max:10080'], // max 1 semaine en minutes
        ];
    }

    /**
     * Messages d'erreur personnalisés.
     */
    public function messages(): array
    {
        return [
            'asset_type.required' => 'Le type d\'actif est obligatoire.',
            'asset_type.in' => 'Le type d\'actif doit être équipement ou camion.',
            'equipment_id.required_if' => 'L\'équipement est obligatoire pour ce type d\'actif.',
            'equipment_id.exists' => 'L\'équipement sélectionné n\'existe pas.',
            'truck_id.required_if' => 'Le camion est obligatoire pour ce type d\'actif.',
            'truck_id.exists' => 'Le camion sélectionné n\'existe pas.',
            'title.required' => 'Le titre est obligatoire.',
            'title.max' => 'Le titre ne peut pas dépasser 255 caractères.',
            'description.max' => 'La description ne peut pas dépasser 2000 caractères.',
            'type.required' => 'Le type d\'intervention est obligatoire.',
            'type.in' => 'Le type d\'intervention n\'est pas valide.',
            'priority.required' => 'La priorité est obligatoire.',
            'priority.in' => 'La priorité n\'est pas valide.',
            'assigned_to.exists' => 'Le technicien sélectionné n\'existe pas.',
            'scheduled_start.date' => 'La date de début prévue n\'est pas valide.',
            'scheduled_end.date' => 'La date de fin prévue n\'est pas valide.',
            'scheduled_end.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
            'estimated_duration.integer' => 'La durée estimée doit être un nombre entier.',
            'estimated_duration.min' => 'La durée estimée doit être d\'au moins 1 minute.',
        ];
    }

    /**
     * Attributs personnalisés pour les messages d'erreur.
     */
    public function attributes(): array
    {
        return [
            'asset_type' => 'type d\'actif',
            'equipment_id' => 'équipement',
            'truck_id' => 'camion',
            'title' => 'titre',
            'description' => 'description',
            'type' => 'type d\'intervention',
            'priority' => 'priorité',
            'assigned_to' => 'technicien assigné',
            'scheduled_start' => 'date de début prévue',
            'scheduled_end' => 'date de fin prévue',
            'estimated_duration' => 'durée estimée',
        ];
    }

    /**
     * Prépare les données pour la validation.
     */
    protected function prepareForValidation(): void
    {
        // Nettoyer les IDs non pertinents selon le type d'asset
        if ($this->asset_type === 'equipment') {
            $this->merge(['truck_id' => null]);
        } elseif ($this->asset_type === 'truck') {
            $this->merge(['equipment_id' => null]);
        }
    }
}
