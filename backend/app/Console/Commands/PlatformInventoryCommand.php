<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Read-only platform inventory used by the audit and documentation work:
 * table/row counts, RBAC totals, the role→grant map and the API route count.
 * It never writes to the database.
 */
class PlatformInventoryCommand extends Command
{
    protected $signature = 'platform:inventory {--tables} {--roles} {--routes}';

    protected $description = 'Print the live platform inventory (tables, RBAC grants, API routes)';

    public function handle(): int
    {
        $showTables = $this->option('tables') || (!$this->option('roles') && !$this->option('routes'));
        $showRoles = $this->option('roles') || (!$this->option('tables') && !$this->option('routes'));
        $showRoutes = $this->option('routes') || (!$this->option('tables') && !$this->option('roles'));

        if ($showTables) {
            $this->line('TABLES');
            foreach ($this->tableNames() as $name) {
                try {
                    $count = DB::table($name)->count();
                } catch (\Throwable $e) {
                    $count = 'n/a';
                }
                $this->line(sprintf('  %-34s %s', $name, $count));
            }
        }

        if ($showRoles) {
            $this->line('RBAC');
            $this->line('  permissions = ' . DB::table('permissions')->count());
            $this->line('  roles       = ' . DB::table('roles')->count());
            $this->line('  grants      = ' . DB::table('role_permissions')->count());

            foreach (DB::table('roles')->orderBy('scope')->orderBy('key')->get() as $role) {
                $keys = DB::table('role_permissions')
                    ->where('role_id', $role->id)
                    ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->orderBy('permissions.key')
                    ->pluck('permissions.key')
                    ->all();

                $this->line("  {$role->scope}/{$role->key} (" . count($keys) . '): ' . implode(', ', $keys));
            }
        }

        if ($showRoutes) {
            $routes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with($route->uri(), 'api/'));
            $this->line('ROUTES');
            $this->line('  api registrations = ' . $routes->count());
            $this->line('  unique actions    = ' . $routes->map(fn ($r) => $r->getActionName())->unique()->count());
        }

        return 0;
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

            return array_map(fn ($row) => array_values((array) $row)[0], $rows);
        }

        return array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW TABLES'));
    }
}
