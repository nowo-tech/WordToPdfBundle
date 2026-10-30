<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Exception;

use RuntimeException;

/**
 * Thrown when a converted PDF cannot be streamed, saved locally or uploaded through Flysystem.
 */
final class ExportException extends RuntimeException implements WordToPdfExceptionInterface
{
}
