<?php

namespace Tests\\Feature;

use Illuminate\\Support\\Facades\\File;

class BackupRetentionScriptTest extends EnterpriseTestCase
{
    public function test_backup_retention_keeps_seven_daily_four_weekly_and_six_monthly_sets(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tth-retention-' . bin2hex(random_bytes(6));
        File::makeDirectory($directory, 0700, true);

        $stamps = [
            // Seven daily calendar days, plus an older same-day backup.
            '20261120_120000' => true,
            '20261121_120000' => true,
            '20261122_120000' => true,
            '20261123_120000' => true,
            '20261124_120000' => true,
            '20261125_120000' => true,
            '20261126_090000' => false,
            '20261126_230000' => true,

            // Four newest ISO weeks; same-week older backup and fifth week expire.
            '20261119_090000' => false,
            '20261119_230000' => true,
            '20261112_120000' => true,
            '20261105_120000' => true,
            '20261029_120000' => true,
            '20261023_120000' => false,

            // Six monthly buckets after the weekly window.
            '20261022_120000' => true,
            '20260915_120000' => true,
            '20260815_120000' => true,
            '20260715_120000' => true,
            '20260615_120000' => true,
            '20260527_120000' => true,
            '20260415_120000' => false,
        ];

        try {
            foreach ($stamps as $stamp => $_keep) {
                file_put_contents($directory . "/tth_db_{$stamp}.sql.gz", 'db');
                file_put_contents($directory . "/tth_vault_{$stamp}.tar.gz", 'vault');
                file_put_contents($directory . "/manifest-{$stamp}.sha256", 'manifest');
            }

            $script = dirname(__DIR__, 3) . '/scripts/prune-backups.php';
            $command = escapeshellarg(PHP_BINARY) . ' '
                . escapeshellarg($script) . ' '
                . escapeshellarg($directory) . ' 2026-11-26 2>&1';
            exec($command, $output, $exitCode);

            $this->assertSame(0, $exitCode, implode("\\n", $output));
            $this->assertStringContainsString('kept 17 backup set(s)', implode("\\n", $output));

            foreach ($stamps as $stamp => $keep) {
                foreach ([
                    "tth_db_{$stamp}.sql.gz",
                    "tth_vault_{$stamp}.tar.gz",
                    "manifest-{$stamp}.sha256",
                ] as $name) {
                    $this->assertSame(
                        $keep,
                        is_file($directory . DIRECTORY_SEPARATOR . $name),
                        "{$name} retention state did not match its backup set."
                    );
                }
            }
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
