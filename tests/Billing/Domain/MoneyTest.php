<?php

declare(strict_types=1);

namespace App\Tests\Billing\Domain;

use App\Billing\Domain\Exception\CurrencyMismatch;
use App\Billing\Domain\Model\Money;
use App\Billing\Domain\Tax\VatRate;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money(-1, 'EUR');
    }

    public function testRejectsUncodedCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Money(100, 'eur');
    }

    public function testRejectsAdditionAcrossCurrencies(): void
    {
        $this->expectException(CurrencyMismatch::class);

        (new Money(100, 'EUR'))->add(new Money(100, 'USD'));
    }

    public function testAppliesVatHalfUp(): void
    {
        self::assertSame(1, (new Money(1, 'EUR'))->applyRate(new VatRate(5_000))->amountMinor);
        self::assertSame(0, (new Money(1, 'EUR'))->applyRate(new VatRate(4_999))->amountMinor);
        self::assertSame(2, (new Money(10, 'EUR'))->applyRate(new VatRate(1_900))->amountMinor);
        self::assertSame(1_900, (new Money(10_000, 'EUR'))->applyRate(new VatRate(1_900))->amountMinor);
    }

    public function testMultipliesLineQuantity(): void
    {
        $net = (new Money(10_000, 'EUR'))->multiply(2);

        self::assertTrue($net->equals(new Money(20_000, 'EUR')));
    }
}
