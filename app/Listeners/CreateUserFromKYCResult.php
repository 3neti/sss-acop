<?php

namespace App\Listeners;

use App\Events\KYCResultFetched;
use App\Models\User;
use App\Support\ParsedKYCResult;
use App\Actions\ExtractIdCardValidationModule;
use App\Actions\ExtractSelfieValidationModule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateUserFromKYCResult
{
    public function handle(KYCResultFetched $event): void
    {
        $parsed = new ParsedKYCResult(
            kyc: $event->data,
            idCardModule: ExtractIdCardValidationModule::run($event->data),
            selfieModule: ExtractSelfieValidationModule::run($event->data),
        );

        if (! $parsed->idNumber() || ! $parsed->fullName()) {
            Log::warning('[CreateUserFromKYCResult] Incomplete parsed KYC data', [
                'transactionId' => $event->transactionId,
            ]);
            return;
        }

        $user = User::firstOrCreate(
            ['id_number' => $parsed->idNumber()], // stronger key
            [
                'name' => $parsed->fullName(),
                'birthdate' => $parsed->birthdate(),
                'id_type' => $parsed->idType(),
                'email' => null,
            ]
        );

//        $user = User::firstOrCreate(
//            ['mobile' => $parsed->idNumber()], // assumes uniqueness
//            [
//                'name' => $parsed->fullName(),
//                'birthdate' => $parsed->birthdate() ?? now(),
//                'country' => 'PH',
//                'email' => null,
//            ]
//        );

        if ($parsed->photo()) {
            $user->addMediaFromUrl($parsed->photo())
                ->toMediaCollection('photo');
        }

        Log::info('[CreateUserFromKYCResult] User created from KYC result', [
            'transactionId' => $event->transactionId,
            'user_id' => $user->id,
        ]);
    }
}
