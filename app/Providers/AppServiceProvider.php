<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Services\RolePermissionService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissionGates();
    }

    /**
     * Register permission gates for RBAC.
     *
     * Gates are defined for:
     * - T413: Page access (view_channels_list, view_comments_list, view_admin_panel)
     * - T414: Feature access (use_search_videos, use_official_api_import, use_video_update)
     * - T415: Actions (manage_users, change_password)
     */
    protected function registerPermissionGates(): void
    {
        // Before callback - administrators bypass all gates
        Gate::before(function (User $user, string $ability) {
            $rolePermissionService = app(RolePermissionService::class);
            if ($rolePermissionService->isAdministrator($user)) {
                return true;
            }
            return null; // Let the gate check continue
        });

        // T413: Page access gates
        Gate::define('view_channels_list', function (User $user) {
            return $this->checkPermission($user, 'view_channels_list');
        });

        Gate::define('view_comments_list', function (User $user) {
            return $this->checkPermission($user, 'view_comments_list');
        });

        Gate::define('view_admin_panel', function (User $user) {
            return $this->checkPermission($user, 'view_admin_panel');
        });

        // T414: Feature access gates
        Gate::define('use_search_videos', function (User $user) {
            return $this->checkPermission($user, 'use_search_videos');
        });

        Gate::define('use_search_comments', function (User $user) {
            return $this->checkPermission($user, 'use_search_comments');
        });

        Gate::define('use_video_analysis', function (User $user) {
            return $this->checkPermission($user, 'use_video_analysis');
        });

        Gate::define('use_video_update', function (?User $user) {
            if (!$user) {
                return false;
            }
            // Video update requires YouTube API key to be configured
            if (!$this->checkPermission($user, 'use_video_update')) {
                return false;
            }
            // Additional check: user must have YouTube API key configured
            return !empty($user->youtube_api_key);
        });

        Gate::define('use_u_api_import', function (User $user) {
            return $this->checkPermission($user, 'use_u_api_import');
        });

        Gate::define('use_official_api_import', function (User $user) {
            // Note: Quota check should be done separately
            return $this->checkPermission($user, 'use_official_api_import');
        });

        // T415: Action gates
        Gate::define('manage_users', function (User $user) {
            return $this->checkPermission($user, 'manage_users');
        });

        Gate::define('manage_permissions', function (User $user) {
            return $this->checkPermission($user, 'manage_permissions');
        });

        Gate::define('change_password', function (User $user) {
            return $this->checkPermission($user, 'change_password');
        });
    }

    /**
     * Check if user has the given permission through their roles.
     */
    protected function checkPermission(User $user, string $permission): bool
    {
        $rolePermissionService = app(RolePermissionService::class);
        return $rolePermissionService->hasPermission($user, $permission);
    }
}
