<?php

namespace App\Actions\PackageSizes;

use App\Models\PackageSize;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdatePackageSize
{
    /** @param array{package_type: string, contents: int|float|string, contents_unit: string} $data */
    public function update(PackageSize $packageSize, array $data, User $actor): PackageSize
    {
        Gate::forUser($actor)->authorize('update', $packageSize);
        $definitionKey = (new GetPackageSizeDefinitionKey)->get($data);

        try {
            return DB::transaction(function () use ($packageSize, $data, $actor, $definitionKey) {
                // Lock and recheck the current definition before modifying it.
                $current = PackageSize::query()->whereKey($packageSize->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $current);
                $this->ensureDefinitionAvailable($definitionKey, $current->id);

                return $this->savePackage($current, $data, $definitionKey);
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent create/edit may have claimed the equivalent definition.
            $this->ensureDefinitionAvailable($definitionKey, $packageSize->id);

            throw $exception;
        }
    }

    private function ensureDefinitionAvailable(string $definitionKey, int $exceptId): void
    {
        // Keeping this record's own definition is allowed; matching another is not.
        if (PackageSize::query()->where('definition_key', $definitionKey)->whereKeyNot($exceptId)->exists()) {
            throw ValidationException::withMessages([
                'contents' => 'An equivalent package size already exists.',
            ]);
        }
    }

    /** @param array{package_type: string, contents: int|float|string, contents_unit: string} $data */
    private function savePackage(PackageSize $packageSize, array $data, string $definitionKey): PackageSize
    {
        // Preserve the record's ID and regenerate its key from the new values.
        $packageSize->package_type = $data['package_type'];
        $packageSize->contents = (string) $data['contents'];
        $packageSize->contents_unit = $data['contents_unit'];
        $packageSize->definition_key = $definitionKey;
        $packageSize->save();

        return $packageSize;
    }
}
