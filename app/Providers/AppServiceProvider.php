<?php

namespace App\Providers;

use App\Lagoon\DeploymentEngine\Engine;
use App\Sanity\SanityEngine;
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

        $this->app->bind("SanityEngine", function ($app) {
            $engine = new SanityEngine(
                "sanity",
                "./",
                env('LAGOON_PROJECT',''),
                env('SANITY_PROJECT_ID',''),
                env('SANITY_STUDIO_API_DATASET','')
            );

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
