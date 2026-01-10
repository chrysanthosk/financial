<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BonusController extends Controller
{
    public function index()
    {
        // Just render the page; no storage needed
        return view('bonus.index');
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $amount = (float) $validated['amount'];

        [$rate, $band] = $this->resolveRateAndBand($amount);

        $bonus = round($amount * $rate, 2);

        return view('bonus.index', [
            'amount' => $amount,
            'rate'   => $rate,
            'band'   => $band,
            'bonus'  => $bonus,
        ]);
    }

    /**
     * Ranges exactly as you described (flat rate based on band).
     * Lower bound is inclusive.
     */
    private function resolveRateAndBand(float $amount): array
    {
        if ($amount < 1600) {
            return [0.00, '0 – 1600'];
        }

        if ($amount < 2800) {
            return [0.03, '1600 – 2800'];
        }

        if ($amount < 4000) {
            return [0.04, '2800 – 4000'];
        }

        if ($amount < 5200) {
            return [0.05, '4000 – 5200'];
        }

        return [0.06, '5200+'];
    }
}
