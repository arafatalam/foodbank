<?php

namespace App\Http\Requests\PackageSizes;

use Illuminate\Foundation\Http\FormRequest;

class IndexPackageSizesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The list route checks the PackageSize policy.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
        ];
    }
}
