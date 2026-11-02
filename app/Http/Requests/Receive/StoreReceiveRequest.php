<?php

namespace App\Http\Requests\Receive;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreReceiveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Permission is checked by the route policy and the save action.
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
            // Leave room in MySQL's TEXT column even for four-byte characters.
            'notes' => ['nullable', 'string', 'max:10000'],
            // Require a list containing at least one receipt item.
            'items' => ['required', 'array', 'min:1'],
            // Allow only these two fields inside each receipt row.
            'items.*' => ['array:stock_item_id,quantity'],
            // Check the selected stock item in every receipt row.
            'items.*.stock_item_id' => [
                'required',
                'integer',
                'exists:stock_items,id',
                'distinct',
            ],
            // Require a positive whole-package quantity in every receipt row.
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
            ],
        ];
    }

    /**
     * Prepare the validated receipt for the saving action.
     *
     * @return array{notes: string|null, items: list<array{stock_item_id: int, quantity: positive-int}>}
     */
    public function receiptData(): array
    {
        $items = [];

        // HTML forms can send whole numbers as strings; give the action integers.
        foreach (array_keys($this->array('items')) as $index) {
            $quantity = $this->integer("items.$index.quantity");

            // Preserve the action's positive-quantity contract if this helper is called early.
            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'The quantity must be at least 1.',
                ]);
            }

            $items[] = [
                'stock_item_id' => $this->integer("items.$index.stock_item_id"),
                'quantity' => $quantity,
            ];
        }

        return [
            'notes' => $this->filled('notes') ? $this->string('notes')->toString() : null,
            'items' => $items,
        ];
    }
}
