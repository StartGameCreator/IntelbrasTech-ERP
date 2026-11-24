<?php

namespace Tests\Unit;

use App\Services\Sistema\InfrastructureMonitor;
use Tests\TestCase;

class InfrastructureMonitorTest extends TestCase
{
    public function test_it_parses_prometheus_metrics_and_labels(): void
    {
        $content = <<<'METRICS'
# HELP node_memory_MemTotal_bytes Memory information field MemTotal_bytes.
node_memory_MemTotal_bytes 8.388608e+09
node_uname_info{domainname="(none)",machine="x86_64",nodename="srv2179676",release="6.12.0",sysname="Linux",version="#1 SMP"} 1
node_filesystem_size_bytes{device="/dev/sda1",fstype="ext4",mountpoint="/"} 106300440576
METRICS;

        $metrics = app(InfrastructureMonitor::class)->parsePrometheus($content);

        $this->assertSame(8388608000.0, $metrics['node_memory_MemTotal_bytes'][0]['value']);
        $this->assertSame('srv2179676', $metrics['node_uname_info'][0]['labels']['nodename']);
        $this->assertSame('/', $metrics['node_filesystem_size_bytes'][0]['labels']['mountpoint']);
    }

    public function test_it_calculates_container_cpu_memory_network_and_io(): void
    {
        $stats = [
            'cpu_stats' => [
                'cpu_usage' => ['total_usage' => 300, 'percpu_usage' => [150, 150]],
                'system_cpu_usage' => 2000,
                'online_cpus' => 2,
            ],
            'precpu_stats' => [
                'cpu_usage' => ['total_usage' => 100],
                'system_cpu_usage' => 1000,
            ],
            'memory_stats' => [
                'usage' => 5000,
                'limit' => 10000,
                'stats' => ['inactive_file' => 1000],
            ],
            'networks' => [
                'eth0' => ['rx_bytes' => 100, 'tx_bytes' => 200],
                'eth1' => ['rx_bytes' => 50, 'tx_bytes' => 75],
            ],
            'blkio_stats' => ['io_service_bytes_recursive' => [
                ['op' => 'read', 'value' => 300],
                ['op' => 'write', 'value' => 400],
            ]],
            'pids_stats' => ['current' => 7],
        ];

        $result = app(InfrastructureMonitor::class)->calculateContainerStats($stats);

        $this->assertSame(40.0, $result['cpu_percent']);
        $this->assertSame(4000, $result['memory_usage_bytes']);
        $this->assertSame(40.0, $result['memory_percent']);
        $this->assertSame(150, $result['network_rx_bytes']);
        $this->assertSame(275, $result['network_tx_bytes']);
        $this->assertSame(300, $result['block_read_bytes']);
        $this->assertSame(400, $result['block_write_bytes']);
        $this->assertSame(7, $result['pids']);
    }
}
