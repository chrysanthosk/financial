<?php

namespace App\Http\Controllers;

use App\Models\Income;
use App\Models\IncomeSource;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->string('month')->toString();

        // Default = current month (YYYY-MM)
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        // Active sources (columns)
        $sources = IncomeSource::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $sourceIds = $sources->pluck('id')->all();

        // Pull all income rows for the month (we’ll pivot in PHP)
        $rows = Income::query()
            ->with(['source'])
            ->whereBetween('income_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('income_source_id', $sourceIds)
            ->orderBy('income_date')
            ->orderBy('income_source_id')
            ->get();

        // Build a quick lookup for "cell -> income id" (so edit/delete works)
        // If duplicates exist for same date+source, we keep the latest id for actions, but sum amounts.
        $cellId = [];      // ["YYYY-MM-DD"][source_id] => income_id
        $cellNote = [];    // optional: last note (for tooltip)
        $pivot = [];       // ["YYYY-MM-DD"][source_id] => sum(amount)

        foreach ($rows as $r) {
            $d = Carbon::parse($r->income_date)->toDateString();
            $sid = (int)$r->income_source_id;

            if (!isset($pivot[$d][$sid])) $pivot[$d][$sid] = 0.0;
            $pivot[$d][$sid] += (float)$r->amount;

            // keep latest record id/note for actions
            $cellId[$d][$sid] = $r->id;
            $cellNote[$d][$sid] = (string)($r->note ?? '');
        }

        // Generate all days for the month (so empty days show as 0)
        $days = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $days[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // Totals per source (columns)
        $colTotals = [];
        foreach ($sourceIds as $sid) $colTotals[$sid] = 0.0;

        // Totals per day (rows) + month total
        $rowTotals = []; // ["YYYY-MM-DD"] => total
        $monthTotal = 0.0;

        foreach ($days as $d) {
            $rowTotals[$d] = 0.0;
            foreach ($sourceIds as $sid) {
                $amt = (float)($pivot[$d][$sid] ?? 0.0);
                $rowTotals[$d] += $amt;
                $colTotals[$sid] += $amt;
                $monthTotal += $amt;
            }
        }

        return view('income.index', [
            'month'       => $month,
            'sources'     => $sources,
            'days'        => $days,
            'pivot'       => $pivot,
            'cellId'      => $cellId,
            'cellNote'    => $cellNote,
            'rowTotals'   => $rowTotals,
            'colTotals'   => $colTotals,
            'monthTotal'  => $monthTotal,
            'sourceId'    => null,
        ]);
    }

    public function create()
    {
        $sources = IncomeSource::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('income.create', [
            'sources' => $sources,
            'defaultDate' => now()->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $sources = IncomeSource::where('is_active', true)->pluck('id')->all();

        $validated = $request->validate([
            'income_date' => ['required', 'date'],
            'income_source_id' => ['required', Rule::in($sources)],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['created_by'] = Auth::id();

        $income = Income::create($validated);

        Audit::log(
            action: 'income.created',
            category: 'income',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Income',
            targetId: (string)$income->id,
            meta: [
                'income_date' => (string)$income->income_date,
                'income_source_id' => (int)$income->income_source_id,
                'amount' => (float)$income->amount,
                'note_present' => !empty($income->note),
            ]
        );

        return redirect()->route('income.index')->with('status', 'Income added successfully.');
    }

    public function edit(Income $income)
    {
        $sources = IncomeSource::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('income.edit', [
            'income' => $income->load('source'),
            'sources' => $sources,
        ]);
    }

    public function update(Request $request, Income $income)
    {
        $sources = IncomeSource::where('is_active', true)->pluck('id')->all();

        $validated = $request->validate([
            'income_date' => ['required', 'date'],
            'income_source_id' => ['required', Rule::in($sources)],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $before = [
            'income_date' => (string)$income->income_date,
            'income_source_id' => (int)$income->income_source_id,
            'amount' => (float)$income->amount,
            'note' => (string)($income->note ?? ''),
        ];

        $income->update($validated);

        $after = [
            'income_date' => (string)$income->income_date,
            'income_source_id' => (int)$income->income_source_id,
            'amount' => (float)$income->amount,
            'note' => (string)($income->note ?? ''),
        ];

        Audit::log(
            action: 'income.updated',
            category: 'income',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Income',
            targetId: (string)$income->id,
            meta: [
                'changed' => [
                    'income_date' => $before['income_date'] !== $after['income_date'],
                    'income_source_id' => $before['income_source_id'] !== $after['income_source_id'],
                    'amount' => $before['amount'] !== $after['amount'],
                    'note' => $before['note'] !== $after['note'],
                ],
                'before' => [
                    'income_date' => $before['income_date'],
                    'income_source_id' => $before['income_source_id'],
                    'amount' => $before['amount'],
                    // keep note minimal (don’t store full free-text unless you want to)
                    'note_present' => $before['note'] !== '',
                ],
                'after' => [
                    'income_date' => $after['income_date'],
                    'income_source_id' => $after['income_source_id'],
                    'amount' => $after['amount'],
                    'note_present' => $after['note'] !== '',
                ],
            ]
        );

        return redirect()->route('income.index')->with('status', 'Income updated successfully.');
    }

    public function destroy(Request $request, Income $income)
    {
        $meta = [
            'income_date' => (string)$income->income_date,
            'income_source_id' => (int)$income->income_source_id,
            'amount' => (float)$income->amount,
            'note_present' => !empty($income->note),
        ];

        $id = (string)$income->id;

        $income->delete();

        Audit::log(
            action: 'income.deleted',
            category: 'income',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'Income',
            targetId: $id,
            meta: $meta
        );

        return redirect()->route('income.index')->with('status', 'Income deleted successfully.');
    }
}
