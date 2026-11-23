<?php

namespace App\Services\Sistema;

use Carbon\CarbonImmutable;

class SystemLogReader
{
    private const HEADER_PATTERN = '/^\[(?<timestamp>[^\]]+)]\s+(?<environment>[^.\s]+)\.(?<level>[A-Z]+):\s*(?<message>.*)$/';

    public function __construct(
        private readonly ?string $logPath = null,
        private readonly int $maxBytes = 1048576,
    ) {
    }

    public function read(array $filters = []): array
    {
        $path = $this->path();
        $limit = max(20, min((int) ($filters['limit'] ?? 100), 200));
        $level = strtolower((string) ($filters['level'] ?? 'all'));
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $hours = max(1, min((int) ($filters['hours'] ?? 24), 168));
        $minimumDate = CarbonImmutable::now()->subHours($hours);

        if (! is_file($path) || ! is_readable($path)) {
            return $this->emptyResult($path, 'Arquivo de log indisponível ou sem permissão de leitura.');
        }

        $content = $this->tail($path);
        $entries = $this->parse($content);
        $summary = array_fill_keys(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'], 0);
        $filtered = [];

        foreach (array_reverse($entries) as $entry) {
            $entryLevel = $entry['level'];

            try {
                $date = CarbonImmutable::parse($entry['timestamp']);
            } catch (\Throwable) {
                $date = null;
            }

            if ($date && $date->lessThan($minimumDate)) {
                continue;
            }

            $summary[$entryLevel] = ($summary[$entryLevel] ?? 0) + 1;

            if ($level !== 'all' && $entryLevel !== $level) {
                continue;
            }

            $searchable = mb_strtolower($entry['message'].' '.$entry['context'].' '.$entry['source']);
            if ($search !== '' && ! str_contains($searchable, $search)) {
                continue;
            }

            $filtered[] = $entry;
            if (count($filtered) >= $limit) {
                break;
            }
        }

        return [
            'entries' => $filtered,
            'summary' => $summary,
            'meta' => [
                'path' => 'storage/logs/'.basename($path),
                'size_bytes' => filesize($path) ?: 0,
                'modified_at' => ($modified = filemtime($path)) ? date(DATE_ATOM, $modified) : null,
                'scanned_entries' => count($entries),
                'returned_entries' => count($filtered),
                'tail_bytes' => strlen($content),
                'truncated' => (filesize($path) ?: 0) > strlen($content),
            ],
            'error' => null,
        ];
    }

    public function recentSeverityCounts(int $hours = 1): array
    {
        $result = $this->read(['hours' => $hours, 'limit' => 200]);

        return [
            'critical' => (int) ($result['summary']['critical'] ?? 0),
            'error' => (int) ($result['summary']['error'] ?? 0),
            'warning' => (int) ($result['summary']['warning'] ?? 0),
        ];
    }

    public function sanitize(string $value): string
    {
        $value = preg_replace('/(Authorization:\s*Bearer\s+)[^\s,]+/i', '$1[PROTEGIDO]', $value) ?? $value;
        $value = preg_replace('/(["\']?(?:password|senha|secret|token|api[_-]?key|client_secret|access_token|refresh_token)["\']?\s*(?:=>|:|=)\s*["\']?)[^"\'\s,}\]]+/i', '$1[PROTEGIDO]', $value) ?? $value;
        $value = preg_replace('/(:\/\/[^:\s\/]+:)[^@\s\/]+@/', '$1[PROTEGIDO]@', $value) ?? $value;
        $value = preg_replace_callback('/\b([A-Z0-9._%+-]{2})[A-Z0-9._%+-]*@([A-Z0-9.-]+\.[A-Z]{2,})\b/i', fn (array $match) => $match[1].'***@'.$match[2], $value) ?? $value;
        $value = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', '***.***.***-**', $value) ?? $value;
        $value = preg_replace('/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/', '**.***.***\/****-**', $value) ?? $value;
        $value = preg_replace('/(?<!\d)(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?9?\d{4}[-\s]?\d{4}(?!\d)/', '[TELEFONE PROTEGIDO]', $value) ?? $value;
        $applicationPaths = ['/var/www/html'];
        if (app() instanceof \Illuminate\Foundation\Application) {
            $applicationPaths[] = base_path();
        }
        $value = str_replace(array_unique($applicationPaths), '[app]', $value);

        return trim($value);
    }

    private function parse(string $content): array
    {
        $entries = [];
        $current = null;

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            if (preg_match(self::HEADER_PATTERN, $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $this->normalize($current);
                }

                $current = [
                    'timestamp' => $matches['timestamp'],
                    'environment' => $matches['environment'],
                    'level' => strtolower($matches['level']),
                    'lines' => [$matches['message']],
                ];
            } elseif ($current !== null && trim($line) !== '') {
                $current['lines'][] = $line;
            }
        }

        if ($current !== null) {
            $entries[] = $this->normalize($current);
        }

        return $entries;
    }

    private function normalize(array $entry): array
    {
        $raw = implode("\n", $entry['lines']);
        $sanitized = $this->sanitize($raw);
        $lines = preg_split('/\R/', $sanitized) ?: [];
        $message = mb_substr(trim((string) array_shift($lines)), 0, 1600);
        $contextLines = array_slice(array_values(array_filter($lines, fn ($line) => trim($line) !== '')), 0, 8);
        $context = mb_substr(implode("\n", $contextLines), 0, 3000);
        $source = '';

        if (preg_match('/(?:#\d+\s+)?(?<file>\[app\][^\s(:]+\.php)(?:\((?<line>\d+)\)|:(?<line_alt>\d+))?/', $sanitized, $match)) {
            $source = $match['file'].(! empty($match['line'] ?? $match['line_alt'] ?? '') ? ':'.($match['line'] ?: $match['line_alt']) : '');
        }

        return [
            'id' => hash('sha256', $entry['timestamp'].'|'.$entry['level'].'|'.$message),
            'timestamp' => $entry['timestamp'],
            'environment' => $entry['environment'],
            'level' => $entry['level'],
            'message' => $message,
            'context' => $context,
            'source' => $source,
            'fingerprint' => substr(hash('sha256', preg_replace('/\d+/', '#', $message) ?? $message), 0, 12),
        ];
    }

    private function tail(string $path): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $size = filesize($path) ?: 0;
        $start = max(0, $size - $this->maxBytes);
        fseek($handle, $start);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        if ($start > 0 && ($newline = strpos($content, "\n")) !== false) {
            $content = substr($content, $newline + 1);
        }

        return $content;
    }

    private function path(): string
    {
        return $this->logPath ?: storage_path('logs/laravel.log');
    }

    private function emptyResult(string $path, string $error): array
    {
        return [
            'entries' => [],
            'summary' => [],
            'meta' => [
                'path' => 'storage/logs/'.basename($path),
                'size_bytes' => 0,
                'modified_at' => null,
                'scanned_entries' => 0,
                'returned_entries' => 0,
                'tail_bytes' => 0,
                'truncated' => false,
            ],
            'error' => $error,
        ];
    }
}
