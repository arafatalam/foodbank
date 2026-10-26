<?php

namespace App\Http\Requests\Users;

use App\Actions\Users\GetAssignableRoles;
use App\Models\User;
use Closure;
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
}
