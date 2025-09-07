<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class IndexGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,archived',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'q.max' => 'Search query cannot exceed 255 characters.',
            'status.in' => 'Status must be one of: active, inactive, archived.',
        ];
    }
}
