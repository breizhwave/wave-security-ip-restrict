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
            ));
        }

        $config = $this->app['config'];

        if ($config->get('ip-restrict.global')) {
            $kernel = $this->app->make('Illuminate\Contracts\Http\Kernel');
            if (method_exists($kernel, 'prependMiddleware')) {
                $kernel->prependMiddleware(RestrictIp::class);
            }
        }

        if ($config->get('ip-restrict.admin.enabled') && ! $this->routesCached()) {
            $admin = (array) $config->get('ip-restrict.admin');
            $router = $this->app['router'];
            require __DIR__.'/../routes/admin.php';
        }
    }

    /**
     * @return bool
     */
    private function routesCached()
    {
        return method_exists($this->app, 'routesAreCached') && $this->app->routesAreCached();
    }
}
