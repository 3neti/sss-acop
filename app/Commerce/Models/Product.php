<?php

namespace App\Commerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Bavix\Wallet\Interfaces\ProductInterface;
use Illuminate\Database\Eloquent\Model;
use Bavix\Wallet\Traits\HasWalletFloat;
use Database\Factories\ProductFactory;
use Bavix\Wallet\Interfaces\Customer;
use Whitecube\Price\Price;
use Brick\Money\Money;
use App\Commerce\Support\MoneyFactory;

class Product extends Model implements ProductInterface
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasWalletFloat;
    use HasFactory;

    const DEFAULT_CURRENCY = 'PHP';

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'vendor_id',
    ];

    public static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->currency = empty($product->currency) ? self::DEFAULT_CURRENCY : $product->currency;
        });
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    protected function Price(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $currency = $attributes['currency'] ?? self::DEFAULT_CURRENCY;

                return MoneyFactory::priceWithPrecision(Money::ofMinor($value, $currency));
            },
            set: function ($value, $attributes) {
                $currency = $attributes['currency'] ?? self::DEFAULT_CURRENCY;

                return $value instanceof Price
                    ? $value->inclusive()->getMinorAmount()->toInt()  // Extract minor units if already Money
                    : ($value instanceof Money ? $value->getMinorAmount()->toInt() : Money::of($value, $currency)->getMinorAmount()->toInt()); // Convert before storing
            }
        );
    }

    public function getAmountProduct(Customer $customer): int|string
    {
        $amount = $this->getAttribute('price');

        return $amount instanceof Price ? $amount->inclusive()->getMinorAmount()->toInt() : (int) $amount * 100;
    }

    public function getMetaProduct(): ?array
    {
        return [
            'name' => $this->name,
            'price' => (string) $this->price
        ];
    }
}
