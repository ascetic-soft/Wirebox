<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesAmbiguous;

interface PaymentInterface
{
    public function pay(int $amount): void;
}
