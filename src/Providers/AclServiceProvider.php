<?php

namespace Dukhanin\Acl\Providers;

use Illuminate\Support\ServiceProvider;
use Dukhanin\Acl\Auth\Access\AccessManager;
use Dukhanin\Acl\Auth\Access\RoleResolver;
use Dukhanin\Acl\Contracts\Auth\RoleResolver as RoleResolverContract;
use Dukhanin\Acl\Contracts\Auth\AccessManager as AccessManagerContract;

class AclServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->bootRoles();

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(RoleResolverContract::class, RoleResolver::class);

        $this->app->singleton(AccessManagerContract::class, AccessManager::class);
    }

    /**
     * Подключает конфигурацию ролей (описывают взаимотношения
     * между разными уровнями доступа пользователей)
     *
     * @return void
     */
    protected function bootRoles()
    {
        $this->app->bind('acl.roles', function(){
            return require __DIR__.'/../Auth/roles.php';
        });
    }
}
