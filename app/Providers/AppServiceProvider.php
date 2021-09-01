<?php

namespace App\Providers;

use App\Lagoon\DeploymentEngine\Engine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind("LagoonDeploymentEngine", function ($app) {
            $engine = new Engine();

            return $engine;
        });
    }
}
