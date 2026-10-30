<?php

namespace App\Domain\Media;

use RuntimeException;

/**
 * An uploaded image refused by its content (SECURITY_MODEL section 13),
 * before anything is stored or any fact is written.
 */
final class ImageRefused extends RuntimeException
{
    public function __construct(public readonly ImageProblem $problem)
    {
        parent::__construct(sprintf('The image is refused: %s.', $problem->value));
    }
}
