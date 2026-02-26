<?php

namespace ApiLens;

use ApiLens\Commands\ExportCommand;
use ApiLens\Commands\InstallCommand;
use ApiLens\Controllers\ApiLensController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ApiLensServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/api-lens.php', 'api-lens');

        $this->app->singleton(ApiLens::class);
        $this->app->singleton(ApiLensToOpenApi::class);
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/api-lens.php' => config_path('api-lens.php'),
        ], 'api-lens-config');

        // Publish assets
        $this->publishes([
            __DIR__ . '/../resources/dist' => public_path('vendor/api-lens'),
        ], 'api-lens-assets');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'api-lens');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExportCommand::class,
                InstallCommand::class,
            ]);
        }

        // Register routes
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        if (!config('api-lens.enabled')) {
            return;
        }

        $url = config('api-lens.url', 'api-lens');
        $middlewares = config('api-lens.middlewares', []);

        Route::group([
            'prefix'     => $url,
            'middleware'  => $middlewares,
        ], function () use ($url) {
            Route::get('/', [ApiLensController::class, 'index'])->name('api-lens.index');
            Route::get('/api', [ApiLensController::class, 'api'])->name('api-lens.api');
            Route::get('/config', [ApiLensController::class, 'config'])->name('api-lens.config');
            Route::get('/export/openapi', [ApiLensController::class, 'exportOpenApi'])->name('api-lens.export.openapi');
            Route::get('/export/postman', [ApiLensController::class, 'exportPostman'])->name('api-lens.export.postman');
            Route::get('/check-update', [ApiLensController::class, 'checkUpdate'])->name('api-lens.check-update');
            Route::get('/assets/{file}', [ApiLensController::class, 'assets'])
                ->where('file', '.*')
                ->name('api-lens.assets');
        });
    }
}
