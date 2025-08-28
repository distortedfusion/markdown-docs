<?php

declare(strict_types=1);

namespace DistortedFusion\MarkdownDocs\Exceptions;

use Exception;

final class SetNotFound extends Exception
{
    public static function missing(string $set): self
    {
        return new self("Set by name \"$set\" not found.");
    }
}
