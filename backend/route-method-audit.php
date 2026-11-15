<?php
// Route -> controller method existence audit (read-only).
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$missing = [];
$checked = 0;
foreach (app('router')->getRoutes() as $route) {
    $action = $route->getActionName();
    if (strpos($action, 'Controller@') === false) {
        continue;
    }
    [$class, $method] = explode('@', $action);
    $checked++;
    if (!class_exists($class)) {
        $missing[] = $action . ' (class missing)';
        continue;
    }
    if (!method_exists($class, $method)) {
        $missing[] = $action . ' -> /' . $route->uri();
    }
}

echo "routes checked: {$checked}\n";
if (count($missing) === 0) {
    echo "ALL_ROUTE_METHODS_EXIST\n";
    exit(0);
}
echo "MISSING (" . count($missing) . "):\n";
foreach (array_unique($missing) as $m) {
    echo "  - {$m}\n";
}
exit(1);
