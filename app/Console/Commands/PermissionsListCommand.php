<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Permission;
use App\Models\Role;

/**
 * T530: List all permissions command
 *
 * Lists all permissions in the system with their details.
 * Usage: php artisan permissions:list [--role=<role_name>] [--category=<category>]
 */
class PermissionsListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:list
                            {--role= : Filter by role name}
                            {--category= : Filter by category (pages, features, actions)}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '列出所有權限 (List all permissions in the system)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $roleName = $this->option('role');
        $category = $this->option('category');
        $format = $this->option('format');

        $this->info('');
        $this->info('=== 權限列表 (Permissions List) ===');
        $this->info('');

        // Get permissions
        $query = Permission::query();

        if ($category) {
            $validCategories = ['pages', 'features', 'actions'];
            if (!in_array($category, $validCategories)) {
                $this->error("Invalid category: {$category}");
                $this->info("Valid categories: " . implode(', ', $validCategories));
                return self::FAILURE;
            }
            $query->where('category', $category);
        }

        $permissions = $query->orderBy('category')->orderBy('name')->get();

        if ($permissions->isEmpty()) {
            $this->warn('No permissions found.');
            return self::SUCCESS;
        }

        // If filtering by role, get role permissions
        $rolePermissionNames = [];
        if ($roleName) {
            $role = Role::where('name', $roleName)->with('permissions')->first();
            if (!$role) {
                $this->error("Role not found: {$roleName}");
                $this->info("Available roles: " . Role::pluck('name')->implode(', '));
                return self::FAILURE;
            }
            $rolePermissionNames = $role->permissions->pluck('name')->toArray();
            $this->info("Filtering by role: {$roleName}");
            $this->info('');
        }

        // Output based on format
        if ($format === 'json') {
            $data = $permissions->map(function ($p) use ($roleName, $rolePermissionNames) {
                $item = [
                    'id' => $p->id,
                    'name' => $p->name,
                    'display_name' => $p->display_name,
                    'category' => $p->category,
                ];
                if ($roleName) {
                    $item['has_permission'] = in_array($p->name, $rolePermissionNames);
                }
                return $item;
            });
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        // Table format
        $headers = ['ID', 'Name', 'Display Name', 'Category'];
        if ($roleName) {
            $headers[] = 'Has Permission';
        }

        $rows = $permissions->map(function ($p) use ($roleName, $rolePermissionNames) {
            $row = [
                $p->id,
                $p->name,
                $p->display_name,
                $p->category,
            ];
            if ($roleName) {
                $row[] = in_array($p->name, $rolePermissionNames) ? '✅' : '❌';
            }
            return $row;
        })->toArray();

        $this->table($headers, $rows);

        // Summary
        $this->info('');
        $this->info('Summary:');
        $this->info('  Total: ' . count($rows) . ' permissions');

        if (!$category) {
            $byCategory = $permissions->groupBy('category');
            foreach ($byCategory as $cat => $perms) {
                $this->info("  - {$cat}: " . count($perms));
            }
        }

        if ($roleName && !empty($rolePermissionNames)) {
            $this->info("  Role '{$roleName}' has " . count($rolePermissionNames) . " permissions");
        }

        $this->info('');

        return self::SUCCESS;
    }
}
