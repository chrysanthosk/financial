<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ApplicationLogController extends Controller
{
    /** Never parse more than the last N bytes, to avoid memory exhaustion. */
    private const MAX_BYTES = 20 * 1024 * 1024; // 20 MB

    private const PER_PAGE = 25;

    /** Standard Monolog levels, most severe first. */
    private const LEVELS = [
        'emergency', 'alert', 'critical', 'error',
        'warning', 'notice', 'info', 'debug',
    ];

    public function index(Request $request)
    {
        $path = $this->logPath();

        $entries = $this->parse($path);

        // --- Filters ---
        $level = strtolower(trim((string) $request->query('level', '')));
        if ($level !== '') {
            $entries = array_filter($entries, fn ($e) => $e['level_lower'] === $level);
        }

        $from = (string) $request->query('from', '');
        if ($from !== '') {
            $entries = array_filter($entries, fn ($e) => $e['date'] >= $from);
        }

        $to = (string) $request->query('to', '');
        if ($to !== '') {
            $entries = array_filter($entries, fn ($e) => $e['date'] <= $to);
        }

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $entries = array_filter(
                $entries,
                fn ($e) => stripos($e['message']."\n".$e['trace'], $search) !== false
            );
        }

        $entries = array_values($entries);

        // --- Sorting ---
        $sort = (string) $request->query('sort', 'time');
        if (! in_array($sort, ['time', 'level', 'env', 'message'], true)) {
            $sort = 'time';
        }
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $factor = $direction === 'asc' ? 1 : -1;

        usort($entries, function (array $a, array $b) use ($sort, $factor) {
            $cmp = match ($sort) {
                'level' => $this->levelRank($a['level_lower']) <=> $this->levelRank($b['level_lower']),
                'env' => strcasecmp($a['env'], $b['env']),
                'message' => strcasecmp($a['message'], $b['message']),
                default => strcmp($a['timestamp'], $b['timestamp']),
            };

            return $factor * $cmp;
        });

        // --- Manual pagination ---
        $page = max(1, (int) $request->query('page', 1));
        $total = count($entries);
        $items = array_slice($entries, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $logs = (new LengthAwarePaginator(
            $items,
            $total,
            self::PER_PAGE,
            $page,
            ['path' => $request->url()]
        ))->withQueryString();

        return view('admin.settings.logs.index', [
            'logs' => $logs,
            'levels' => self::LEVELS,
            'sort' => $sort,
            'direction' => $direction,
            'fileExists' => is_file($path),
            'fileSize' => is_file($path) ? (int) filesize($path) : 0,
            'truncated' => is_file($path) && (int) filesize($path) > self::MAX_BYTES,
        ]);
    }

    /**
     * Severity rank for sorting (0 = most severe). Unknown levels sort last.
     */
    private function levelRank(string $level): int
    {
        $rank = array_flip(self::LEVELS);

        return $rank[$level] ?? 99;
    }

    private function logPath(): string
    {
        return config('logging.channels.single.path') ?: storage_path('logs/laravel.log');
    }

    /**
     * Parse the log file into entries (file order; index() applies ordering).
     *
     * @return array<int, array{date:string,timestamp:string,env:string,level:string,level_lower:string,message:string,trace:string}>
     */
    private function parse(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $content = $this->readTail($path);
        if ($content === '') {
            return [];
        }

        // [2026-05-22 12:04:33] local.ERROR: message
        $headerRe = '/^\[(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(?:\.\d+)?(?:[+-]\d{2}:?\d{2})?\]\s+([\w-]+)\.([A-Z]+):\s?(.*)$/';

        $entries = [];
        $current = null;

        foreach (preg_split('/\r?\n/', $content) as $line) {
            if (preg_match($headerRe, $line, $m)) {
                if ($current !== null) {
                    $entries[] = $this->finalize($current);
                }

                $current = [
                    'date' => $m[1],
                    'timestamp' => $m[1].' '.$m[2],
                    'env' => $m[3],
                    'level' => $m[4],
                    'message' => $m[5],
                    'trace_lines' => [],
                ];
            } elseif ($current !== null) {
                $current['trace_lines'][] = $line;
            }
            // Lines before the first header (e.g. a truncated tail) are ignored.
        }

        if ($current !== null) {
            $entries[] = $this->finalize($current);
        }

        return $entries; // file order (oldest first); ordering is applied by index()
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function finalize(array $c): array
    {
        $c['trace'] = trim(implode("\n", $c['trace_lines']));
        unset($c['trace_lines']);
        $c['level_lower'] = strtolower($c['level']);

        return $c;
    }

    private function readTail(string $path): string
    {
        $size = filesize($path);
        if ($size === false) {
            return '';
        }

        if ($size <= self::MAX_BYTES) {
            return (string) file_get_contents($path);
        }

        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return '';
        }

        fseek($fh, -self::MAX_BYTES, SEEK_END);
        $chunk = (string) fread($fh, self::MAX_BYTES);
        fclose($fh);

        // Drop the (likely partial) first line of the tail.
        $nl = strpos($chunk, "\n");

        return $nl === false ? $chunk : substr($chunk, $nl + 1);
    }
}
