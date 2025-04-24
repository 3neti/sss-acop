<?php

namespace App\KYC\Data;

use App\KYC\Enums\{HypervergeCountry, HypervergeDocument, KYCIdType};
use Spatie\LaravelData\{Attributes\WithCast, Casts\EnumCast, Data, Optional};

class KYCModuleResultData extends Data
{
    public function __construct(
        public ?string $module,
        #[WithCast(EnumCast::class)]
        public ?HypervergeCountry $countrySelected,
        #[WithCast(EnumCast::class)]
        public ?HypervergeDocument $documentSelected,
        public ?string $moduleId,
        public ?string $imageUrl,
        public ?string $croppedImageUrl,
        public ?int $attempts,
        public ?KYCModuleApiResponseData $apiResponse,
        public mixed $previousAttempts = null, // can refine later
    ) {}
}
