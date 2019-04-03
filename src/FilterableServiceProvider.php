<?php

namespace Sedehi\Filterable;

use Illuminate\Support\ServiceProvider;

class FilterableServiceProvider extends ServiceProvider
{

    protected $defer = false;

    /**
     * Bootstrap the application services.
     * @return void
     */
    public function boot(){

        $this->publishes([__DIR__.'/../config/filterable.php' => config_path('filterable.php')], 'filterable-config');
    }

    /**
     * Register the application services.
     * @return void
     */
    public function register(){

        $this->mergeConfigFrom(__DIR__.'/../config/filterable.php', 'filterable');
    }
}
