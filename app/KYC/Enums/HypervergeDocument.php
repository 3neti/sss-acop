<?php

namespace App\KYC\Enums;

enum HypervergeDocument: string
{
    case DL = 'dl'; // Driver’s License
    case UMID = 'umid'; // Unified Multi-purpose ID


    public function label(): string
    {
        return match ($this) {
            self::DL => 'Driver\'s License',
            self::UMID => 'UMID Card',
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
