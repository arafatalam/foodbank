<?php

namespace App\Http\Controllers;

use App\Actions\Distribution\CreateDistribution;
use App\Actions\Distribution\GetDistributionHouseholds;
use App\Actions\Distribution\GetDistributionStockItems;
use App\Http\Requests\Distribution\StoreDistributionRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DistributionController extends Controller
{
    // Prepare the choices and send them to the Distribution page.
    public function create(
        GetDistributionHouseholds $getHouseholds,
        GetDistributionStockItems $getStockItems,
    ): Response {
        // Fetch each set of choices using its own action.
        $households = $getHouseholds->get();
        $stockItems = $getStockItems->get();

        // Give Vue two named pieces of page data.
        return Inertia::render('distribution/Create', [
            'households' => $households,
            'stockItems' => $stockItems,
        ]);
    }

    public function store(
        StoreDistributionRequest $request,
        CreateDistribution $action,
    ): RedirectResponse {
        // Pass the checked values and signed-in recorder to the saving action.
        $action->create($request->distributionData(), $request->user());

        // Show confirmation after the entire distribution saves successfully.
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Distribution recorded successfully.',
        ]);

        // Return to the Distribution page after saving succeeds.
        return to_route('distribution.create');
    }
}
