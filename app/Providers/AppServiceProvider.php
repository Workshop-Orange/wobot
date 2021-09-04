<?php

namespace App\Providers;

use App\Lagoon\DeploymentEngine\Engine;
use App\Lagoon\NewRelicEngine;
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

        $this->app->singleton(NewRelicEngine::class, function() {
            $engine = new NewRelicEngine(
                env('NEWRELIC_USER_KEY',''),
                env('NEWRELIC_API_BASE', 'https://api.newrelic.com/v2/'),
                env('NEWRELIC_API_SYNTHETICS_BASE', 'https://synthetics.newrelic.com/synthetics/api/v3/'),
            );

            return $engine;
        });
    }
}
