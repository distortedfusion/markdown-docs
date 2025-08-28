<?php

namespace DistortedFusion\MarkdownDocs;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class MarkdownDocsServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerMarkdownDocumentation();
    }

    private function registerMarkdownDocumentation(): void
    {
        $this->app->singleton('markdown.documentation', function (Container $app): Documentation {
            return new Documentation(
                $app['files'],
                $app['markdown.converter'],
            );
        });
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->setupConfig();
    }

    private function setupConfig(): void
    {
        $source = realpath($raw = __DIR__.'/../config/markdown-docs.php') ?: $raw;

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $source => config_path('markdown-docs.php'),
            ], 'markdown-docs-config');
        }

        $this->mergeConfigFrom($source, 'markdown-docs');
    }
}
