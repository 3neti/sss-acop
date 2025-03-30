<?php

namespace App\KYC\Listeners;

use App\KYC\Actions\{ExtractIdCardValidationModule, ExtractSelfieValidationModule};
use App\KYC\Events\KYCResultFetched;
use App\KYC\Support\ParsedKYCResult;
use Illuminate\Support\Facades\Log;
use App\Models\User;

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
                'country' => $parsed->country(),
                'email' => null,
            ]
        );

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
