<?php

namespace App\Services\Sistema;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class InfrastructureMonitor
{
    private string $dockerUrl;

    private string $nodeExporterUrl;

    private float $timeout;

    public function __construct()
    {
        $this->dockerUrl = rtrim((string) config('services.infrastructure_monitor.docker_url'), '/');
        $this->nodeExporterUrl = rtrim((string) config('services.infrastructure_monitor.node_exporter_url'), '/');
        $this->timeout = max(1, (float) config('services.infrastructure_monitor.timeout', 3));
    }

    public function snapshot(): array
    {
        $errors = [];

        try {
            $host = $this->hostSnapshot();
        } catch (\Throwable) {
            $host = ['available' => false, 'status' => 'error', 'message' => 'Coletor de desempenho da VPS indisponível.'];
            $errors[] = 'Não foi possível consultar as métricas da VPS.';
        }

        try {
            $docker = $this->dockerSnapshot();
        } catch (\Throwable) {
            $docker = ['available' => false, 'status' => 'error', 'message' => 'Monitor de containers indisponível.', 'containers' => []];
            $errors[] = 'Não foi possível consultar os containers Docker.';
        }

        try {
            $project = $this->projectSnapshot($docker);
        } catch (\Throwable) {
            $project = ['available' => false, 'status' => 'error', 'message' => 'Diagnóstico do projeto indisponível.'];
            $errors[] = 'Não foi possível consultar os dados do projeto.';
        }

        $statuses = array_filter([$host['status'] ?? null, $docker['status'] ?? null, $project['status'] ?? null]);
        $status = in_array('error', $statuses, true) ? 'error' : (in_array('warning', $statuses, true) ? 'warning' : 'ok');

        return [
            'status' => $status,
            'collected_at' => now()->toIso8601String(),
            'host' => $host,
            'docker' => $docker,
            'project' => $project,
            'errors' => $errors,
            'read_only' => true,
        ];
    }

    private function hostSnapshot(): array
    {
        if ($this->nodeExporterUrl === '') {
            throw new \RuntimeException('NODE_EXPORTER_URL ausente.');
        }

        $response = Http::timeout($this->timeout)->get($this->nodeExporterUrl.'/metrics');
        $response->throw();
        $metrics = $this->parsePrometheus($response->body());
        $timestamp = $this->metricValue($metrics, 'node_time_seconds') ?? microtime(true);

        $memoryTotal = $this->metricValue($metrics, 'node_memory_MemTotal_bytes') ?? 0;
        $memoryAvailable = $this->metricValue($metrics, 'node_memory_MemAvailable_bytes') ?? 0;
        $memoryUsed = max(0, $memoryTotal - $memoryAvailable);
        $memoryPercent = $memoryTotal > 0 ? ($memoryUsed / $memoryTotal) * 100 : 0;

        $swapTotal = $this->metricValue($metrics, 'node_memory_SwapTotal_bytes') ?? 0;
        $swapFree = $this->metricValue($metrics, 'node_memory_SwapFree_bytes') ?? 0;

        $filesystem = $this->rootFilesystem($metrics);
        $cpu = $this->cpuSnapshot($metrics, $timestamp);
        $network = $this->networkSnapshot($metrics, $timestamp);
        $diskIo = $this->diskIoSnapshot($metrics, $timestamp);
        $uname = $this->metricLabels($metrics, 'node_uname_info');
        $os = $this->metricLabels($metrics, 'node_os_info');
        $dmi = $this->metricLabels($metrics, 'node_dmi_info');
        $bootTime = $this->metricValue($metrics, 'node_boot_time_seconds') ?? $timestamp;
        $cores = count(array_unique(array_filter(
            array_map(
                fn (array $sample) => $sample['labels']['cpu'] ?? null,
                $this->samples($metrics, 'node_cpu_seconds_total')
            ),
            fn (?string $cpu) => $cpu !== null
        )));

        $load1 = $this->metricValue($metrics, 'node_load1') ?? 0;
        $load5 = $this->metricValue($metrics, 'node_load5') ?? 0;
        $load15 = $this->metricValue($metrics, 'node_load15') ?? 0;
        $filesystemPercent = (float) ($filesystem['used_percent'] ?? 0);

        $status = $this->resourceStatus(max($cpu['usage_percent'], $memoryPercent, $filesystemPercent));

        return [
            'available' => true,
            'status' => $status,
            'identity' => [
                'hostname' => $uname['nodename'] ?? gethostname(),
                'operating_system' => $os['pretty_name'] ?? $os['name'] ?? 'Linux',
                'kernel' => trim(($uname['sysname'] ?? 'Linux').' '.($uname['release'] ?? '')),
                'architecture' => $uname['machine'] ?? php_uname('m'),
                'virtualization' => $this->virtualizationName($dmi),
                'vendor' => $dmi['sys_vendor'] ?? $dmi['board_vendor'] ?? null,
                'model' => $dmi['product_name'] ?? null,
                'bios' => $dmi['bios_version'] ?? null,
                'timezone' => config('app.timezone'),
            ],
            'uptime_seconds' => max(0, (int) ($timestamp - $bootTime)),
            'cpu' => [
                ...$cpu,
                'cores' => $cores,
                'load_1' => round($load1, 2),
                'load_5' => round($load5, 2),
                'load_15' => round($load15, 2),
            ],
            'memory' => [
                'total_bytes' => (int) $memoryTotal,
                'used_bytes' => (int) $memoryUsed,
                'available_bytes' => (int) $memoryAvailable,
                'used_percent' => round($memoryPercent, 1),
                'swap_total_bytes' => (int) $swapTotal,
                'swap_used_bytes' => (int) max(0, $swapTotal - $swapFree),
            ],
            'filesystem' => $filesystem,
            'network' => $network,
            'disk_io' => $diskIo,
            'processes' => [
                'running' => (int) ($this->metricValue($metrics, 'node_procs_running') ?? 0),
                'blocked' => (int) ($this->metricValue($metrics, 'node_procs_blocked') ?? 0),
                'file_descriptors_open' => (int) ($this->metricValue($metrics, 'node_filefd_allocated') ?? 0),
                'file_descriptors_max' => (int) ($this->metricValue($metrics, 'node_filefd_maximum') ?? 0),
            ],
            'collector' => [
                'name' => 'Prometheus Node Exporter',
                'version' => $this->metricLabels($metrics, 'node_exporter_build_info')['version'] ?? null,
            ],
        ];
    }

    private function dockerSnapshot(): array
    {
        if ($this->dockerUrl === '') {
            throw new \RuntimeException('DOCKER_MONITOR_URL ausente.');
        }

        $client = Http::baseUrl($this->dockerUrl)->timeout($this->timeout)->acceptJson();
        $info = $client->get('/info')->throw()->json();
        $version = $client->get('/version')->throw()->json();
        $summaries = $client->get('/containers/json', ['all' => 'true'])->throw()->json();
        $containers = [];

        foreach ($summaries as $summary) {
            $id = (string) ($summary['Id'] ?? '');
            if ($id === '') {
                continue;
            }

            $stats = $client->get("/containers/{$id}/stats", ['stream' => 'false', 'one-shot' => 'true'])->throw()->json();
            $inspect = $client->get("/containers/{$id}/json")->throw()->json();
            $usage = $this->calculateContainerStats($stats);
            $state = (string) ($summary['State'] ?? $inspect['State']['Status'] ?? 'unknown');
            $health = $inspect['State']['Health']['Status'] ?? null;
            $status = $this->containerStatus($state, $health);

            $containers[] = [
                'id' => substr($id, 0, 12),
                'name' => ltrim((string) (($summary['Names'][0] ?? $inspect['Name'] ?? $id)), '/'),
                'service' => $summary['Labels']['com.docker.compose.service'] ?? null,
                'project' => $summary['Labels']['com.docker.compose.project'] ?? null,
                'image' => $summary['Image'] ?? $inspect['Config']['Image'] ?? null,
                'image_id' => $summary['ImageID'] ?? $inspect['Image'] ?? null,
                'state' => $state,
                'health' => $health,
                'status' => $status,
                'status_text' => $summary['Status'] ?? null,
                'created_at' => isset($summary['Created']) ? date(DATE_ATOM, (int) $summary['Created']) : null,
                'started_at' => $inspect['State']['StartedAt'] ?? null,
                'restart_count' => (int) ($inspect['RestartCount'] ?? 0),
                ...$usage,
            ];
        }

        usort($containers, fn (array $a, array $b) => strcmp($a['name'], $b['name']));
        $running = count(array_filter($containers, fn (array $item) => $item['state'] === 'running'));
        $unhealthy = count(array_filter($containers, fn (array $item) => $item['health'] === 'unhealthy'));
        $problematic = count(array_filter($containers, fn (array $item) => in_array($item['status'], ['warning', 'error'], true)));

        return [
            'available' => true,
            'status' => $problematic > 0 ? ($unhealthy > 0 ? 'error' : 'warning') : 'ok',
            'engine' => [
                'name' => $info['Name'] ?? null,
                'server_version' => $info['ServerVersion'] ?? $version['Version'] ?? null,
                'api_version' => $version['ApiVersion'] ?? null,
                'operating_system' => $info['OperatingSystem'] ?? null,
                'kernel' => $info['KernelVersion'] ?? null,
                'architecture' => $info['Architecture'] ?? null,
                'storage_driver' => $info['Driver'] ?? null,
                'logging_driver' => $info['LoggingDriver'] ?? null,
                'cgroup_driver' => $info['CgroupDriver'] ?? null,
                'cgroup_version' => $info['CgroupVersion'] ?? null,
                'docker_root' => $info['DockerRootDir'] ?? null,
                'cpu_cores' => (int) ($info['NCPU'] ?? 0),
                'memory_bytes' => (int) ($info['MemTotal'] ?? 0),
                'images' => (int) ($info['Images'] ?? 0),
            ],
            'summary' => [
                'total' => count($containers),
                'running' => $running,
                'stopped' => count($containers) - $running,
                'healthy' => count(array_filter($containers, fn (array $item) => $item['health'] === 'healthy')),
                'unhealthy' => $unhealthy,
                'cpu_percent' => round(array_sum(array_column($containers, 'cpu_percent')), 1),
                'memory_bytes' => array_sum(array_column($containers, 'memory_usage_bytes')),
                'network_rx_bytes' => array_sum(array_column($containers, 'network_rx_bytes')),
                'network_tx_bytes' => array_sum(array_column($containers, 'network_tx_bytes')),
            ],
            'containers' => $containers,
        ];
    }

    private function projectSnapshot(array $docker): array
    {
        $driver = DB::connection()->getDriverName();
        $database = [
            'driver' => $driver,
            'database' => DB::connection()->getDatabaseName(),
            'version' => null,
            'size_bytes' => null,
            'connections' => null,
            'max_connections' => null,
            'tables' => null,
        ];

        if ($driver === 'pgsql') {
            $row = DB::selectOne("select current_database() as database, version() as version, pg_database_size(current_database()) as size_bytes, (select count(*) from pg_stat_activity where datname = current_database()) as connections, (select count(*) from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE') as tables");
            $maxConnections = DB::selectOne('show max_connections');
            $database = [
                'driver' => 'pgsql',
                'database' => $row->database,
                'version' => $row->version,
                'size_bytes' => (int) $row->size_bytes,
                'connections' => (int) $row->connections,
                'max_connections' => (int) ($maxConnections->max_connections ?? 0),
                'tables' => (int) $row->tables,
            ];
        } elseif ($driver === 'sqlite') {
            $database['version'] = DB::selectOne('select sqlite_version() as version')->version ?? null;
            $database['tables'] = (int) (DB::selectOne("select count(*) as total from sqlite_master where type = 'table'")->total ?? 0);
        }

        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
        $applied = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
        $pending = array_values(array_diff(array_map(fn (string $file) => pathinfo($file, PATHINFO_FILENAME), $migrationFiles), $applied));
        $composer = $this->composerPackages();
        $opcache = function_exists('opcache_get_status') ? (opcache_get_status(false) ?: []) : [];
        $appContainer = collect($docker['containers'] ?? [])->firstWhere('service', 'app');

        return [
            'available' => true,
            'status' => count($pending) > 0 ? 'warning' : 'ok',
            'application' => [
                'name' => config('app.name'),
                'environment' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'server_api' => PHP_SAPI,
                'routes' => Route::getRoutes()->count(),
                'composer_packages' => $composer['total'],
                'direct_dependencies' => $composer['direct'],
                'image' => $appContainer['image'] ?? null,
                'image_id' => $appContainer['image_id'] ?? null,
                'container_started_at' => $appContainer['started_at'] ?? null,
            ],
            'runtime' => [
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'opcache_enabled' => (bool) ($opcache['opcache_enabled'] ?? false),
                'opcache_used_bytes' => (int) ($opcache['memory_usage']['used_memory'] ?? 0),
                'opcache_free_bytes' => (int) ($opcache['memory_usage']['free_memory'] ?? 0),
                'cache_store' => config('cache.default'),
                'queue_connection' => config('queue.default'),
                'session_driver' => config('session.driver'),
                'filesystem_disk' => config('filesystems.default'),
            ],
            'database' => $database,
            'migrations' => [
                'files' => count($migrationFiles),
                'applied' => count($applied),
                'pending' => count($pending),
                'pending_names' => array_slice($pending, 0, 20),
            ],
            'stack' => array_values(array_filter(array_map(fn (array $item) => $item['service'] ?? null, $docker['containers'] ?? []))),
        ];
    }

    public function calculateContainerStats(array $stats): array
    {
        $cpu = $stats['cpu_stats'] ?? [];
        $preCpu = $stats['precpu_stats'] ?? [];
        $cpuDelta = (float) ($cpu['cpu_usage']['total_usage'] ?? 0) - (float) ($preCpu['cpu_usage']['total_usage'] ?? 0);
        $systemDelta = (float) ($cpu['system_cpu_usage'] ?? 0) - (float) ($preCpu['system_cpu_usage'] ?? 0);
        $onlineCpus = (int) ($cpu['online_cpus'] ?? count($cpu['cpu_usage']['percpu_usage'] ?? []) ?: 1);
        $cpuPercent = ($cpuDelta > 0 && $systemDelta > 0) ? ($cpuDelta / $systemDelta) * $onlineCpus * 100 : 0;

        $memory = $stats['memory_stats'] ?? [];
        $cache = (float) ($memory['stats']['inactive_file'] ?? $memory['stats']['cache'] ?? 0);
        $memoryUsage = max(0, (float) ($memory['usage'] ?? 0) - $cache);
        $memoryLimit = (float) ($memory['limit'] ?? 0);

        $networkRx = 0;
        $networkTx = 0;
        foreach ($stats['networks'] ?? [] as $network) {
            $networkRx += (float) ($network['rx_bytes'] ?? 0);
            $networkTx += (float) ($network['tx_bytes'] ?? 0);
        }

        $blockRead = 0;
        $blockWrite = 0;
        foreach ($stats['blkio_stats']['io_service_bytes_recursive'] ?? [] as $entry) {
            $operation = strtolower((string) ($entry['op'] ?? ''));
            if ($operation === 'read') {
                $blockRead += (float) ($entry['value'] ?? 0);
            } elseif ($operation === 'write') {
                $blockWrite += (float) ($entry['value'] ?? 0);
            }
        }

        return [
            'cpu_percent' => round($cpuPercent, 2),
            'memory_usage_bytes' => (int) $memoryUsage,
            'memory_limit_bytes' => (int) $memoryLimit,
            'memory_percent' => $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 2) : 0,
            'network_rx_bytes' => (int) $networkRx,
            'network_tx_bytes' => (int) $networkTx,
            'block_read_bytes' => (int) $blockRead,
            'block_write_bytes' => (int) $blockWrite,
            'pids' => (int) ($stats['pids_stats']['current'] ?? 0),
        ];
    }

    public function parsePrometheus(string $content): array
    {
        $metrics = [];
        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! preg_match('/^(?<name>[a-zA-Z_:][a-zA-Z0-9_:]*)(?:\{(?<labels>.*)\})?\s+(?<value>[-+]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][-+]?\d+)?)$/', $line, $match)) {
                continue;
            }

            $labels = [];
            if (! empty($match['labels']) && preg_match_all('/([a-zA-Z_][a-zA-Z0-9_]*)="((?:\\\\.|[^"\\\\])*)"/', $match['labels'], $pairs, PREG_SET_ORDER)) {
                foreach ($pairs as $pair) {
                    $labels[$pair[1]] = stripcslashes($pair[2]);
                }
            }

            $metrics[$match['name']][] = ['labels' => $labels, 'value' => (float) $match['value']];
        }

        return $metrics;
    }

    private function cpuSnapshot(array $metrics, float $timestamp): array
    {
        $total = 0.0;
        $idle = 0.0;
        foreach ($this->samples($metrics, 'node_cpu_seconds_total') as $sample) {
            $total += $sample['value'];
            if (in_array($sample['labels']['mode'] ?? '', ['idle', 'iowait'], true)) {
                $idle += $sample['value'];
            }
        }

        $previous = Cache::get('infra:host:cpu');
        Cache::put('infra:host:cpu', compact('total', 'idle', 'timestamp'), 60);
        $usage = 0.0;
        $warming = true;
        if (is_array($previous)) {
            $deltaTotal = $total - (float) ($previous['total'] ?? $total);
            $deltaIdle = $idle - (float) ($previous['idle'] ?? $idle);
            if ($deltaTotal > 0) {
                $usage = max(0, min(100, (($deltaTotal - $deltaIdle) / $deltaTotal) * 100));
                $warming = false;
            }
        }

        return ['usage_percent' => round($usage, 1), 'warming' => $warming];
    }

    private function networkSnapshot(array $metrics, float $timestamp): array
    {
        $rx = 0.0;
        $tx = 0.0;
        foreach ($this->samples($metrics, 'node_network_receive_bytes_total') as $sample) {
            if ($this->physicalInterface($sample['labels']['device'] ?? '')) {
                $rx += $sample['value'];
            }
        }
        foreach ($this->samples($metrics, 'node_network_transmit_bytes_total') as $sample) {
            if ($this->physicalInterface($sample['labels']['device'] ?? '')) {
                $tx += $sample['value'];
            }
        }

        $rates = $this->counterRates('infra:host:network', compact('rx', 'tx'), $timestamp);

        return [
            'received_bytes' => (int) $rx,
            'transmitted_bytes' => (int) $tx,
            'receive_bytes_per_second' => (int) ($rates['rx'] ?? 0),
            'transmit_bytes_per_second' => (int) ($rates['tx'] ?? 0),
        ];
    }

    private function diskIoSnapshot(array $metrics, float $timestamp): array
    {
        $read = array_sum(array_column($this->samples($metrics, 'node_disk_read_bytes_total'), 'value'));
        $write = array_sum(array_column($this->samples($metrics, 'node_disk_written_bytes_total'), 'value'));
        $rates = $this->counterRates('infra:host:disk', compact('read', 'write'), $timestamp);

        return [
            'read_bytes' => (int) $read,
            'written_bytes' => (int) $write,
            'read_bytes_per_second' => (int) ($rates['read'] ?? 0),
            'write_bytes_per_second' => (int) ($rates['write'] ?? 0),
        ];
    }

    private function counterRates(string $key, array $counters, float $timestamp): array
    {
        $previous = Cache::get($key);
        Cache::put($key, ['timestamp' => $timestamp, 'counters' => $counters], 60);
        if (! is_array($previous) || $timestamp <= (float) ($previous['timestamp'] ?? $timestamp)) {
            return array_fill_keys(array_keys($counters), 0);
        }

        $seconds = $timestamp - (float) $previous['timestamp'];
        $rates = [];
        foreach ($counters as $name => $value) {
            $rates[$name] = max(0, ($value - (float) ($previous['counters'][$name] ?? $value)) / $seconds);
        }

        return $rates;
    }

    private function rootFilesystem(array $metrics): array
    {
        foreach ($this->samples($metrics, 'node_filesystem_size_bytes') as $sample) {
            if (($sample['labels']['mountpoint'] ?? '') !== '/') {
                continue;
            }
            $labels = $sample['labels'];
            $available = $this->metricValue($metrics, 'node_filesystem_avail_bytes', $labels) ?? 0;
            $size = $sample['value'];
            $used = max(0, $size - $available);

            return [
                'mountpoint' => '/',
                'device' => $labels['device'] ?? null,
                'type' => $labels['fstype'] ?? null,
                'total_bytes' => (int) $size,
                'used_bytes' => (int) $used,
                'available_bytes' => (int) $available,
                'used_percent' => $size > 0 ? round(($used / $size) * 100, 1) : 0,
            ];
        }

        return ['mountpoint' => '/', 'total_bytes' => 0, 'used_bytes' => 0, 'available_bytes' => 0, 'used_percent' => 0];
    }

    private function metricValue(array $metrics, string $name, array $labels = []): ?float
    {
        foreach ($this->samples($metrics, $name) as $sample) {
            $matches = true;
            foreach ($labels as $key => $value) {
                if (($sample['labels'][$key] ?? null) !== $value) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $sample['value'];
            }
        }

        return null;
    }

    private function metricLabels(array $metrics, string $name): array
    {
        return $this->samples($metrics, $name)[0]['labels'] ?? [];
    }

    private function samples(array $metrics, string $name): array
    {
        return $metrics[$name] ?? [];
    }

    private function physicalInterface(string $device): bool
    {
        return $device !== '' && ! preg_match('/^(lo|docker\d*|veth|br-|virbr|tun|tap)/', $device);
    }

    private function virtualizationName(array $dmi): string
    {
        $haystack = strtolower(implode(' ', $dmi));
        if (str_contains($haystack, 'qemu') || str_contains($haystack, 'kvm')) {
            return 'KVM / QEMU';
        }
        if (str_contains($haystack, 'vmware')) {
            return 'VMware';
        }
        if (str_contains($haystack, 'virtualbox')) {
            return 'VirtualBox';
        }

        return $dmi === [] ? 'Não identificada' : 'Máquina virtual';
    }

    private function resourceStatus(float $highestPercent): string
    {
        return $highestPercent >= 90 ? 'error' : ($highestPercent >= 80 ? 'warning' : 'ok');
    }

    private function containerStatus(string $state, ?string $health): string
    {
        if ($health === 'unhealthy' || in_array($state, ['dead', 'exited'], true)) {
            return 'error';
        }
        if (in_array($state, ['restarting', 'paused', 'created'], true) || $health === 'starting') {
            return 'warning';
        }

        return $state === 'running' ? 'ok' : 'warning';
    }

    private function composerPackages(): array
    {
        $lock = json_decode((string) @file_get_contents(base_path('composer.lock')), true);
        $composer = json_decode((string) @file_get_contents(base_path('composer.json')), true);

        return [
            'total' => count($lock['packages'] ?? []),
            'direct' => count($composer['require'] ?? []),
        ];
    }
}
