<?php

namespace App\KYC\Actions;

use App\KYC\Data\SelfieValidationModuleData;
use Lorisleiva\Actions\Concerns\AsAction;
use App\KYC\Data\KYCResultData;
use Illuminate\Support\Arr;

class ExtractSelfieValidationModule
{
    use AsAction;

    protected string $targetModuleName = 'Selfie Validation';

    public function handle(KYCResultData $kyc): ?SelfieValidationModuleData
    {
        $module = collect($kyc->result->results ?? [])
            ->firstWhere('module', $this->targetModuleName);

        if (! $module) {
            return null;
        }

        return new SelfieValidationModuleData(
            moduleId: $module->moduleId ?? null,
            imageUrl: Arr::get($module, 'imageUrl')
        );
    }

//    public function handle(KYCResultData $kyc): ?SelfieValidationModuleData
//    {
//        $modules = $kyc->result->results ?? [];
//
//        foreach ($modules as $module) {
//            if ($module->module === $this->targetModuleName) {
//                return new SelfieValidationModuleData(
//                    moduleId: $module->moduleId ?? null,
//                    imageUrl: $module->imageUrl ?? null
//                );
//            }
//        }
//
//        return null;
//    }
}
