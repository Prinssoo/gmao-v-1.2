<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStatusRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête.
     */
    public function authorize(): bool
    {
        $workOrder = $this->route('workOrder');
        return $this->user()->canAccessSite($workOrder->site_id);
    }

    /**
     * Règles de validation.
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(['pending', 'approved', 'assigned', 'in_progress', 'on_hold', 'completed', 'cancelled']),
            ],
            'work_performed' => ['nullable', 'string', 'max:5000'],
            'root_cause' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
            'mileage_at_intervention' => ['nullable', 'integer', 'min:0'],
            'cancellation_reason' => [
                'nullable',
                'required_if:status,cancelled',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Messages d'erreur personnalisés.
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Le statut est obligatoire.',
            'status.in' => 'Le statut n\'est pas valide.',
            'cancellation_reason.required_if' => 'La raison d\'annulation est obligatoire.',
            'cancellation_reason.max' => 'La raison d\'annulation ne peut pas dépasser 500 caractères.',
        ];
    }
}
