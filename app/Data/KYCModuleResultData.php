<?php

namespace App\Data;

namespace App\Data;

use Spatie\LaravelData\Data;

class KYCModuleResultData extends Data
{
    public function __construct(
        public ?string $module,
        public ?string $moduleId,
        public ?string $imageUrl,
        public ?string $croppedImageUrl,
        public ?int $attempts,
        public ?KYCModuleApiResponseData $apiResponse,
        public mixed $previousAttempts = null, // can refine later
    ) {}
}
