<?php

namespace App\KYC\Data;

use App\KYC\Enums\{HypervergeCountry, HypervergeDocument, KYCIdType};
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class IdCardValidationModuleData extends Data
{
    public function __construct(
        public ?string             $moduleId,
        #[WithCast(EnumCast::class)]
        public ?HypervergeCountry  $countrySelected,
        #[WithCast(EnumCast::class)]
        public ?HypervergeDocument $documentSelected,
        public ?string             $croppedImageUrl,
        #[WithCast(EnumCast::class)]
        public ?KYCIdType          $idType,
        public array               $fields,
    ) {}
}
