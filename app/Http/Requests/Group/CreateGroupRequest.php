<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'settings' => 'nullable|array',
            'settings.turn_duration' => 'nullable|integer|min:1|max:1440',
            'settings.notifications_enabled' => 'nullable|boolean',
            'settings.auto_advance' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Group name is required.',
            'name.max' => 'Group name cannot exceed 255 characters.',
            'description.max' => 'Group description cannot exceed 1000 characters.',
            'settings.turn_duration.min' => 'Turn duration must be at least 1 minute.',
            'settings.turn_duration.max' => 'Turn duration cannot exceed 1440 minutes (24 hours).',
        ];
    }
}
