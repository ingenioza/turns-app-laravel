<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class JoinGroupRequest extends FormRequest
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
            'invite_code' => 'required|string|size:8',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'invite_code.required' => 'Invite code is required.',
            'invite_code.size' => 'Invite code must be exactly 8 characters.',
        ];
    }
}
