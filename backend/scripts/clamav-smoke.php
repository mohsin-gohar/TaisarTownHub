<?php

declare(strict_types=1);

use App\Services\ClamAVScanner;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$socket = (string) config('malware.clamav.socket', '');
if ($socket === '') {
    fwrite(STDERR, "CLAMD_SOCKET is not configured.\n");
    exit(1);
}

$scanner = new ClamAVScanner(
    (string) config('malware.clamav.command', 'clamscan'),
    $socket,
    true,
    (int) config('malware.clamav.timeout', 30),
);

$cleanPath = tempnam(sys_get_temp_dir(), 'tth-clamav-clean-');
$eicarPath = tempnam(sys_get_temp_dir(), 'tth-clamav-eicar-');
if ($cleanPath === false || $eicarPath === false) {
    if (is_string($cleanPath)) {
        @unlink($cleanPath);
    }
    if (is_string($eicarPath)) {
        @unlink($eicarPath);
    }
    fwrite(STDERR, "Could not create temporary scan fixtures.\n");
    exit(1);
}

$exitCode = 0;
try {
    file_put_contents($cleanPath, "TaisarTownHub ClamAV clean smoke fixture\n");
    // Standard harmless antivirus test signature, used only as scanner test data.
    file_put_contents($eicarPath, 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');

    $clean = $scanner->scan($cleanPath);
    if (! $clean->isClean || $clean->skipped) {
        throw new RuntimeException('ClamAV did not return a real CLEAN verdict for the clean fixture.');
    }

    $infected = $scanner->scan($eicarPath);
    if ($infected->isClean || $infected->skipped || $infected->threats === []) {
        throw new RuntimeException('ClamAV did not detect the antivirus test signature.');
    }

    fwrite(STDOUT, "PASS: real ClamAV clean-file verdict and threat detection verified.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    $exitCode = 1;
} finally {
    @unlink($cleanPath);
    @unlink($eicarPath);
}

exit($exitCode);
