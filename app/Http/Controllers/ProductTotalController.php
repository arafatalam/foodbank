<?php

namespace App\Http\Controllers;

use App\Actions\ProductTotals\ListProductTotals;
use App\Http\Requests\ProductTotals\IndexProductTotalsRequest;
use Inertia\Inertia;
use Inertia\Response;

class ProductTotalController extends Controller
{
    public function index(IndexProductTotalsRequest $request, ListProductTotals $action): Response
    {
        // Use only validated search text, with an empty default.
        $filters = ['search' => (string) $request->validated('search', '')];

        // Ask the action for the prepared, paginated Product totals.
        $productTotals = $action->get($filters, $request->user());

        // Send those totals to the Product totals Vue page.
        return Inertia::render('product-totals/Index', [
            'productTotals' => $productTotals,
            'search' => $filters['search'],
        ]);
    }
}
