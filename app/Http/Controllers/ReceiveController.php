<?php

namespace App\Http\Controllers;

use App\Actions\Receive\CreateReceive;
use App\Actions\Receive\GetReceiveStockItems;
use App\Http\Requests\Receive\StoreReceiveRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReceiveController extends Controller
{
    // Show the Receive page with stock-item choices prepared by the action.
    public function create(GetReceiveStockItems $action): Response
    {
        $stockItems = $action->get();

        return Inertia::render('receive/Create', [
            'stockItems' => $stockItems,
        ]);
    }

    public function store(
        StoreReceiveRequest $request,
        CreateReceive $action,
    ): RedirectResponse {
        // Pass the checked form values and signed-in user to the saving action.
        $action->create($request->receiptData(), $request->user());

        // Show a success notification after the complete receipt saves.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Stock received successfully.',
        ]);

        // Return to the Receive page after saving succeeds.
        return to_route('receive.create');
    }
}
