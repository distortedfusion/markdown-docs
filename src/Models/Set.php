<?php

namespace DistortedFusion\MarkdownDocs\Models;

use DistortedFusion\MarkdownDocs\Facades\Documentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Sushi\Sushi;

class Set extends Model
{
    use Sushi;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $schema = [
        'id' => 'string',
        'path' => 'string',
        'metadata' => 'json',
        'priority' => 'integer',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function getRows(): array
    {
        return collect(Documentation::sets())->map(
            fn ($config, $set) => array_merge($config, [
                'id' => $set,
                'metadata' => json_encode($config['metadata']),
            ])
        )->values()->all();
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function groupedPages(): Collection
    {
        $pages = $this->pages;

        return $pages
            ->groupBy('category')
            ->map(fn (Collection $group): Collection => $group->sortBy(
                fn (Page $page): string => $this->sortPageBy($page)
            ))
            ->sortBy(fn (Collection $group): ?string => $group->first()->category_raw);
    }

    public function sortPageBy(Page $page): string
    {
        return Str::after($page->path, '/');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->pages->first()?->url;
    }
}
