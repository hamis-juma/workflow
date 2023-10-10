<?php

namespace HamisJuma\Workflow\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerResources();
    }

    public function register()
    {
        if($this->app->runningInConsole()){
            $this->registerPublishing();
        }
        $this->registerRoutes();
    }

    private function registerResources()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    private function registerPublishing()
    {
        $this->publishes([
            __DIR__.'/../Config/Workflow.php' => config_path('Workflow.php')
        ]);
    }

    private function registerRoutes()
    {
        Route::group($this->routeConfig(), function (){
            $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        });
    }

    private function routeConfig()
    {
        return [
            'prefix' => config_path('workflow.path'),
            'namespace' => 'HamisJuma\Workflow\Http\Controllers'
        ];
    }
}