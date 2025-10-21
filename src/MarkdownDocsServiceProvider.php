<?php

namespace DistortedFusion\MarkdownDocs;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownDocsServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerDocumentation();
        $this->registerEnvironment();
        $this->registerConverter();
        $this->registerMatterEnvironment();
        $this->registerMatterConverter();
    }

    private function registerDocumentation(): void
    {
        $this->app->singleton('markdown.documentation', function (Container $app): Documentation {
            return new Documentation(
                $app['files'],
                $app['markdown.documentation.converter'],
                $app['markdown.documentation.matter-converter'],
            );
        });
    }

    private function registerEnvironment(): void
    {
        $this->app->singleton('markdown.documentation.environment', function (): EnvironmentInterface {
            $environment = new Environment(
                config('markdown-docs.converter_environment', [])
            );

            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            $environment->addExtension(new FrontMatterExtension());

            return $environment;
        });
    }

    private function registerConverter(): void
    {
        $this->app->singleton('markdown.documentation.converter', function (Container $app): ConverterInterface {
            return new MarkdownConverter(
                $app['markdown.documentation.environment']
            );
        });
    }

    private function registerMatterEnvironment(): void
    {
        $this->app->singleton('markdown.documentation.matter-environment', function (): EnvironmentInterface {
            $environment = new Environment(
                config('markdown-docs.converter_environment', [])
            );

            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new FrontMatterExtension());

            return $environment;
        });
    }

    private function registerMatterConverter(): void
    {
        $this->app->singleton('markdown.documentation.matter-converter', function (Container $app): ConverterInterface {
            return new MarkdownConverter(
                $app['markdown.documentation.matter-environment']
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
