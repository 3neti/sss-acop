<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class SelfieValidationModuleData extends Data
{
    public function __construct(
        public ?string $moduleId,
        public ?string $imageUrl,
    ) {}
}
