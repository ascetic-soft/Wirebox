<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesAmbiguous;

class BitcoinPayment implements PaymentInterface
{
    public function pay(int $amount): void
    {
    }
}
