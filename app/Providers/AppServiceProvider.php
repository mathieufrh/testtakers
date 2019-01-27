<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Http\Resources\RequestQueryFilter;
use Illuminate\Http\Resources\Json\Resource;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Resource::withoutWrapping();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('filter', function($app) {
            return new RequestQueryFilter;
        });
    }
}
