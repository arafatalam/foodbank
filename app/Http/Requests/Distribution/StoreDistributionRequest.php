<?php

namespace App\Http\Requests\Distribution;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreDistributionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Permission will be checked by the route policy and saving action.
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
            // Require the ID of an existing household.
            'household_id' => ['required', 'integer', 'exists:households,id'],
            // Require at least one item in the distribution.
            'items' => ['required', 'array', 'min:1'],
            // Allow only the stock-item ID and quantity in each row.
            'items.*' => ['array:stock_item_id,quantity'],
            // Require an existing stock item, appearing only once in the distribution.
            'items.*.stock_item_id' => [
                'required',
                'integer',
                'exists:stock_items,id',
                'distinct',
            ],
            // Require a positive whole-package count within the database's limit.
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
            ],
        ];
    }

    /**
     * @return array{household_id: int, items: list<array{stock_item_id: int, quantity: positive-int}>}
     */
    public function distributionData(): array
    {
        // Start an empty list for the prepared item rows.
        $items = [];

        // Visit each submitted item row using its position.
        foreach (array_keys($this->array('items')) as $index) {
            // Read the quantity once for this row.
            $quantity = $this->integer("items.$index.quantity");

            // Stop preparation if the quantity is not positive.
            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'The quantity must be at least 1.',
                ]);
            }

            // Read this row's ID and quantity as whole numbers.
            $items[] = [
                'stock_item_id' => $this->integer("items.$index.stock_item_id"),
                // Use the positive quantity we just checked.
                'quantity' => $quantity,
            ];
        }

        // Give the saving action the household ID as a whole number.
        return [
            'household_id' => $this->integer('household_id'),
            // Include the prepared item rows with the selected household.
            'items' => $items,
        ];
    }
}
