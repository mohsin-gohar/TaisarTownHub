<?php

/**
 * Minimal fake clamd daemon used by the malware-scanning tests.
 *
 * Usage:
 *   php fake_clamd.php <port> <reply-file> <ready-file> <capture-file>
 *
 * - <reply-file>   a file whose RAW bytes are sent back to the client
 *                  (e.g. "OK\0") - a file is used because a NUL byte cannot
 *                  be passed through a command line argument
 * - <ready-file>   created as soon as the socket is bound (test synchronisation)
 * - <capture-file> receives the RAW bytes the client sent, so the test can
 *                  assert the INSTREAM framing (and that the file CONTENT —
 *                  not the server-side path — was transmitted).
 */

$port = (int) ($argv[1] ?? 0);
$replyFile = $argv[2] ?? null;
$reply = ($replyFile !== null && is_file($replyFile)) ? (string) file_get_contents($replyFile) : "OK\0";
$readyFile = $argv[3] ?? null;
$captureFile = $argv[4] ?? null;

$server = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "fake-clamd: cannot bind port {$port}: {$errstr}\n");
    exit(1);
}

if ($readyFile) {
    file_put_contents($readyFile, (string) $port);
}

$conn = @stream_socket_accept($server, 20);
if ($conn) {
    stream_set_timeout($conn, 15);

    $buffer = '';
    while (!feof($conn)) {
        $chunk = fread($conn, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $buffer .= $chunk;

        // End of stream marker: a 4-byte zero length chunk at the tail.
        if (strlen($buffer) >= 12 && substr($buffer, -4) === "\0\0\0\0") {
            break;
        }
    }

    if ($captureFile) {
        file_put_contents($captureFile, $buffer);
    }

    fwrite($conn, $reply);
    fflush($conn);
    fclose($conn);
}

fclose($server);
exit(0);
