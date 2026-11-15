<?php

namespace App\Services;

use App\Support\MalwareScanResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ClamAV malware scanner.
 *
 * Tries, in order:
 *   1. the `clamscan` CLI (configured via services.clamav.command),
 *   2. a clamd TCP socket (services.clamav.socket, e.g. "127.0.0.1:3310").
 *
 * When neither is reachable it returns MalwareScanResult::skipped() -- it
 * never claims a file is clean unless a real scan ran.
 */
class ClamAVScanner implements MalwareScanner
{
    public function __construct(
        public string $command = 'clamscan',
        public ?string $socket = null,
        public bool $failClosed = false,
    ) {}

    public function scan(string $filePath): MalwareScanResult
    {
        $scanner = 'clamav';

        if ($result = $this->scanViaClamscan($filePath)) {
            return $result;
        }

        if ($this->socket !== null && $result = $this->scanViaClamd($filePath)) {
            return $result;
        }

        $reason = 'clamav_not_available';
        Log::warning('Malware scan skipped: no ClamAV scanner reachable', [
            'file' => $filePath,
            'scanner' => $scanner,
        ]);

        if ($this->failClosed) {
            return MalwareScanResult::infected($scanner, ['scan_unavailable']);
        }

        return MalwareScanResult::skipped($scanner, $reason);
    }

    public function isAvailable(): bool
    {
        return $this->clamscanBinaryExists() || $this->socket !== null;
    }

    private function scanViaClamscan(string $filePath): ?MalwareScanResult
    {
        if (!$this->clamscanBinaryExists()) {
            return null;
        }

        $command = escapeshellcmd($this->command) . ' --stdout --no-summary -- ' . escapeshellarg($filePath);
        exec($command . ' 2>&1', $output, $exitCode);
        $text = implode("\n", $output);

        if ($exitCode === 0) {
            return MalwareScanResult::clean('clamav');
        }

        // clamscan exit code 1 = virus found; 2 = error.
        if ($exitCode === 1) {
            $threats = [];
            foreach (explode("\n", $text) as $line) {
                if (str_contains($line, 'FOUND')) {
                    $threats[] = trim($line);
                }
            }
            return MalwareScanResult::infected('clamav', $threats);
        }

        return null;
    }

    private function scanViaClamd(string $filePath): ?MalwareScanResult
    {
        [$host, $port] = $this->parseSocket($this->socket);
        if ($host === null || $port === null) {
            return null;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$socket) {
            return null;
        }

        stream_set_timeout($socket, 30);
        fwrite($socket, "zINSTREAM\0" . $filePath . "\0");
        $response = '';
        while (!feof($socket)) {
            $response .= fread($socket, 4096);
        }
        fclose($socket);

        if (str_starts_with($response, 'OK')) {
            return MalwareScanResult::clean('clamav');
        }

        if (str_contains($response, 'FOUND')) {
            $threats = [];
            foreach (explode("\n", $response) as $line) {
                if (str_contains($line, 'FOUND')) {
                    $threats[] = trim($line);
                }
            }
            return MalwareScanResult::infected('clamav', $threats);
        }

        return null;
    }

    private function clamscanBinaryExists(): bool
    {
        return $this->command !== '' && (bool) @shell_exec('which ' . escapeshellarg($this->command) . ' 2>/dev/null');
    }

    private function parseSocket(?string $socket): array
    {
        if ($socket === null || $socket === '') {
            return [null, null];
        }
        $parts = explode(':', $socket, 2);
        $host = $parts[0] ?? null;
        $port = $parts[1] ?? null;
        return [$host, $port ? (int) $port : null];
    }
}
