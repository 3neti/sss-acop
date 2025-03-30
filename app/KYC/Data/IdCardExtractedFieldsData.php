<?php

namespace App\KYC\Data;

use Spatie\LaravelData\Data;

class IdCardExtractedFieldsData extends Data
{
    public function __construct(
        public string $firstName,
        public string $middleName,
        public string $lastName,
        public string $fullName,
        public string $dateOfBirth,
        public string $dateOfExpiry,
        public string $gender,
        public string $address,
        public string $idNumber,
        public string $nationality,
    ) {}
}
