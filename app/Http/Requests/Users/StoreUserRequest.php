<?php

namespace App\Http\Requests\Users;

use App\Actions\Users\GetAssignableRoles;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $actor = $this->user();

        $allowedRoleIds = $actor
            ? (new GetAssignableRoles)->get($actor)->pluck('id')->all()
            : [];

        return [
            'first_name' => ['required', 'string', 'max:100'],

            'last_name' => ['required', 'string', 'max:100'],

            'email' => [
                'bail',
                'required',
                'string',
                'email',
                'max:254',
                'unique:users,email',
            ],

            'phone' => [
                'required',
                'string',
                'regex:/\A\+1 [0-9]{3}-[0-9]{3}-[0-9]{4}\z/',
            ],

            'role_id' => [
                'required',
                'integer',
                Rule::in($allowedRoleIds),
            ],

            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array{first_name: string, last_name: string, email: string, phone: string, role_id: int, is_active: bool} */
    public function userData(): array
    {
        // These fields have passed validation; provide consistent types to the action.
        return [
            'first_name' => $this->string('first_name')->toString(),
            'last_name' => $this->string('last_name')->toString(),
            'email' => $this->string('email')->toString(),
            'phone' => $this->string('phone')->toString(),
            'role_id' => $this->integer('role_id'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
