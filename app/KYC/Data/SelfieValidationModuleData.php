<?php

namespace App\KYC\Data;

use Spatie\LaravelData\Data;

class SelfieValidationModuleData extends Data
{
    public function __construct(
        public ?string $moduleId,
        public ?string $imageUrl,
    ) {}
}
