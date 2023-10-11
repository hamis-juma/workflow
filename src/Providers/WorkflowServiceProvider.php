<?php

namespace HamisJuma\Workflow\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerResources();
//        $this->registerRoutes();
        if($this->app->runningInConsole()){
            $this->registerPublishing();
        }
    }

    public function register()
    {
        $this->registerRoutes();
    }

    private function registerResources()
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    private function registerPublishing()
    {
        $this->publishes([__DIR__.'/../Config/workflow.php' => config_path('workflow.php')],'workflow-config');
        $this->publishes([__DIR__.'/../Database/migrations' => database_path('migrations')],'workflow-migrations');
    }

    private function registerRoutes()
    {

//        "repositories": {
//        "dev-package": {
//            "type": "path",
//            "url": "/Users/hamis/Packages/workflow",
//            "options": {
//                "symlink": true
//            }
//        }
//    }

        Route::middleware(['web','auth'])
            ->prefix(config('workflow.path'))
            ->namespace('HamisJuma\Workflow\Http\Controllers')
            ->group(__DIR__.'/../Routes/web.php');
    }

}