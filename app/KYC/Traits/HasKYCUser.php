<?php

namespace App\KYC\Traits;

use App\KYC\Enums\{HypervergeCountry, HypervergeIdType};
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
            'id_number',
            'id_type',
            'mobile',
            'country',
            'birthdate',
        ]);

        $this->mergeCasts([
            'country' => HypervergeCountry::class,
            'id_type' => HypervergeIdType::class,
            'birthdate' => 'date',
        ]);
    }

    public function getPhotoAttribute(): ?Media
    {
        return $this->getFirstMedia('photo');
    }

    public function getKYCIdentifier(): string
    {
        return $this->id_number ?? $this->mobile ?? 'unknown';
    }

    public function getCountry(): ?HypervergeCountry
    {
        return $this->country instanceof HypervergeCountry
            ? $this->country
            : HypervergeCountry::tryFrom(Str::lower($this->country));
    }

    public function getIdType(): ?HypervergeIdType
    {
        return $this->id_type instanceof HypervergeIdType
            ? $this->id_type
            : HypervergeIdType::tryFrom(Str::lower($this->id_type));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }
}
