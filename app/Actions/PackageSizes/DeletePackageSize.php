<?php

namespace App\Actions\PackageSizes;

use App\Models\PackageSize;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeletePackageSize
{
    public function delete(PackageSize $packageSize, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $packageSize);

        DB::transaction(function () use ($packageSize, $actor) {
            // Stock-item creation locks this same definition before attaching it.
            $current = PackageSize::query()->whereKey($packageSize->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $current);
            $current->delete();
        }, attempts: 3);
    }
}
