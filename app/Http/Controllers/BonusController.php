<?php

namespace App\Http\Controllers;

use App\Services\BonusCalculator;
use Illuminate\Http\Request;

class BonusController extends Controller
{
    public function __construct(private BonusCalculator $calculator) {}

    public function index()
    {
        // Just render the page; no storage needed
        return view('bonus.index');
    }

    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
        ]);

        return view('bonus.index', $this->calculator->calculate($validated['amount']));
    }
}
