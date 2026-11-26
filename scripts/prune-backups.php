#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Retain complete database/vault/manifest backup sets:
 * - newest backup for each of the last 7 calendar days;
 * - newest backup for each of the next 4 ISO weeks;
 * - newest backup for each of the next 6 calendar months.
 *
 * Only timestamp-matched artefacts are removed. Unknown filenames and orphan
 * files are deliberately preserved for manual review.
 */
$directory = $argv[1] ?? '';
$nowArgument = $argv[2] ?? 'now';

if ($directory === '' || ! is_dir($directory)) {
    fwrite(STDERR, "[retention] ERROR: backup directory does not exist.\n");
    exit(2);
}

try {
    $now = new DateTimeImmutable($nowArgument);
} catch (Throwable $exception) {
    fwrite(STDERR, "[retention] ERROR: invalid reference date: {$exception->getMessage()}\n");
    exit(2);
}

$today = $now->setTime(0, 0, 0);
$dailyCutoff = $today->modify('-6 days');
$weeklyCutoff = $today->modify('-34 days');
$monthlyCutoff = $today->modify('-6 months');

$databaseFiles = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tth_db_*.sql.gz') ?: [];
usort($databaseFiles, static fn (string $a, string $b): int => strcmp(basename($b), basename($a)));

$dailyDays = [];
$weeklyBuckets = [];
$monthlyBuckets = [];
$keep = [];
$remove = [];
$invalid = [];

foreach ($databaseFiles as $file) {
    $base = basename($file);
    if (! preg_match('/^tth_db_(\\d{8}_\\d{6})\\.sql\\.gz$/D', $base, $matches)) {
        $invalid[] = $base;
        continue;
    }

    $date = DateTimeImmutable::createFromFormat('!Ymd_His', $matches[1]);
    $errors = DateTimeImmutable::getLastErrors();
    if (! $date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        $invalid[] = $base;
        continue;
    }

    $stamp = $matches[1];
    if ($date >= $dailyCutoff) {
        $bucket = $date->format('Y-m-d');
        if (! isset($dailyDays[$bucket])) {
            $dailyDays[$bucket] = true;
            $keep[$stamp] = true;
        } else {
            $remove[$stamp] = true;
        }
        continue;
    }

    if ($date >= $weeklyCutoff) {
        $bucket = $date->format('o-W');
        if (! isset($weeklyBuckets[$bucket]) && count($weeklyBuckets) < 4) {
            $weeklyBuckets[$bucket] = true;
            $keep[$stamp] = true;
        } else {
            $remove[$stamp] = true;
        }
        continue;
    }

    if ($date >= $monthlyCutoff) {
        $bucket = $date->format('Y-m');
        if (! isset($monthlyBuckets[$bucket]) && count($monthlyBuckets) < 6) {
            $monthlyBuckets[$bucket] = true;
            $keep[$stamp] = true;
        } else {
            $remove[$stamp] = true;
        }
        continue;
    }

    $remove[$stamp] = true;
}

$deleted = 0;
$errors = [];
foreach (array_keys($remove) as $stamp) {
    $artefacts = [
        rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "tth_db_{$stamp}.sql.gz",
        rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "tth_vault_{$stamp}.tar.gz",
        rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "manifest-{$stamp}.sha256",
    ];

    foreach ($artefacts as $artefact) {
        if (! is_file($artefact)) {
            continue;
        }
        if (! unlink($artefact)) {
            $errors[] = basename($artefact);
        } else {
            $deleted++;
        }
    }
}

foreach ($invalid as $file) {
    fwrite(STDERR, "[retention] WARNING: unrecognized backup timestamp retained: {$file}\n");
}

printf(
    "[retention] kept %d backup set(s) (daily=%d, weekly=%d, monthly=%d); removed %d artefact(s).\n",
    count($keep),
    count($dailyDays),
    count($weeklyBuckets),
    count($monthlyBuckets),
    $deleted
);

if ($errors !== []) {
    fwrite(STDERR, '[retention] ERROR: could not delete: ' . implode(', ', $errors) . "\n");
    exit(1);
}

exit(0);
