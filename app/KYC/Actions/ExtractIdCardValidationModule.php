<?php

namespace App\KYC\Actions;

use App\KYC\Data\IdCardValidationModuleData;
use App\KYC\Data\IdCardExtractedFieldsData;
use Lorisleiva\Actions\Concerns\AsAction;
use App\KYC\Enums\HypervergeIdType;
use Illuminate\Support\{Arr, Str};
use App\KYC\Data\KYCResultData;


class ExtractIdCardValidationModule
{
    use AsAction;

    protected string $targetModuleName = 'ID Card Validation front';

    public function handle(KYCResultData $kyc): ?IdCardValidationModuleData
    {
        $modules = $kyc->result->results ?? [];
        foreach ($modules as $module) {
            if ($module->module !== $this->targetModuleName) {
                continue;
            }

            $details = $module->apiResponse->result['details'][0] ?? null;
            if (! $details || empty($details['fieldsExtracted'])) {
                return null;
            }

            return new IdCardValidationModuleData(
                moduleId: $module->moduleId ?? null,
                countrySelected: $module->countrySelected ?? null,
                documentSelected: $module->documentSelected ?? null,
                croppedImageUrl: $module->croppedImageUrl ?? null,
                idType: $this->castIdType($details['idType'] ?? null),
                fields: $this->mapHighConfidenceFields(Arr::get($details, 'fieldsExtracted', [])),
            );
        }

        return null;
    }

    protected function castIdType(?string $value): ?HypervergeIdType
    {
        return $value ? HypervergeIdType::tryFrom(Str::lower($value)) : null;
    }

    protected function mapHighConfidenceFields(array $fields): array
    {
        $high = fn($field) => ($field['confidence'] ?? null) === 'high' ? $field['value'] ?? null : null;

        return (new IdCardExtractedFieldsData(
            firstName: $high($fields['firstName'] ?? []),
            middleName: $high($fields['middleName'] ?? []),
            lastName: $high($fields['lastName'] ?? []),
            fullName: $high($fields['fullName'] ?? []),
            dateOfBirth: $high($fields['dateOfBirth'] ?? []),
            dateOfExpiry: $high($fields['dateOfExpiry'] ?? []),
            gender: $high($fields['gender'] ?? []),
            address: $high($fields['address'] ?? []),
            idNumber: $high($fields['idNumber'] ?? []),
            nationality: $high($fields['nationality'] ?? []),
        ))->toArray();
    }
}
