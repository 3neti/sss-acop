<?php

namespace App\KYC\Enums;

enum HypervergeCountry: string
{
    case PHL = 'phl';

    public function label(): string
    {
        return match($this) {
            self::PHL => 'Philippines',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }
}
