<?php

namespace Wave\SecurityIpRestrict;

use Illuminate\Support\ServiceProvider;
use Wave\SecurityIpRestrict\Http\Middleware\RestrictIp;

class IpRestrictServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ip-restrict.php', 'ip-restrict');

        $this->app->singleton('Wave\SecurityIpRestrict\IpList', function ($app) {
            return new IpList((array) $app['config']->get('ip-restrict', array()));
        });

        $this->app->singleton('Wave\SecurityIpRestrict\Attempts', function ($app) {
            $config = $app['config'];
            $attempts = new Attempts(
                (array) $config->get('ip-restrict.attempts', array()) + array('auto_block' => (array) $config->get('ip-restrict.auto_block', array())),
                $app->make('Wave\SecurityIpRestrict\IpList')
            );
            if ($config->get('ip-restrict.attempts.laravel_log')) {
                $attempts->setLogger(function (array $entry) use ($app) {
                    $app['log']->warning('ip-restrict: '.$entry['type'].' from '.$entry['ip'].(empty($entry['blocked']) ? '' : ' (blocked)'), $entry);
                });
            }

            return $attempts;
        });
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ip-restrict');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'ip-restrict');

        $this->publishes(array(__DIR__.'/../config/ip-restrict.php' => config_path('ip-restrict.php')), 'ip-restrict-config');
        $this->publishes(array(__DIR__.'/../resources/views' => base_path('resources/views/vendor/ip-restrict')), 'ip-restrict-views');
        $langPath = function_exists('lang_path') ? lang_path('vendor/ip-restrict') : base_path('resources/lang/vendor/ip-restrict');
        $this->publishes(array(__DIR__.'/../resources/lang' => $langPath), 'ip-restrict-lang');

        if ($this->app->runningInConsole()) {
            $this->commands(array(
                'Wave\SecurityIpRestrict\Console\ListCommand',
                'Wave\SecurityIpRestrict\Console\AddCommand',
                'Wave\SecurityIpRestrict\Console\RemoveCommand',
                'Wave\SecurityIpRestrict\Console\AttemptsCommand',
            ));
        }

        $config = $this->app['config'];

        if ($config->get('ip-restrict.global')) {
            $kernel = $this->app->make('Illuminate\Contracts\Http\Kernel');
            if (method_exists($kernel, 'prependMiddleware')) {
                $kernel->prependMiddleware(RestrictIp::class);
            }
        }

        if ($config->get('ip-restrict.attempts.enabled', true) && $config->get('ip-restrict.attempts.login_failed', true)) {
            $this->listenToLogins();
        }

        if ($config->get('ip-restrict.admin.enabled') && ! $this->routesCached()) {
            $admin = (array) $config->get('ip-restrict.admin');
            $router = $this->app['router'];
            require __DIR__.'/../routes/admin.php';
        }
    }

    /**
     * Failed logins (Laravel 5.2+) and lockouts after too many of them (Laravel 5.3+, ThrottlesLogins).
     *
     * @return void
     */
    private function listenToLogins()
    {
        $app = $this->app;
        $events = $app['events'];

        $events->listen('Illuminate\Auth\Events\Failed', function ($event) use ($app) {
            $field = $app['config']->get('ip-restrict.attempts.login_field', 'email');
            $login = '';
            if ($field && isset($event->credentials) && is_array($event->credentials) && isset($event->credentials[$field]) && is_scalar($event->credentials[$field])) {
                $login = (string) $event->credentials[$field];
            }
            $app->make('Wave\SecurityIpRestrict\Attempts')->recordRequest($app['request'], 'login_failed', $login);
        });

        $events->listen('Illuminate\Auth\Events\Lockout', function ($event) use ($app) {
            $request = isset($event->request) ? $event->request : $app['request'];
            $app->make('Wave\SecurityIpRestrict\Attempts')->recordRequest($request, 'lockout');
        });
    }

    /**
     * @return bool
     */
    private function routesCached()
    {
        return method_exists($this->app, 'routesAreCached') && $this->app->routesAreCached();
    }
}
