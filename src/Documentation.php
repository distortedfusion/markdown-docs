<?php

namespace DistortedFusion\MarkdownDocs;

use Carbon\Carbon;
use DistortedFusion\MarkdownDocs\Concerns\ManagesSets;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\CommonMark\ConverterInterface;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\MarkdownConverter;
use Symfony\Component\Finder\SplFileInfo;

class Documentation
{
    use ManagesSets;

    /**
     * The filesystem implementation.
     *
     * @var Filesystem
     */
    protected Filesystem $files;

    /**
     * The markdown converter implementation.
     *
     * @var ConverterInterface
     */
    protected ConverterInterface $converter;

    /**
     * The markdown converter implementation with minimal extensions for meta parsing.
     *
     * @var ConverterInterface
     */
    protected ConverterInterface $matterConverter;

    /**
     * Create a new documentation instance.
     *
     * @param Filesystem         $files
     * @param ConverterInterface $converter
     *
     * @return void
     */
    public function __construct(Filesystem $files, ConverterInterface $converter)
    {
        $this->files = $files;
        $this->converter = $converter;
        $this->matterConverter = new MarkdownConverter($this->matterEnvironment());
    }

    /**
     * Get all available pages across sets.
     *
     * @return array
     */
    public function allPages(): array
    {
        $pages = [];

        foreach (array_keys($this->sets()) as $set) {
            $pages[$set] = $this->pages($set);
        }

        return $pages;
    }

    /**
     * Get the pages within a set.
     *
     * @param string $set
     *
     * @return array
     */
    public function pages(string $set): array
    {
        $files = $this->files->allFiles($this->set($set)['path']);

        return Collection::make($files)
            ->map(fn (SplFileInfo $file): string => $file->getRelativePathname())
            ->filter(fn (string $path): bool => Str::endsWith($path, '.md'))
            ->all();
    }

    /**
     * Get the title for the page.
     *
     * @param string $set
     * @param string $path
     *
     * @return string|null
     */
    public function pageTitle(string $set, string $path): ?string
    {
        $matter = $this->pageMatter($set, $path);

        // Prefer front-matter metadata for the page title.
        if (! empty($matter) && isset($matter['title'])) {
            return $matter['title'];
        }

        $content = $this->pageContent($set, $path);

        // Fallback to the `h1` element within the document.
        if (! is_null($content) && trim($content) !== '') {
            $title = trim(
                Str::match('/<h1[^>]*>(.*?)<\/h1>/is', $content)
            );

            return $title !== '' ? $title : null;
        }

        return null;
    }

    /**
     * Get the contents for the page.
     *
     * @param string $set
     * @param string $path
     *
     * @return array
     */
    public function pageMatter(string $set, string $path): array
    {
        if (! ($rawContent = $this->pageContentRaw($set, $path))) {
            return [];
        }

        $markdown = $this->matterConverter->convert($rawContent);

        if (! $markdown instanceof RenderedContentWithFrontMatter) {
            return [];
        }

        return $markdown->getFrontMatter();
    }

    /**
     * Get the contents for the page.
     *
     * @param string $set
     * @param string $path
     *
     * @return string|null
     */
    public function pageContent(string $set, string $path): ?string
    {
        if (! ($rawContent = $this->pageContentRaw($set, $path))) {
            return null;
        }

        return $this->converter->convert($rawContent)->getContent();
    }

    /**
     * Get the RAW contents for the page.
     *
     * @param string $set
     * @param string $path
     *
     * @return string|null
     */
    public function pageContentRaw(string $set, string $path): ?string
    {
        $path = $this->setPagePath($set, $path);

        if ($this->files->exists($path)) {
            return $this->files->get($path);
        }

        return null;
    }

    public function pageLastModified(string $set, string $path): Carbon
    {
        $path = $this->setPagePath($set, $path);

        return Carbon::parse($this->files->lastModified($path));
    }

    public function setPagePath(string $set, string $path): string
    {
        return sprintf('%s/%s', rtrim($this->set($set)['path'], '/'), ltrim($path, '/'));
    }

    private function matterEnvironment(): EnvironmentInterface
    {
        $environment = new Environment(
            Arr::except(Config::get('markdown'), ['extensions', 'views'])
        );

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new FrontMatterExtension());

        return $environment;
    }

    public function markdownCache(): FilesystemAdapter
    {
        return Storage::disk($this->cacheDisk());
    }

    protected function cacheDisk(): string
    {
        return config('markdown-docs.cache_disk', 'public');
    }
}
