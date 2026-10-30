<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Exception;

use RuntimeException;

/**
 * Thrown when LibreOffice fails, times out, produces no PDF or the source/workspace cannot be prepared.
 */
final class ConversionFailedException extends RuntimeException implements WordToPdfExceptionInterface
{
}
