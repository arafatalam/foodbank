<?php

namespace App\Http\Requests\Tags;

use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check permission for the tag selected by the URL.
        $tag = $this->route('tag');

        return $tag instanceof Tag
            && ($this->user()?->can('update', $tag) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Ignore this record when checking whether its name is already used.
        $tag = $this->route('tag');
        abort_unless($tag instanceof Tag, 404);

        return [
            // Laravel trims outer spaces; MySQL's configured collation ignores case.
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tags', 'name')->ignore($tag),
            ],
        ];
    }
}
