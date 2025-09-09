<?php

namespace DistortedFusion\MarkdownDocs\Models;

use DistortedFusion\MarkdownDocs\Facades\Documentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use League\CommonMark\Normalizer\SlugNormalizer;
use Sushi\Sushi;

class Page extends Model
{
    use Sushi;

    protected $casts = [
        'matter' => 'json',
        'last_modified' => 'datetime',
    ];

    public function getRows(): array
    {
        $mappedPages = [];

        foreach (Documentation::allPages() as $set => $pages) {
            array_push($mappedPages, ...array_map(
                fn (string $path): array => $this->mapPage($set, $path),
                $pages
            ));
        }

        return $mappedPages;
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    public function getContentRawAttribute(): ?string
    {
        $content = Documentation::pageContentRaw($this->set_id, $this->path);

        return $content ? $content : null;
    }

    public function getContentAttribute(): ?HtmlString
    {
        $content = Documentation::markdownCache()->exists($this->cachePath())
            ? Documentation::markdownCache()->get($this->cachePath())
            : Documentation::pageContent($this->set_id, $this->path);

        return $content ? new HtmlString($content) : null;
    }

    public function sections(): array
    {
        $matches = [];

        // Extract sub-headings, e.g. ## or deeper, from the raw page content...
        preg_match_all('/(?m)^#{2,} (.*)/', $this->content_raw, $matches);

        $sections = [];

        foreach ($matches[1] as $index => $heading) {
            $sections[] = [
                'title' => $heading,
                'slug' => (new SlugNormalizer())->normalize($heading),
                'depth' => substr_count($matches[0][$index], '#'),
            ];
        }

        return $sections;
    }

    public function indexOfCurrentCategory(): int
    {
        $categories = $this->set()->firstOrFail()->groupedPages()->keys();

        return $categories->search($this->category);
    }

    public function indexOfCurrentPageWithinCategory(): int
    {
        $groupedPages = $this->set()->firstOrFail()->groupedPages();

        return $groupedPages->get($this->category)->search(fn ($page) => $page->is($this));
    }

    public function getPreviousPage(): ?Page
    {
        $groupedPages = $this->set()->firstOrFail()->groupedPages();

        $pageIndex = $this->indexOfCurrentPageWithinCategory();
        $categoryIndex = $this->indexOfCurrentCategory();

        // Resolve previous page from same category...
        if ($pageIndex > 0) {
            return $groupedPages->get($this->category)->get($pageIndex - 1);
        }

        // Resolve previous page from previous category...
        if ($categoryIndex > 0) {
            return $groupedPages->values()->get($categoryIndex - 1)->last();
        }

        return null;
    }

    public function getNextPage(): ?Page
    {
        $groupedPages = $this->set()->firstOrFail()->groupedPages();

        $pageIndex = $this->indexOfCurrentPageWithinCategory();
        $categoryIndex = $this->indexOfCurrentCategory();

        // Resolve next page from same category...
        if ($groupedPages->get($this->category)->count() !== $pageIndex + 1) {
            return $groupedPages->get($this->category)->get($pageIndex + 1);
        }

        // Resolve next page from next category...
        if ($groupedPages->count() !== $categoryIndex + 1) {
            return $groupedPages->values()->get($categoryIndex + 1)->first();
        }

        return null;
    }

    public function hasPreviousPage(): bool
    {
        return ! is_null($this->getPreviousPage());
    }

    public function hasNextPage(): bool
    {
        return ! is_null($this->getNextPage());
    }

    public function getUrlAttribute(): ?string
    {
        return route('docs.page', ['page' => $this->slug]);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function cachePath(): string
    {
        return 'markdown_cache/'.$this->set_id.'/'.$this->path.'/'.$this->checksum;
    }

    private function mapPage(string $set, string $path): array
    {
        $categoryRaw = Str::contains($path, '/') ? Str::before($path, '/') : null;
        $category = Str::after($categoryRaw, '-') ?: null;

        $filename = Str::afterLast($path, '/');
        $slug = implode('/', array_filter([$category, Str::after(Str::before($filename, '.md'), '-')]));

        return [
            'set_id' => $set,
            'title' => Documentation::pageTitle($set, $path),
            'slug' => $slug,
            'category_raw' => $categoryRaw,
            'category' => $category,
            'filename' => $filename,
            'path' => $path,
            'last_modified' => Documentation::pageLastModified($set, $path),
            'checksum' => hash('xxh3', Documentation::pageContentRaw($set, $path)),
            'matter' => json_encode(Documentation::pageMatter($set, $path)),
        ];
    }
}
