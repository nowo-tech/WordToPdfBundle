<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Exception;

use RuntimeException;

final class ConversionFailedException extends RuntimeException implements WordToPdfExceptionInterface
{
}
