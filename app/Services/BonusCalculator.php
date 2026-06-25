<?php

namespace App\Services;

/**
 * Flat-rate bonus calculation by amount band.
 *
 * All comparisons and the final multiplication are done in integer cents so
 * float boundary values (e.g. 1599.999…) cannot fall into the wrong band.
 * The lower bound of each band is inclusive.
 */
class BonusCalculator
{
    /**
     * @return array{amount: float, rate: float, band: string, bonus: float}
     */
    public function calculate(string|float|int $amount): array
    {
        $cents = (int) round(((float) $amount) * 100);

        [$rate, $band] = $this->resolveRateAndBand($cents);

        $bonusCents = (int) round($cents * $rate);

        return [
            'amount' => round($cents / 100, 2),
            'rate' => $rate,
            'band' => $band,
            'bonus' => round($bonusCents / 100, 2),
        ];
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function resolveRateAndBand(int $cents): array
    {
        return match (true) {
            $cents < 160_000 => [0.00, '0 – 1600'],
            $cents < 280_000 => [0.03, '1600 – 2800'],
            $cents < 400_000 => [0.04, '2800 – 4000'],
            $cents < 520_000 => [0.05, '4000 – 5200'],
            default => [0.06, '5200+'],
        };
    }
}
