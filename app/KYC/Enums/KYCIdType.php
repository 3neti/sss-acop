<?php

namespace App\KYC\Enums;

enum KYCIdType: string
{
    case PHL_DL = 'phl_dl';
    case PHL_UMID = 'phl_umid';
    case PHL_SYS = 'philsys';

    public function label(): string
    {
        return match ($this) {
            self::PHL_DL   => 'Philippine Driver\'s License',
            self::PHL_UMID => 'Unified Multi-purpose ID',
            self::PHL_SYS => 'Philippine ID',
        };
    }

    /**
     * Optionally return list for dropdowns or selects
     */
    public static function options(): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases()
        );
    }

    public static function random(): self
    {
        return collect(self::cases())->random();
    }

    public function country(): HypervergeCountry
    {
        return HypervergeCountry::PHL;
    }

    public function document(): HypervergeDocument
    {
        return HypervergeDocument::DL;
    }
}
