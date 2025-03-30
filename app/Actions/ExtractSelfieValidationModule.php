<?php

namespace App\Actions;

use App\Data\KYCResultData;
use App\Data\SelfieValidationModuleData;
use Lorisleiva\Actions\Concerns\AsAction;

class ExtractSelfieValidationModule
{
    use AsAction;

    protected string $targetModuleName = 'Selfie Validation';

    public function handle(KYCResultData $kyc): ?SelfieValidationModuleData
    {
        $modules = $kyc->result->results ?? [];

        foreach ($modules as $module) {
            if ($module->module === $this->targetModuleName) {
                return new SelfieValidationModuleData(
                    moduleId: $module->moduleId ?? null,
                    imageUrl: $module->imageUrl ?? null
                );
            }
        }

        return null;
    }
}
