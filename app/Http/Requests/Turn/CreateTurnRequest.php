<?php

namespace App\Http\Requests\Turn;

use Illuminate\Foundation\Http\FormRequest;

class CreateTurnRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in the controller via policies
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'group_id' => 'required|exists:groups,id',
            'notes' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'group_id.required' => 'Group ID is required.',
            'group_id.exists' => 'The specified group does not exist.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }
}
