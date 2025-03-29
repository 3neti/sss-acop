<?php

namespace App\Data;

use Spatie\LaravelData\{Data, Optional};

class KYCModuleResultData extends Data
{
    public function __construct(
        public ?string $module,
        public string|Optional $countrySelected,
        public string|Optional $documentSelected,
        public ?string $moduleId,
        public ?string $imageUrl,
        public ?string $croppedImageUrl,
        public ?int $attempts,
        public ?KYCModuleApiResponseData $apiResponse,
        public mixed $previousAttempts = null, // can refine later
    ) {}
}
