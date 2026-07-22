<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Exception;

use RuntimeException;

final class ExportException extends RuntimeException implements WordToPdfExceptionInterface
{
}
