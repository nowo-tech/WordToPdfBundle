<?php

declare(strict_types=1);

namespace Nowo\WordToPdfBundle\Exception;

use InvalidArgumentException;

final class InvalidProfileException extends InvalidArgumentException implements WordToPdfExceptionInterface
{
}
