<?php

namespace App\Actions\Distribution;

use App\Models\Household;
use Illuminate\Support\Collection;

class GetDistributionHouseholds
{
    /** @return Collection<int, Household> */
    public function get(): Collection
    {
        // Sort the household choices by first name, then last name.
        return Household::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->orderBy('id')
            // Fetch only the fields our selection screen needs.
            ->get(['id', 'first_name', 'last_name']);
    }
}
