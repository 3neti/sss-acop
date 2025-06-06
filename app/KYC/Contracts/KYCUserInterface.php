<?php

namespace App\KYC\Contracts;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\KYC\Enums\HypervergeCountry;
use App\KYC\Enums\KYCIdType;
use Spatie\MediaLibrary\HasMedia;

interface KYCUserInterface extends HasMedia
{
    public function getPhotoAttribute(): ?Media;

    public function getKYCIdentifier(): string;

    public function getCountry(): ?HypervergeCountry;

    public function getIdType(): ?KYCIdType;
}
