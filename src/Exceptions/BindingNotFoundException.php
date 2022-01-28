<?php

declare(strict_types=1);

namespace Otobio\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class BindingNotFoundException extends Exception implements NotFoundExceptionInterface
{
}
