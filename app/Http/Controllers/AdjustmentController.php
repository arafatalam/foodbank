<?php

namespace App\Http\Controllers;

use App\Actions\Adjustment\CreateAdjustment;
use App\Actions\Adjustment\GetAdjustmentStockItems;
use App\Http\Requests\Adjustment\StoreAdjustmentRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdjustmentController extends Controller
{
    public function create(GetAdjustmentStockItems $action): Response
    {
        // Ask the action to prepare the stock-item choices.
        $stockItems = $action->get();

        // Send those choices to the Adjustment Vue page.
        return Inertia::render('adjustment/Create', [
            'stockItems' => $stockItems,
        ]);
    }

    public function store(
        StoreAdjustmentRequest $request,
        CreateAdjustment $action,
    ): RedirectResponse {
        // Pass the prepared values and signed-in user to the saving action.
        $action->create($request->adjustmentData(), $request->user());

        // Show success only after the complete adjustment saves.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Stock adjusted successfully.',
        ]);

        // Return to the Adjustment page with updated stock choices.
        return to_route('adjustment.create');
    }
}
