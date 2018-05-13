<?php

namespace Dukhanin\Acl\Tests;

use App\Exceptions\Handler;
use Exception;
use Illuminate\Contracts\Console\Kernel;
use Dukhanin\Acl\Providers\AclServiceProvider;

trait CreatesApplication
{
    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * {@inheritDoc}
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../vendor/laravel/laravel/bootstrap/app.php';

        $kernel = $app->make(Kernel::class);

        $app->events->listen('bootstrapped: Illuminate\Foundation\Bootstrap\RegisterProviders', function ($app) {
            foreach ($this->providers() as $providerClass) {
                $app->register(new $providerClass($app));
            }
        });

        $kernel->bootstrap();

        return $app;
    }

    /**
     * Отключает phpunit обработчик ошибок, позволяя
     * посмотреть реальный Exception
     */
    protected function disableExceptionHandling()
    {
        $this->app->instance(Handler::class, new class extends Handler
        {
            public function __construct()
            {
            }

            public function report(Exception $exception)
            {
            }

            public function render($request, Exception $exception)
            {
                throw $exception;
            }
        });
    }

    /**
     * Возвращает список провайдеров необходимых
     * в конкретном тесте
     *
     * @return array
     */
    protected function providers()
    {
        return [
            AclServiceProvider::class,
        ];
    }
}
