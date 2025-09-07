<?php

namespace App\Http\Requests\Turn;

use Illuminate\Foundation\Http\FormRequest;

class SkipTurnRequest extends FormRequest
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
            'reason' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'reason.max' => 'Skip reason cannot exceed 500 characters.',
        ];
    }
}
