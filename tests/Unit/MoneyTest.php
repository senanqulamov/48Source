<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_parses_decimal_strings_into_cents_without_floats(): void
    {
        $this->assertSame(123450, Money::parse('1,234.50'));
        $this->assertSame(123450, Money::parse('1234,5'));
        $this->assertSame(1999, Money::parse('19.99'));
        $this->assertSame(10, Money::parse('0.1'));
        $this->assertSame(700, Money::parse('7'));
        $this->assertNull(Money::parse(''));
        $this->assertNull(Money::parse(null));
    }

    public function test_it_rejects_values_that_are_not_amounts(): void
    {
        foreach (['abc', '-5', '1.234', '1e3', '12..5'] as $bad) {
            try {
                Money::parse($bad);
                $this->fail("Accepted {$bad}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_line_totals_round_half_up_to_the_cent(): void
    {
        $this->assertSame(1000, Money::lineTotal(100, 10000));       // 1.00 x 10
        $this->assertSame(1748, Money::lineTotal(699, 2500));        // 6.99 x 2.5 = 17.475 -> 17.48
        $this->assertSame(1, Money::lineTotal(1, 500));              // 0.01 x 0.5 = 0.005 -> 0.01
        $this->assertSame(0, Money::lineTotal(1, 499));
        $this->assertSame(10065600, Money::lineTotal(69900, 144000)); // 699.00 x 144
    }

    public function test_the_classic_float_trap_stays_exact(): void
    {
        // 0.1 + 0.2 must be exactly 0.30
        $this->assertSame(30, Money::parse('0.1') + Money::parse('0.2'));
        $this->assertSame('$0.30', Money::format(30));
    }

    public function test_formatting_and_quantities(): void
    {
        $this->assertSame('$1,234.50', Money::format(123450));
        $this->assertSame('€1,235', Money::format(123500, 'EUR', false));
        $this->assertSame('-$5.05', Money::format(-505));
        $this->assertSame('-', Money::format(null));
        $this->assertSame(12500, Money::parseQty('12.5'));
        $this->assertSame('12.5', Money::qty(12500));
        $this->assertSame('1,200', Money::qty(1200000));
        $this->assertSame('125.50', Money::input(12550));
    }
}
