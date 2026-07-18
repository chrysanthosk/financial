<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeSource;
use App\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ImportController extends Controller
{
    /** Maximum upload size in kilobytes (10 MB). */
    private const MAX_FILE_KB = 10240;

    /** Maximum number of data rows accepted in a single import. */
    private const MAX_IMPORT_ROWS = 5000;

    public function index()
    {
        return view('admin.settings.import.index');
    }

    public function showUpload(string $type)
    {
        $type = $this->normalizeType($type);

        return view('admin.settings.import.upload', [
            'type' => $type,
            'title' => $type === 'income' ? 'Import Income from Excel' : 'Import Expenses from Excel',
        ]);
    }

    public function handleUpload(Request $request, string $type)
    {
        $type = $this->normalizeType($type);

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:'.self::MAX_FILE_KB,
            'has_header' => 'nullable|boolean',
        ]);

        $uuid = (string) Str::uuid();
        $dir = "imports/{$uuid}";

        // Don't interpolate the client-supplied extension into the stored path;
        // constrain it to the validated set (mimes already passed above).
        $ext = strtolower($request->file('file')->getClientOriginalExtension());
        $ext = in_array($ext, ['xlsx', 'xls', 'csv'], true) ? $ext : 'xlsx';

        $path = $request->file('file')->storeAs($dir, 'upload.'.$ext);

        [$headers, $sampleRows] = $this->readHeadersAndSample($path, (bool) $request->boolean('has_header', true));

        session([
            "import.{$type}.uuid" => $uuid,
            "import.{$type}.file" => $path,
            "import.{$type}.has_header" => (bool) $request->boolean('has_header', true),
            "import.{$type}.headers" => $headers,
        ]);

        if ($type === 'income') {
            return view('admin.settings.import.mapping_income_wide', [
                'type' => $type,
                'title' => 'Map Income Columns',
                'headers' => $headers,
                'sampleRows' => $sampleRows,
                'defaultYear' => (int) date('Y'),
            ]);
        }

        return view('admin.settings.import.mapping', [
            'type' => $type,
            'title' => 'Map Expense Columns',
            'headers' => $headers,
            'sampleRows' => $sampleRows,
            'requiredFields' => $this->requiredFields($type),
            'optionalFields' => $this->optionalFields($type),
        ]);
    }

    public function preview(Request $request, string $type)
    {
        $type = $this->normalizeType($type);
        $uuid = session("import.{$type}.uuid");
        $file = session("import.{$type}.file");
        $hasHeader = (bool) session("import.{$type}.has_header", true);
        $headers = session("import.{$type}.headers", []);

        if (! $uuid || ! $file) {
            return redirect()->route('tools.import.index')
                ->with('error', 'Import session expired. Please upload the file again.');
        }

        // INCOME (wide)
        if ($type === 'income') {
            $dateCol = $request->input('date_col');
            $sourceCols = (array) $request->input('source_cols', []);
            $year = $request->input('year');

            if (! $dateCol) {
                return back()->with('error', 'Please select the Date column.');
            }

            $sourceCols = array_values(array_filter($sourceCols, fn ($c) => trim((string) $c) !== ''));
            if (count($sourceCols) === 0) {
                return back()->with('error', 'Please select at least one income source column.');
            }

            $yearInt = null;
            if ($year !== null && $year !== '') {
                $yearInt = (int) $year;
                if ($yearInt < 1900 || $yearInt > 2100) {
                    return back()->with('error', 'Year must be between 1900 and 2100.');
                }
            }

            $rows = $this->readAllRows($file, $hasHeader);
            if (count($rows) === 0) {
                return back()->with('error', 'No rows to preview.');
            }

            $result = $this->validateIncomeWideRows($rows, $dateCol, $sourceCols, $headers, $yearInt);

            $jsonPath = "imports/{$uuid}/preview.json";
            Storage::put($jsonPath, json_encode($result, JSON_UNESCAPED_UNICODE));

            session([
                "import.{$type}.mapping" => [
                    'date_col' => $dateCol,
                    'source_cols' => $sourceCols,
                    'year' => $yearInt,
                ],
                "import.{$type}.preview" => $jsonPath,
            ]);

            return view('admin.settings.import.preview', [
                'type' => $type,
                'title' => 'Preview Income Import',
                'mapping' => session("import.{$type}.mapping"),
                'summary' => $result['summary'],
                'rows' => array_slice($result['rows'], 0, 80),
                'hasMore' => count($result['rows']) > 80,
            ]);
        }

        // EXPENSES (classic)
        $mapping = $request->input('map', []);
        $this->validateMapping($type, $mapping);

        $rows = $this->readAllRows($file, $hasHeader);
        if (count($rows) === 0) {
            return back()->with('error', 'No rows to preview.');
        }

        $result = $this->validateExpenseRows($rows, $mapping);

        $jsonPath = "imports/{$uuid}/preview.json";
        Storage::put($jsonPath, json_encode($result, JSON_UNESCAPED_UNICODE));

        session([
            "import.{$type}.mapping" => $mapping,
            "import.{$type}.preview" => $jsonPath,
        ]);

        return view('admin.settings.import.preview', [
            'type' => $type,
            'title' => 'Preview Expense Import',
            'mapping' => $mapping,
            'summary' => $result['summary'],
            'rows' => array_slice($result['rows'], 0, 80),
            'hasMore' => count($result['rows']) > 80,
        ]);
    }

    public function commit(Request $request, string $type)
    {
        $type = $this->normalizeType($type);
        $uuid = session("import.{$type}.uuid");
        $jsonPath = session("import.{$type}.preview");

        if (! $uuid || ! $jsonPath || ! Storage::exists($jsonPath)) {
            return redirect()->route('tools.import.index')
                ->with('error', 'Nothing to import. Please run preview first.');
        }

        $payload = json_decode(Storage::get($jsonPath), true) ?? [];
        $rows = $payload['rows'] ?? [];

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        // Safety: refresh allowed FK ids at commit time (prevents stale preview from breaking)
        $validIncomeSourceIds = IncomeSource::query()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $validExpenseCatIds = ExpenseCategory::query()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $validPaymentIds = PaymentMethod::query()->pluck('id')->map(fn ($v) => (int) $v)->all();

        $defaultExpenseCatId = $this->getOrCreateExpenseOtherCategoryId();
        $defaultPaymentId = $this->getOrCreatePaymentOtherMethodId();

        DB::beginTransaction();
        try {
            $userId = Auth::id();

            foreach ($rows as $r) {
                if (! empty($r['errors'])) {
                    $skipped++;

                    continue;
                }

                try {
                    if ($type === 'income') {
                        $sid = (int) ($r['data']['income_source_id'] ?? 0);
                        if (! $sid || ! in_array($sid, $validIncomeSourceIds, true)) {
                            // invalid FK -> skip (should never happen if preview is correct)
                            $skipped++;

                            continue;
                        }

                        // Income is unique per (date, source), so re-importing a month
                        // overwrites the existing rows instead of hitting the unique index.
                        $income = Income::firstOrNew([
                            'income_date' => $r['data']['income_date'],
                            'income_source_id' => $sid,
                        ]);

                        if (! $income->exists) {
                            $income->created_by = $userId;
                        }

                        $income->amount = $r['data']['amount'];
                        $income->note = $r['data']['note'] ?? null;
                        $income->save();
                    } else {
                        $catId = (int) ($r['data']['expense_category_id'] ?? 0);
                        if (! $catId || ! in_array($catId, $validExpenseCatIds, true)) {
                            $catId = $defaultExpenseCatId;
                        }

                        $pmId = (int) ($r['data']['payment_method_id'] ?? 0);
                        if (! $pmId || ! in_array($pmId, $validPaymentIds, true)) {
                            $pmId = $defaultPaymentId;
                        }

                        Expense::create([
                            'expense_date' => $r['data']['expense_date'],
                            'payee_name' => $r['data']['payee_name'],
                            'expense_category_id' => $catId,
                            'payment_method_id' => $pmId,
                            'amount' => $r['data']['amount'],
                            'cheque_no' => $r['data']['cheque_no'] ?? null,
                            'reason' => $r['data']['reason'] ?? null,
                            'created_by' => $userId,
                        ]);
                    }

                    $imported++;
                } catch (\Throwable $rowEx) {
                    // Row failed – do NOT break whole import
                    $failed++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        try {
            Storage::deleteDirectory("imports/{$uuid}");
        } catch (\Throwable $e) {
            // ignore
        }

        session()->forget("import.{$type}");

        $msg = "Import complete. Imported {$imported} row(s), skipped {$skipped} row(s).";
        if ($failed > 0) {
            $msg .= " Failed {$failed} row(s) (unexpected).";
        }

        return redirect()->route('tools.import.index')->with('success', $msg);
    }

    // -------------------------
    // Internals
    // -------------------------

    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (in_array($type, ['income', 'incomes'], true)) {
            return 'income';
        }
        if (in_array($type, ['expense', 'expenses'], true)) {
            return 'expenses';
        }
        abort(404);
    }

    private function requiredFields(string $type): array
    {
        return [
            'expense_date' => 'Date',
            'payee_name' => 'Payee / Company',
            'expense_category' => 'Expense Category (name or id)',
            'payment_method' => 'Payment Method (name or id)',
            'amount' => 'Amount',
        ];
    }

    private function optionalFields(string $type): array
    {
        return [
            'cheque_no' => 'Cheque No (optional / required if method is Cheque)',
            'reason' => 'Reason (optional)',
        ];
    }

    private function validateMapping(string $type, array $mapping): void
    {
        foreach (array_keys($this->requiredFields($type)) as $f) {
            if (empty($mapping[$f])) {
                abort(422, "Missing mapping for required field: {$f}");
            }
        }
    }

    /**
     * Load a spreadsheet reading values only (no styles/formatting), which
     * keeps memory down on large or hostile uploads.
     */
    private function loadSpreadsheet(string $abs): Spreadsheet
    {
        $reader = IOFactory::createReaderForFile($abs);
        $reader->setReadDataOnly(true);

        return $reader->load($abs);
    }

    private function readHeadersAndSample(string $storedPath, bool $hasHeader): array
    {
        $abs = Storage::path($storedPath);
        $spreadsheet = $this->loadSpreadsheet($abs);
        $sheet = $spreadsheet->getActiveSheet();

        $maxCol = $sheet->getHighestColumn();
        $maxRow = min(12, (int) $sheet->getHighestRow());

        // formatted for UI
        $rows = $sheet->rangeToArray("A1:{$maxCol}{$maxRow}", null, true, true, true);

        if (empty($rows)) {
            return [[], []];
        }

        $first = array_shift($rows);

        $headers = [];
        if ($hasHeader) {
            foreach ($first as $col => $val) {
                $label = trim((string) $this->stringifyCell($val));
                $headers[$col] = $label !== '' ? $label : $col;
            }
        } else {
            foreach ($first as $col => $_val) {
                $headers[$col] = $col;
            }
            array_unshift($rows, $first);
        }

        return [$headers, $rows];
    }

    private function readAllRows(string $storedPath, bool $hasHeader): array
    {
        $abs = Storage::path($storedPath);
        $spreadsheet = $this->loadSpreadsheet($abs);
        $sheet = $spreadsheet->getActiveSheet();

        $maxCol = $sheet->getHighestColumn();
        $maxRow = (int) $sheet->getHighestRow();

        // Guard against memory exhaustion before loading the whole range.
        $dataRows = $hasHeader ? max(0, $maxRow - 1) : $maxRow;
        if ($dataRows > self::MAX_IMPORT_ROWS) {
            abort(422, "Import file has too many rows ({$dataRows}). The maximum is "
                .self::MAX_IMPORT_ROWS.'. Please split the file and import in batches.');
        }

        // RAW values (important!)
        $rows = $sheet->rangeToArray("A1:{$maxCol}{$maxRow}", null, true, false, true);

        if ($hasHeader && ! empty($rows)) {
            array_shift($rows);
        }

        return array_values(array_filter($rows, function ($r) {
            foreach ($r as $v) {
                if (trim((string) $this->stringifyCell($v)) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    private function stringifyCell($value): string
    {
        if ($value instanceof RichText) {
            return $value->getPlainText();
        }

        return (string) $value;
    }

    private function normalizeDateValue($value, ?int $fallbackYear = null): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            try {
                $dt = ExcelDate::excelToDateTimeObject((float) $value);

                return Carbon::instance($dt)->toDateString();
            } catch (\Throwable $e) {
            }
        }

        $s = trim((string) $value);
        if ($s === '') {
            return null;
        }

        $formatsWithYear = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y', 'Y/m/d', 'd.m.Y'];
        foreach ($formatsWithYear as $f) {
            try {
                return Carbon::createFromFormat($f, $s)->toDateString();
            } catch (\Throwable $e) {
            }
        }

        if ($fallbackYear) {
            if (str_contains($s, ',')) {
                $parts = explode(',', $s, 2);
                $s = trim($parts[1] ?? $s);
            }
            $s = preg_replace('/^[^\d]+/', '', $s);
            $s = trim($s);

            if ($s !== '') {
                $noYearFormats = ['j F', 'd F', 'j M', 'd M', 'j/n', 'd/m', 'j-n', 'd-m', 'j.m', 'd.m'];
                foreach ($noYearFormats as $nf) {
                    try {
                        return Carbon::createFromFormat($nf.' Y', $s.' '.$fallbackYear)->toDateString();
                    } catch (\Throwable $e) {
                    }
                }
                if (preg_match('/^(\d{1,2})\s+([A-Za-z]+)/', $s, $m)) {
                    try {
                        return Carbon::createFromFormat('j F Y', "{$m[1]} {$m[2]} {$fallbackYear}")->toDateString();
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        try {
            return Carbon::parse($s)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeAmount($value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = trim($this->stringifyCell($value));
        if ($s === '') {
            return null;
        }

        $s = str_replace(["\u{00A0}", ' '], '', $s);
        $s = preg_replace('/[^\d,\.\-]/u', '', $s) ?? '';
        if ($s === '' || $s === '-') {
            return null;
        }

        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace(',', '', $s);
        } elseif (str_contains($s, ',') && ! str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function validateIncomeWideRows(array $rows, string $dateCol, array $sourceCols, array $headers, ?int $year = null): array
    {
        $summary = [
            'total_cells_checked' => 0,
            'candidates' => 0,
            'valid' => 0,
            'invalid' => 0,
            'skipped_blank' => 0,
        ];

        $incomeSourcesByName = IncomeSource::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($s) => [mb_strtolower(trim($s->name)) => (int) $s->id])
            ->toArray();

        $out = [];

        foreach ($rows as $idx => $row) {
            $date = $this->normalizeDateValue($row[$dateCol] ?? null, $year);

            foreach ($sourceCols as $col) {
                $summary['total_cells_checked']++;

                $amount = $this->normalizeAmount($row[$col] ?? null);

                // A blank cell means "no entry"; an explicit 0 is a real 0.00 entry.
                if ($amount === null) {
                    $summary['skipped_blank']++;

                    continue;
                }

                $summary['candidates']++;

                $errors = [];
                if (! $date) {
                    $errors[] = 'Invalid date';
                }

                if ($amount < 0) {
                    $errors[] = 'Negative amount';
                }

                $sourceName = $headers[$col] ?? $col;
                $sourceKey = mb_strtolower(trim((string) $sourceName));
                $sourceId = $incomeSourcesByName[$sourceKey] ?? null;

                if (! $sourceId) {
                    $errors[] = "Unknown income source '{$sourceName}' (create it in Settings → Configuration → Income Sources)";
                }

                $data = [
                    'income_date' => $date,
                    'amount' => $amount,
                    'income_source_id' => $sourceId,
                    'note' => null,
                    'source_name' => $sourceName,
                ];

                if (empty($errors)) {
                    $summary['valid']++;
                } else {
                    $summary['invalid']++;
                }

                $out[] = [
                    'row' => $idx + 1,
                    'col' => $col,
                    'errors' => $errors,
                    'data' => $data,
                ];
            }
        }

        return ['summary' => $summary, 'rows' => $out];
    }

    private function validateExpenseRows(array $rows, array $mapping): array
    {
        $summary = ['total' => 0, 'valid' => 0, 'invalid' => 0];
        $out = [];

        $cats = ExpenseCategory::query()->get(['id', 'name']);
        $expenseCategoriesByName = $cats->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->name)) => (int) $c->id])->toArray();

        $methods = PaymentMethod::query()->get(['id', 'name']);
        $paymentMethodsByName = $methods->mapWithKeys(fn ($m) => [mb_strtolower(trim($m->name)) => (int) $m->id])->toArray();

        $defaultCatId = $this->getOrCreateExpenseOtherCategoryId();
        $defaultMethodId = $this->getOrCreatePaymentOtherMethodId();

        foreach ($rows as $idx => $row) {
            $summary['total']++;
            $errors = [];

            $date = $this->normalizeDateValue($row[$mapping['expense_date']] ?? null);
            if (! $date) {
                $errors[] = 'Invalid date';
            }

            $amount = $this->normalizeAmount($row[$mapping['amount']] ?? null);
            if ($amount === null || $amount <= 0) {
                $errors[] = 'Invalid amount';
            }

            $payee = trim($this->stringifyCell($row[$mapping['payee_name']] ?? ''));
            if ($payee === '') {
                $errors[] = 'Missing payee/company';
            }

            // Category: if unknown -> default to Other (NOT invalid)
            $catId = $this->resolveIdByNameOrId($row[$mapping['expense_category']] ?? null, $expenseCategoriesByName);
            if (! $catId) {
                $catId = $defaultCatId;
            }

            // Method: if unknown -> default to Other (NOT invalid)
            $methodId = $this->resolveIdByNameOrId($row[$mapping['payment_method']] ?? null, $paymentMethodsByName);
            if (! $methodId) {
                $methodId = $defaultMethodId;
            }

            $data = [
                'expense_date' => $date,
                'amount' => $amount,
                'payee_name' => $payee,
                'expense_category_id' => $catId,
                'payment_method_id' => $methodId,
                'expense_category_name' => $this->lookupNameById($catId, $cats),
                'payment_method_name' => $this->lookupNameById($methodId, $methods),
            ];

            if (! empty($mapping['cheque_no'])) {
                $cheque = trim($this->stringifyCell($row[$mapping['cheque_no']] ?? ''));
                if ($cheque !== '') {
                    $data['cheque_no'] = $cheque;
                }
            }

            if (! empty($mapping['reason'])) {
                $reason = trim($this->stringifyCell($row[$mapping['reason']] ?? ''));
                if ($reason !== '') {
                    $data['reason'] = $reason;
                }
            }

            $methodNameLower = mb_strtolower(trim((string) ($data['payment_method_name'] ?? '')));
            if ($methodNameLower === 'cheque' && empty($data['cheque_no'])) {
                $errors[] = 'Cheque No required for Cheque payments';
            }

            if (empty($errors)) {
                $summary['valid']++;
            } else {
                $summary['invalid']++;
            }

            $out[] = [
                'row' => $idx + 1,
                'errors' => $errors,
                'data' => $data,
            ];
        }

        return ['summary' => $summary, 'rows' => $out];
    }

    /**
     * IMPORTANT: numeric values are treated as IDs ONLY if they exist in DB ids.
     */
    private function resolveIdByNameOrId($raw, array $nameToId): ?int
    {
        if ($raw === null) {
            return null;
        }

        $allowedIds = array_values($nameToId);

        if (is_numeric($raw)) {
            $id = (int) $raw;

            return ($id > 0 && in_array($id, $allowedIds, true)) ? $id : null;
        }

        $name = mb_strtolower(trim($this->stringifyCell($raw)));
        if ($name === '') {
            return null;
        }

        return $nameToId[$name] ?? null;
    }

    private function lookupNameById(int $id, $collection): string
    {
        $found = $collection->firstWhere('id', $id);

        return $found ? (string) $found->name : '';
    }

    private function getOrCreateExpenseOtherCategoryId(): int
    {
        $name = 'Other';
        $existing = ExpenseCategory::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        // If your ExpenseCategory model requires extra fields, tell me and I’ll adjust.
        $cat = ExpenseCategory::create(['name' => $name]);

        return (int) $cat->id;
    }

    private function getOrCreatePaymentOtherMethodId(): int
    {
        $name = 'Other';
        $existing = PaymentMethod::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $pm = PaymentMethod::create(['name' => $name]);

        return (int) $pm->id;
    }
}
