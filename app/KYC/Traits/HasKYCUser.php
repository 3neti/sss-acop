<?php

namespace App\KYC\Traits;

use App\KYC\Enums\{HypervergeCountry, KYCIdType};
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Support\Str;

trait HasKYCUser
{
    use InteractsWithMedia;

    public static function bootHasKYCUser(): void
    {
        static::creating(function ($user) {
            $user->password ??= bcrypt('password');
            $user->country ??= HypervergeCountry::PHL;
        });
    }

    public function initializeHasKYCUser(): void
    {
        $this->mergeFillable([
            'id_value',
            'id_type',
            'mobile',
            'country',
            'birthdate',
        ]);

        $this->mergeCasts([
            'country' => HypervergeCountry::class,
            'id_type' => KYCIdType::class,
            'birthdate' => 'date',
        ]);
    }

    public function getPhotoAttribute(): ?Media
    {
        return $this->getFirstMedia('photo');
    }

    public function getKYCIdentifier(): string
    {
        return $this->id_value ?? $this->mobile ?? 'unknown';
    }

    public function getCountry(): ?HypervergeCountry
    {
        return $this->country instanceof HypervergeCountry
            ? $this->country
            : HypervergeCountry::tryFrom(Str::lower($this->country));
    }

    public function getIdType(): ?KYCIdType
    {
        return $this->id_type instanceof KYCIdType
            ? $this->id_type
            : KYCIdType::tryFrom(Str::lower($this->id_type));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }
}
