<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /**
     * @return array<int, array{int, string}>
     */
    public static function formats(): array
    {
        return [
            [0, '₹0.00'],
            [5, '₹0.05'],
            [99900, '₹999.00'],
            [123456, '₹1,234.56'],
            [12345678900, '₹12,34,56,789.00'],
            [-4900, '-₹49.00'],
        ];
    }

    #[DataProvider('formats')]
    public function test_format_uses_indian_grouping(int $paise, string $expected): void
    {
        $this->assertSame($expected, Money::format($paise));
    }

    public function test_rupee_strings_convert_to_paise_exactly(): void
    {
        $this->assertSame(4900, Money::fromRupees('49'));
        $this->assertSame(4950, Money::fromRupees('49.5'));
        $this->assertSame(4905, Money::fromRupees('49.05'));
        $this->assertSame(149999, Money::fromRupees('1499.99'));
        $this->assertSame('1499.99', Money::toRupees(149999));
    }
}
