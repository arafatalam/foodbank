<?php

namespace App\Http\Requests\Users;

use App\Actions\Users\GetAssignableRoles;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Get the account selected through the URL.
        $targetUser = $this->route('user');

        // Allow the request only when the update policy permits it.
        return $targetUser instanceof User
            && ($this->user()?->can('update', $targetUser) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the account being edited.
        $targetUser = $this->route('user');

        // Stop if Laravel has not found a valid account.
        abort_unless($targetUser instanceof User, 404);

        // Accept only the same roles offered by the edit dropdown.
        $actor = $this->user();
        $allowedRoleIds = $actor
            ? (new GetAssignableRoles)->get($actor, forEdit: true)->pluck('id')->all()
            : [];

        return [
            // Require names containing 1–100 characters.
            'first_name' => ['required', 'string', 'min:1', 'max:100'],
            'last_name' => ['required', 'string', 'min:1', 'max:100'],

            // Allow this account's current email, but reject another account's email.
            'email' => [
                'bail',
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('users', 'email')->ignore($targetUser),
            ],

            // Require the Canadian format: +1 506-555-0123.
            'phone' => [
                'required',
                'string',
                'regex:/\A\+1 [0-9]{3}-[0-9]{3}-[0-9]{4}\z/',
            ],

            // Validate these fields so they are included in the saved data.
            'role_id' => ['required', 'integer', Rule::in($allowedRoleIds)],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
