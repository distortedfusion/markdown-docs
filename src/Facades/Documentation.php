<?php

namespace DistortedFusion\MarkdownDocs\Facades;

use Illuminate\Support\Facades\Facade;

class Documentation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'markdown.documentation';
    }
}
