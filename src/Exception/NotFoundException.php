<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Exception;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
