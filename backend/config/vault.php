<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secure Document & Media Vault configuration
    |--------------------------------------------------------------------------
    |
    | encryption.enabled — AES-256-GCM at-rest encryption for every byte the
    |   vault writes to the private disks (`documents`, `quarantine`, media).
    |   The key is derived from APP_KEY (see App\Services\FileVaultCrypto) and
    |   is never stored next to the files. Legacy plaintext files keep reading
    |   transparently. During APP_KEY rotation, configure APP_PREVIOUS_KEYS on
    |   every replica and run `php artisan vault:encrypt` to rewrap old files.
    |
    | quarantine.retention_days — how long rejected/suspicious uploads stay in
    |   the isolated quarantine disk for administrator visibility before
    |   `vault:purge-quarantine` erases them.
    */

    'encryption' => [
        'enabled' => (bool) env('FILE_VAULT_ENCRYPTION', true),
    ],

    'quarantine' => [
        'retention_days' => (int) env('VAULT_QUARANTINE_RETENTION_DAYS', 30),
    ],

];
