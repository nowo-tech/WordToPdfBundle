<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Exception;

use InvalidArgumentException;

/**
 * Thrown when the source is not a supported Word document (.docx / .doc).
 */
final class UnsupportedFormatException extends InvalidArgumentException implements WordToPdfExceptionInterface
{
}
