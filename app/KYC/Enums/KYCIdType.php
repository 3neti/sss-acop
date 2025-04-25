<?php

namespace App\KYC\Enums;

enum KYCIdType: string
{
    // Government-issued IDs
    case PHL_DL   = 'phl_dl';
    case PHL_UMID = 'phl_umid';
    case PHL_SYS  = 'philsys';

    // App-native identifiers
    case EMAIL    = 'email';
    case MOBILE   = 'mobile';
    case PIN      = 'pin';

    public function isNative(): bool
    {
        return in_array($this, [self::EMAIL, self::MOBILE, self::PIN]);
    }

    public function isKycId(): bool
    {
        return ! $this->isNative();
    }

    public static function kycOptions(): array
    {
        return array_filter(self::cases(), fn($case) => $case->isKycId());
    }

    public static function nativeOptions(): array
    {
        return array_filter(self::cases(), fn($case) => $case->isNative());
    }

    public function label(): string
    {
        return match ($this) {
            self::PHL_DL   => "Driver's License",
            self::PHL_UMID => 'UMID',
            self::PHL_SYS  => 'Philippine ID',
            self::EMAIL    => 'Email Address',
            self::MOBILE   => 'Mobile Number',
            self::PIN      => 'Security PIN',
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

    public function validationRules(): array
    {
        return match($this) {
            self::EMAIL => ['required', 'email'],
            self::MOBILE => ['required', 'regex:/^09\d{9}$/'],
            self::PIN => ['required', 'digits:4'],
            default => ['required'],
        };
    }
}
