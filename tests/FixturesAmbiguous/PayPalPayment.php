<?php

declare(strict_types=1);

namespace AsceticSoft\Wirebox\Tests\FixturesAmbiguous;

class PayPalPayment implements PaymentInterface
{
    public function pay(int $amount): void
    {
    }
}
