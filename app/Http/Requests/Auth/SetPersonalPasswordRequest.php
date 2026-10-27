<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
class SetPersonalPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allow submission only while this account needs password setup.
        //return $this->user()?->must_change_password === true;

        // Get the signed-in account.
        $user = $this->user();

        // Reject the request if nobody is signed in.
        if ($user === null) {
            return false;
        }

        // Allow it only if the account still needs password setup.
        return $user->must_change_password;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Require a password of at least 8 characters and matching confirmation.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
