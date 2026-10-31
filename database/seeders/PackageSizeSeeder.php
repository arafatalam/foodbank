<?php

namespace Database\Seeders;

use App\Actions\PackageSizes\GetPackageSizeDefinitionKey;
use App\Models\PackageSize;
use Illuminate\Database\Seeder;

class PackageSizeSeeder extends Seeder
{
    public function run(): void
    {
        // Reusable examples only: these definitions do not create products or stock.
        $packages = [
            'bag' => [
                'g' => ['250', '500'],
                'kg' => ['1', '2', '5', '10'],
            ],
            'box' => [
                'g' => ['250', '500'],
                'item' => ['6', '12', '24'],
            ],
            'can' => [
                'g' => ['200', '400', '800'],
                'kg' => ['1'],
                'ml' => ['400'],
            ],
            'bottle' => [
                'ml' => ['250', '500', '750'],
                'litre' => ['1', '1.5', '2'],
            ],
            'carton' => [
                'ml' => ['200', '250', '500'],
                'litre' => ['1', '2'],
            ],
            'jar' => [
                'g' => ['250', '500', '750'],
                'ml' => ['500'],
            ],
            'packet' => [
                'g' => ['25', '50', '100', '250', '500'],
            ],
            'individual_item' => [
                'item' => ['1'],
            ],
        ];

        // Use the same equivalence calculation as the Create and Edit actions.
        $keys = new GetPackageSizeDefinitionKey;

        foreach ($packages as $packageType => $units) {
            foreach ($units as $unit => $amounts) {
                foreach ($amounts as $contents) {
                    $data = [
                        'package_type' => $packageType,
                        'contents' => $contents,
                        'contents_unit' => $unit,
                    ];
                    $definitionKey = $keys->get($data);

                    // Rerunning the seeder must not duplicate or overwrite existing definitions.
                    if (PackageSize::query()->where('definition_key', $definitionKey)->exists()) {
                        continue;
                    }

                    // Preserve the displayed measurements and store the generated key separately.
                    $packageSize = new PackageSize($data);
                    $packageSize->definition_key = $definitionKey;
                    $packageSize->save();
                }
            }
        }
    }
}
