<?php

namespace App\Http\Requests\PackageSizes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageSizeRequest extends FormRequest
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
        return [
            // Accept only the package types offered by our form.
            'package_type' => [
                'required',
                Rule::in([
                    'bag',
                    'box',
                    'can',
                    'bottle',
                    'carton',
                    'jar',
                    'packet',
                    'individual_item',
                ]),
            ],
            // Require a positive amount that fits our decimal database column.
            'contents' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
                'max:999999999.999',
                // Items must be whole counts; allow 12 or 12.000, but not 12.5.
                Rule::when($this->input('contents_unit') === 'item', [
                    'regex:/^\d+(?:\.0{1,3})?$/',
                ]),
            ],

            // Accept only our supported measurement units.
            'contents_unit' => [
                'required',
                Rule::in(['g', 'kg', 'ml', 'litre', 'item']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            // Explain the item-specific rule beside the contents field.
            'contents.regex' => 'Item contents must be a whole number.',
        ];
    }

    /** @return array{package_type: string, contents: string, contents_unit: string} */
    public function packageData(): array
    {
        // Pass only validated fields, keeping decimal contents as text for exact arithmetic.
        return [
            'package_type' => (string) $this->validated('package_type'),
            'contents' => (string) $this->validated('contents'),
            'contents_unit' => (string) $this->validated('contents_unit'),
        ];
    }
}
