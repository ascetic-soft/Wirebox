<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox;

enum Lifetime
{
    case Singleton;
    case Transient;
}
