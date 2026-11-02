<?php

namespace App\Http\Requests\Adjustment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // The route checks permission through the StockEntry policy.
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
            // Require the ID of an existing stock item.
            'stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            // Accept only the two supported adjustment directions.
            'direction' => ['required', 'in:add,remove'],
            // Require a positive whole-package count within the database limit.
            'quantity' => ['required', 'integer', 'min:1', 'max:4294967295'],
            // Require an explanation and keep its length safe for storage.
            'reason' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * Prepare the checked form values for the saving action.
     *
     * @return array{stock_item_id: int, quantity: int, direction: string, reason: string}
     */
    public function adjustmentData(): array
    {
        return [
            // Convert IDs and package counts into whole numbers.
            'stock_item_id' => $this->integer('stock_item_id'),
            'quantity' => $this->integer('quantity'),

            // Read the chosen direction and explanation as text.
            'direction' => $this->string('direction')->toString(),
            'reason' => $this->string('reason')->toString(),
        ];
    }
}
