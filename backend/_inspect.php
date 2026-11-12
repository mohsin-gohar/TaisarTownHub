<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (\App\Models\User::all() as $u) {
    echo $u->email . ' | role=' . $u->role . ' | agency_id=' . ($u->agency_id ?? 'null') . PHP_EOL;
}
echo 'agencies=' . \App\Models\Agency::count() . PHP_EOL;
echo 'members=' . \App\Models\AgencyMember::count() . PHP_EOL;
echo 'pass check admin: ' . (\Illuminate\Support\Facades\Hash::check('Admin@TaisarTownHub2026', \App\Models\User::where('email','admin@taisartownhub.com')->value('password')) ? 'OK' : 'FAIL') . PHP_EOL;