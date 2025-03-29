<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class IdCardValidationModuleData extends Data
{
    public function __construct(
        public ?string $moduleId,
        public ?string $countrySelected,
        public ?string $documentSelected,
        public ?string $croppedImageUrl,
        public ?string $idType,
        public array $fields,
    ) {}
}
