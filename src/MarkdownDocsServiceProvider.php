<?php

namespace DistortedFusion\MarkdownDocs;

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
        if (! defined('DF_MD_PATH')) {
            define('DF_MD_PATH', realpath(__DIR__.'/../'));
        }

        $this->mergeConfigFrom(DF_MD_PATH.'/config/markdown-docs.php', 'markdown-docs');
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->offerPublishing();
    }

    private function offerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                DF_MD_PATH.'/config/markdown-docs.php' => config_path('markdown-docs.php'),
            ], 'markdown-docs-config');
        }
    }
}
