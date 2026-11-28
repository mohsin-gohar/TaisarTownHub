<?php

namespace App\Services;

use App\Support\MalwareScanResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ClamAV malware scanner.
 *
 * Tries, in order:
 *   1. the `clamscan` CLI (configured via config('malware.clamav.command')),
 *   2. a clamd TCP socket (config('malware.clamav.socket'), e.g. "127.0.0.1:3310").
 *
 * The clamd transport implements the real ClamAV INSTREAM protocol:
 *
 *   1. send the null-terminated command  "zINSTREAM\0"
 *   2. send the file as a sequence of chunks, each prefixed by its length
 *      as a 4-byte big-endian integer:  [len][data][len][data]...
 *   3. terminate the stream with a zero length chunk: [00 00 00 00]
 *   4. read the null-terminated reply: "OK\0" or "<name> FOUND\0"
 *
 * The file CONTENT is streamed — never the server-side path (the daemon runs
 * in a different container and could not read the path anyway).
 *
 * When neither transport is reachable it returns MalwareScanResult::skipped() —
 * it never claims a file is clean unless a real scan ran and reported OK.
 */
class ClamAVScanner implements MalwareScanner
{
    /** Chunk size used when streaming the file to clamd (8 KiB). */
    private const CHUNK_SIZE = 8192;

    public function __construct(
        public string $command = 'clamscan',
        public ?string $socket = null,
        public bool $failClosed = false,
        public int $timeout = 30,
    ) {}

    public function scan(string $filePath): MalwareScanResult
    {
        $scanner = 'clamav';

        if (!is_file($filePath) || !is_readable($filePath)) {
            Log::warning('Malware scan skipped: file is not readable', [
                'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
                'scanner' => $scanner,
            ]);

            return $this->failClosed
                ? MalwareScanResult::infected($scanner, ['scan_unavailable'])
                : MalwareScanResult::skipped($scanner, 'file_not_readable');
        }

        if ($result = $this->scanViaClamscan($filePath)) {
            return $result;
        }

        if ($this->socket !== null && $result = $this->scanViaClamd($filePath)) {
            return $result;
        }

        $reason = 'clamav_not_available';
        Log::warning('Malware scan skipped: no ClamAV scanner reachable', [
            'extension' => strtolower(pathinfo($filePath, PATHINFO_EXTENSION)),
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

        // clamscan exit code 1 = virus found; 2 = error (treat as "unknown").
        if ($exitCode === 1) {
            $threats = [];
            foreach (explode("\n", $text) as $line) {
                if (str_contains($line, 'FOUND')) {
                    $threats[] = trim($line);
                }
            }

            return MalwareScanResult::infected('clamav', $threats ?: ['unknown_threat']);
        }

        return null;
    }

    /**
     * Stream the file to clamd using the INSTREAM protocol.
     *
     * Returns null when no verdict could be obtained (unreachable daemon,
     * malformed reply, protocol error) so the caller never treats it as clean.
     */
    private function scanViaClamd(string $filePath): ?MalwareScanResult
    {
        [$host, $port] = $this->parseSocket($this->socket);
        if ($host === null || $port === null) {
            return null;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$socket) {
            Log::warning('ClamAV clamd socket unreachable', [
                'host' => $host,
                'port' => $port,
                'error' => $errstr,
            ]);

            return null;
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            // 1) command
            if ($this->writeAll($socket, "zINSTREAM\0") === false) {
                return null;
            }

            // 2) chunks: [4-byte big-endian length][data]
            $handle = @fopen($filePath, 'rb');
            if ($handle === false) {
                return null;
            }

            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                if ($chunk === false) {
                    break;
                }
                if ($chunk === '') {
                    if (feof($handle)) {
                        break;
                    }
                    continue;
                }
                if ($this->writeAll($socket, pack('N', strlen($chunk)) . $chunk) === false) {
                    fclose($handle);

                    return null;
                }
            }
            fclose($handle);

            // 3) end of stream
            if ($this->writeAll($socket, pack('N', 0)) === false) {
                return null;
            }

            // 4) reply (null-terminated)
            $response = '';
            while (!feof($socket)) {
                $read = fread($socket, 4096);
                if ($read === false || $read === '') {
                    break;
                }
                $response .= $read;
                if (str_contains($response, "\0")) {
                    break;
                }
            }

            $reply = trim(str_replace("\0", '', $response));

            if ($reply === '') {
                Log::warning('ClamAV clamd returned an empty reply — verdict unknown', [
                    'host' => $host,
                    'port' => $port,
                ]);

                return null;
            }

            if (str_starts_with($reply, 'OK')) {
                return MalwareScanResult::clean('clamav');
            }

            if (str_contains($reply, 'FOUND')) {
                return MalwareScanResult::infected('clamav', [$reply]);
            }

            // Anything else (e.g. "INSTREAM size limit exceeded. ERROR") is an
            // error, not a clean verdict — never report it as clean.
            Log::warning('ClamAV clamd returned an unexpected reply', [
                'reply' => Str::limit($reply, 200),
            ]);

            return null;
        } finally {
            @fclose($socket);
        }
    }

    /** Write the whole buffer, tolerating partial writes. */
    private function writeAll($socket, string $buffer): bool
    {
        $length = strlen($buffer);
        $written = 0;

        while ($written < $length) {
            $bytes = @fwrite($socket, substr($buffer, $written));
            if ($bytes === false || $bytes === 0) {
                return false;
            }
            $written += $bytes;
        }

        return true;
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
