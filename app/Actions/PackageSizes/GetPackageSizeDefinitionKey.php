<?php

namespace App\Actions\PackageSizes;

use Illuminate\Validation\ValidationException;

class GetPackageSizeDefinitionKey
{
    /** @param array{package_type: string, contents: int|float|string, contents_unit: string} $data */
    public function get(array $data): string
    {
        if ($data['contents_unit'] === 'item') {
            // Protect direct action calls too: never silently truncate fractional items.
            if (! preg_match('/^\d+(?:\.0{1,3})?$/', (string) $data['contents'])) {
                throw ValidationException::withMessages([
                    'contents' => 'Item contents must be a whole number.',
                ]);
            }

            // Items use their actual count: both 12 and 12.000 produce item:12.
            $baseUnit = 'item';
            $normalizedContents = (int) $data['contents'];
        } else {
            // Weight and volume need a common unit and exact decimal handling.
            [$baseUnit, $normalizedContents] = $this->normalizeMeasurement(
                (string) $data['contents'],
                $data['contents_unit'],
            );
        }

        // Equivalent amounts share a key; different package types remain distinct.
        return $data['package_type'].':'.$baseUnit.':'.$normalizedContents;
    }

    /** @return array{string, int} */
    private function normalizeMeasurement(string $contents, string $unit): array
    {
        // Represent decimal amounts as exact thousandths using whole-number arithmetic.
        $parts = explode('.', $contents);
        $whole = (int) $parts[0];
        $fraction = (int) str_pad($parts[1] ?? '', 3, '0');
        $contentsInThousandths = ($whole * 1000) + $fraction;

        // Normalize weight to grams and volume to millilitres for duplicate checks.
        [$baseUnit, $multiplier] = match ($unit) {
            'g' => ['g', 1],
            'kg' => ['g', 1000],
            'ml' => ['ml', 1],
            'litre' => ['ml', 1000],
            // Refuse unsupported units if this helper is called outside a form request.
            default => throw ValidationException::withMessages([
                'contents_unit' => 'Select a supported contents unit.',
            ]),
        };

        return [$baseUnit, $contentsInThousandths * $multiplier];
    }
}
