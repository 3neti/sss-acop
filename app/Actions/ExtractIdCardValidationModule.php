<?php

namespace App\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Data\IdCardValidationModuleData;
use App\Data\KYCResultData;

class ExtractIdCardValidationModule
{
    use AsAction;

    protected string $targetModuleName = 'ID Card Validation front';

    public function handle(KYCResultData $kyc): ?IdCardValidationModuleData
    {
        $modules = $kyc->result->results ?? [];
        foreach ($modules as $module) {
            if ($module->module == $this->targetModuleName) {
                $api = $module->apiResponse->result['details'][0]['fieldsExtracted'];
                $flatFields = collect($api)->mapWithKeys(function ($field, $key) {
                    return [$key => $field['value'] ?? null];
                });

                return new IdCardValidationModuleData(
                    moduleId:$module->moduleId ?? null,
                    countrySelected: $module->countrySelected ?? null,
                    documentSelected: $module->documentSelected ?? null,
                    croppedImageUrl: $module->croppedImageUrl ?? null,
                    idType: $module->apiResponse->result['details'][0]['idType'],
                    fields: $flatFields->toArray()
                );
            }
        }

        return null;
    }
}
