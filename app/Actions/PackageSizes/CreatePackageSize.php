<?php

namespace App\Actions\PackageSizes;

use App\Models\PackageSize;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatePackageSize
{
    /** @param array{package_type: string, contents: int|float|string, contents_unit: string} $data */
    public function create(array $data, User $actor): PackageSize
    {
        // Check permission before performing the creation operation.
        Gate::forUser($actor)->authorize('create', PackageSize::class);

        // Calculate the identity, check for duplicates, then save the original values.
        $definitionKey = (new GetPackageSizeDefinitionKey)->get($data);
        $this->ensureDefinitionAvailable($definitionKey);

        return $this->savePackage($data, $definitionKey);
    }

    private function ensureDefinitionAvailable(string $definitionKey): void
    {
        // Reject a package definition with the same type and equivalent contents.
        if (PackageSize::query()->where('definition_key', $definitionKey)->exists()) {
            throw ValidationException::withMessages([
                'contents' => 'An equivalent package size already exists.',
            ]);
        }
    }

    /** @param array{package_type: string, contents: int|float|string, contents_unit: string} $data */
    private function savePackage(array $data, string $definitionKey): PackageSize
    {
        // Prepare the package using the original measurements and our calculated key.
        $packageSize = new PackageSize;
        $packageSize->package_type = $data['package_type'];
        $packageSize->contents = (string) $data['contents'];
        $packageSize->contents_unit = $data['contents_unit'];
        $packageSize->definition_key = $definitionKey;

        try {
            // Insert the package into the database.
            $packageSize->save();
        } catch (UniqueConstraintViolationException $exception) {
            // Another request may have saved this definition after our earlier check.
            $this->ensureDefinitionAvailable($definitionKey);

            // Preserve unexpected database errors rather than mislabelling them.
            throw $exception;
        }

        // Give the saved package back to the controller.
        return $packageSize;
    }
}
