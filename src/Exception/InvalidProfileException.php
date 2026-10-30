<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Exception;

use InvalidArgumentException;

/**
 * Thrown when a conversion profile key is unknown or its options are invalid.
 */
final class InvalidProfileException extends InvalidArgumentException implements WordToPdfExceptionInterface
{
}
