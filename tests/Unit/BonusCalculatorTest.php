<?php

namespace Tests\Unit;

use App\Services\BonusCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BonusCalculatorTest extends TestCase
{
    private function calc(): BonusCalculator
    {
        return new BonusCalculator;
    }

    #[DataProvider('bandBoundaryProvider')]
    public function test_band_boundaries_map_to_correct_rate(float $amount, float $expectedRate): void
    {
        $result = $this->calc()->calculate($amount);

        $this->assertSame($expectedRate, $result['rate'], "amount {$amount} should be rate {$expectedRate}");
    }

    public static function bandBoundaryProvider(): array
    {
        return [
            'just below 1600' => [1599.99, 0.00],
            'exactly 1600' => [1600.00, 0.03],
            'just below 2800' => [2799.99, 0.03],
            'exactly 2800' => [2800.00, 0.04],
            'exactly 4000' => [4000.00, 0.05],
            'exactly 5200' => [5200.00, 0.06],
        ];
    }

    public function test_bonus_amount_is_rate_times_amount(): void
    {
        $result = $this->calc()->calculate(2000);

        $this->assertSame(0.03, $result['rate']);
        $this->assertSame(60.00, $result['bonus']);
        $this->assertSame(2000.00, $result['amount']);
    }

    public function test_zero_amount_is_zero_rate(): void
    {
        $result = $this->calc()->calculate(0);

        $this->assertSame(0.00, $result['rate']);
        $this->assertSame(0.00, $result['bonus']);
    }
}
