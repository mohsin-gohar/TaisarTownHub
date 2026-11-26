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
    |   is never stored next to the files. During key rotation, keep the old
    |   key in APP_PREVIOUS_KEYS, run `php artisan vault:encrypt`, verify the
    |   migration and retain a backup before removing the previous key. Legacy
    |   plaintext files keep streaming transparently and the command rewrites
    |   them in place.
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
