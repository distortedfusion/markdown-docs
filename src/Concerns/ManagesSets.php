<?php

namespace DistortedFusion\MarkdownDocs\Concerns;

use DistortedFusion\MarkdownDocs\Exceptions\SetNotFound;
use Illuminate\Support\Arr;

trait ManagesSets
{
    protected array $sets = [];

    /**
     * Get all sets ordered by priority.
     *
     * @return array
     */
    public function sets(): array
    {
        return Arr::sort($this->sets, 'priority');
    }

    /**
     * Get a set by name.
     *
     * @param string $set
     *
     * @return array|null
     */
    public function set(string $set): ?array
    {
        if (! isset($this->sets[$set])) {
            throw SetNotFound::missing($set);
        }

        return $this->sets[$set];
    }

    /**
     * Add a set.
     *
     * @param string $set
     * @param string $path
     * @param array  $metadata
     * @param int    $priority
     *
     * @return self
     */
    public function addSet(string $set, string $path, array $metadata = [], int $priority = 10): self
    {
        $this->sets[$set] = [
            'path' => $path,
            'metadata' => $metadata,
            'priority' => $priority,
        ];

        return $this;
    }
}
