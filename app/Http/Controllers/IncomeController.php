<?php

namespace App\Http\Controllers;

use App\Http\Requests\IncomeRequest;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->string('month')->toString();

        // Default = current month (YYYY-MM)
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = (clone $start)->endOfMonth();

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
            $sid = (int) $r->income_source_id;

            if (! isset($pivot[$d][$sid])) {
                $pivot[$d][$sid] = 0.0;
            }
            $pivot[$d][$sid] += (float) $r->amount;

            // keep latest record id/note for actions
            $cellId[$d][$sid] = $r->id;
            $cellNote[$d][$sid] = (string) ($r->note ?? '');
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
        foreach ($sourceIds as $sid) {
            $colTotals[$sid] = 0.0;
        }

        // Totals per day (rows) + month total
        $rowTotals = []; // ["YYYY-MM-DD"] => total
        $monthTotal = 0.0;

        foreach ($days as $d) {
            $rowTotals[$d] = 0.0;
            foreach ($sourceIds as $sid) {
                $amt = (float) ($pivot[$d][$sid] ?? 0.0);
                $rowTotals[$d] += $amt;
                $colTotals[$sid] += $amt;
                $monthTotal += $amt;
            }
        }

        return view('income.index', [
            'month' => $month,
            'sources' => $sources,
            'days' => $days,
            'pivot' => $pivot,
            'cellId' => $cellId,
            'cellNote' => $cellNote,
            'rowTotals' => $rowTotals,
            'colTotals' => $colTotals,
            'monthTotal' => $monthTotal,
            // A day of all-zero entries is still data, so drive the empty state
            // off row existence rather than off the month total.
            'hasAny' => $rows->isNotEmpty(),
            'sourceId' => null,
        ]);
    }

    /**
     * Grid form for a single date: one amount field per active source.
     * Doubles as the edit screen — existing amounts for the date are pre-filled.
     */
    public function create(Request $request)
    {
        $date = $request->string('date')->toString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->toDateString();
        }

        $sources = IncomeSource::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $existing = Income::query()
            ->whereDate('income_date', $date)
            ->get();

        // ["source_id" => amount] for the fields, plus the day's shared note.
        $amounts = [];
        foreach ($existing as $row) {
            $amounts[(int) $row->income_source_id] = number_format((float) $row->amount, 2, '.', '');
        }

        $noteRow = $existing->first(fn ($r) => ! empty($r->note));
        $note = $noteRow ? (string) $noteRow->note : '';

        return view('income.create', [
            'sources' => $sources,
            'date' => $date,
            'amounts' => $amounts,
            'note' => $note,
            'isEdit' => $existing->isNotEmpty(),
        ]);
    }

    /**
     * Upsert every source for the given date in one shot.
     * Zero is a real value here, so a 0.00 field stores a 0.00 row.
     */
    public function store(IncomeRequest $request)
    {
        $validated = $request->validated();

        $date = Carbon::parse($validated['income_date'])->toDateString();
        $note = $validated['note'] ?? null;

        $created = 0;
        $updated = 0;
        $meta = [];

        DB::transaction(function () use ($validated, $date, $note, &$created, &$updated, &$meta) {
            foreach ($validated['amounts'] as $sourceId => $amount) {
                $income = Income::firstOrNew([
                    'income_date' => $date,
                    'income_source_id' => (int) $sourceId,
                ]);

                $income->exists ? $updated++ : $created++;

                if (! $income->exists) {
                    $income->created_by = Auth::id();
                }

                $income->amount = (float) $amount;
                $income->note = $note;
                $income->save();

                $meta[(int) $sourceId] = number_format((float) $amount, 2, '.', '');
            }
        });

        Audit::log(
            action: 'income.day_saved',
            category: 'income',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'IncomeDay',
            targetId: $date,
            meta: [
                'income_date' => $date,
                'created' => $created,
                'updated' => $updated,
                'amounts' => $meta,
                'note_present' => ! empty($note),
            ]
        );

        return redirect()
            ->route('income.index', ['month' => Carbon::parse($date)->format('Y-m')])
            ->with('status', 'Income for '.$date.' saved successfully.');
    }

    /**
     * Remove every income row for a given date (the row-level delete on the listing).
     */
    public function destroyDay(Request $request, string $date)
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(404);
        }

        $rows = Income::whereDate('income_date', $date)->get();

        if ($rows->isEmpty()) {
            return redirect()
                ->route('income.index', ['month' => Carbon::parse($date)->format('Y-m')])
                ->with('status', 'No income entries found for '.$date.'.');
        }

        $meta = [];
        foreach ($rows as $row) {
            $meta[(int) $row->income_source_id] = number_format((float) $row->amount, 2, '.', '');
        }

        DB::transaction(fn () => Income::whereDate('income_date', $date)->delete());

        Audit::log(
            action: 'income.day_deleted',
            category: 'income',
            request: $request,
            userId: $request->user()?->id,
            targetType: 'IncomeDay',
            targetId: $date,
            meta: [
                'income_date' => $date,
                'deleted' => $rows->count(),
                'amounts' => $meta,
            ]
        );

        return redirect()
            ->route('income.index', ['month' => Carbon::parse($date)->format('Y-m')])
            ->with('status', 'Income for '.$date.' deleted successfully.');
    }
}
