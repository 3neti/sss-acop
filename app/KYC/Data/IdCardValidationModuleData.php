<?php

namespace App\KYC\Data;

use App\KYC\Enums\{HypervergeCountry, HypervergeDocument, HypervergeIdType};
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

class IdCardValidationModuleData extends Data
{
    public function __construct(
        public ?string $moduleId,
        #[WithCast(EnumCast::class)]
        public ?HypervergeCountry $countrySelected,
        #[WithCast(EnumCast::class)]
        public ?HypervergeDocument $documentSelected,
        public ?string $croppedImageUrl,
        #[WithCast(EnumCast::class)]
        public ?HypervergeIdType $idType,
        public array $fields,
    ) {}
}
