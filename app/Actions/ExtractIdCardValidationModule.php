<?php

namespace App\Actions;

use App\Data\KYCResultData;
use App\Data\IdCardExtractedFieldsData;
use App\Data\IdCardValidationModuleData;
use Lorisleiva\Actions\Concerns\AsAction;

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

            $fields = $details['fieldsExtracted'];

            $highConfidence = fn($field) => ($field['confidence'] ?? null) === 'high' ? $field['value'] ?? null : null;

            $typedFields = new IdCardExtractedFieldsData(
                firstName: $highConfidence($fields['firstName'] ?? []),
                middleName: $highConfidence($fields['middleName'] ?? []),
                lastName: $highConfidence($fields['lastName'] ?? []),
                fullName: $highConfidence($fields['fullName'] ?? []),
                dateOfBirth: $highConfidence($fields['dateOfBirth'] ?? []),
                dateOfExpiry: $highConfidence($fields['dateOfExpiry'] ?? []),
                gender: $highConfidence($fields['gender'] ?? []),
                address: $highConfidence($fields['address'] ?? []),
                idNumber: $highConfidence($fields['idNumber'] ?? []),
                nationality: $highConfidence($fields['nationality'] ?? []),
            );

            return new IdCardValidationModuleData(
                moduleId: $module->moduleId ?? null,
                countrySelected: $module->countrySelected ?? null,
                documentSelected: $module->documentSelected ?? null,
                croppedImageUrl: $module->croppedImageUrl ?? null,
                idType: $details['idType'] ?? null,
                fields: $typedFields->toArray()
            );
        }

        return null;
    }
}
