<?php

namespace App\Http\Requests\PackageSizes;

use App\Models\PackageSize;

class UpdatePackageSizeRequest extends StorePackageSizeRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Reuse creation validation, but check permission for this saved definition.
        $packageSize = $this->route('packageSize');

        return $packageSize instanceof PackageSize
            && ($this->user()?->can('update', $packageSize) ?? false);
    }
}
