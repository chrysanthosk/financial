<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ImportController;
use App\Models\IncomeSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ImportIncomeWideTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    private function validate(array $rows): array
    {
        $method = new ReflectionMethod(ImportController::class, 'validateIncomeWideRows');
        $method->setAccessible(true);

        return $method->invoke(
            app(ImportController::class),
            $rows,
            'Date',
            ['Cash', 'Card'],
            ['Date' => 'Date', 'Cash' => 'Cash', 'Card' => 'Card'],
            null
        );
    }

    public function test_explicit_zero_is_a_candidate_but_blank_is_skipped(): void
    {
        IncomeSource::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);
        IncomeSource::create(['name' => 'Card', 'sort_order' => 2, 'is_active' => true]);

        $result = $this->validate([
            ['Date' => '2026-05-01', 'Cash' => '0', 'Card' => ''],
        ]);

        $this->assertSame(1, $result['summary']['skipped_blank']);
        $this->assertSame(1, $result['summary']['candidates']);
        $this->assertSame(1, $result['summary']['valid']);
        $this->assertSame(0, $result['summary']['invalid']);

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Cash', $result['rows'][0]['col']);
        $this->assertSame(0.0, $result['rows'][0]['data']['amount']);
        $this->assertSame([], $result['rows'][0]['errors']);
    }

    public function test_negative_amount_is_reported_as_invalid(): void
    {
        IncomeSource::create(['name' => 'Cash', 'sort_order' => 1, 'is_active' => true]);
        IncomeSource::create(['name' => 'Card', 'sort_order' => 2, 'is_active' => true]);

        $result = $this->validate([
            ['Date' => '2026-05-01', 'Cash' => '-10', 'Card' => '5'],
        ]);

        $this->assertSame(1, $result['summary']['invalid']);
        $this->assertSame(1, $result['summary']['valid']);
        $this->assertContains('Negative amount', $result['rows'][0]['errors']);
    }
}
