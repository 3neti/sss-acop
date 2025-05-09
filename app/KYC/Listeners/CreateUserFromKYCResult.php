<?php

namespace App\KYC\Listeners;

use App\KYC\Enums\KYCIdType;
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

        if (! $parsed->idValue() || ! $parsed->fullName()) {
            Log::warning('[CreateUserFromKYCResult] Incomplete parsed KYC data', [
                'transactionId' => $event->transactionId,
            ]);
            return;
        }

        // Attempt to resolve user via identification table
//        $user = User::whereHas('identifications', fn ($q) =>
//        $q->where('id_type', $parsed->idType())
//            ->where('id_value', $parsed->idValue())
//        )->first();

        $user = User::findByIdentification($parsed->idType(), $parsed->idValue());

        // If user doesn't exist, create new one
        if (! $user) {
            $user = User::create([
                'name'      => $parsed->fullName(),
                'birthdate' => $parsed->birthdate(),
                'country'   => $parsed->country(),
                'email'     => null,
                'type'      => 'vendor',
            ]);

            $user->identifications()->create([
                'id_type'  => $parsed->idType(),
                'id_value' => $parsed->idValue(),
            ]);
        }

        // Ensure user is a vendor
        if ($user->type !== 'vendor') {
            $user->update(['type' => 'vendor']);
        }

        // Attach profile photo from KYC if available
        if ($parsed->photo()) {
            $user->addMediaFromUrl($parsed->photo())
                ->toMediaCollection('photo');
        }

        // Add optional identifiers (email, mobile, etc.)
        foreach (['email', 'mobile'] as $field) {
            if ($value = $parsed->{$field}()) {
                $user->identifications()->firstOrCreate([
                    'id_type'  => KYCIdType::from($field),
                    'id_value' => $value,
                ]);
            }
        }

        // Cache user ID for redirect/login flow
        cache()->put("onboard_user_{$event->transactionId}", $user->id);

        Log::info('[CreateUserFromKYCResult] User onboarded', [
            'transactionId' => $event->transactionId,
            'user_id'       => $user->id,
            'payload'       => $parsed->toArray(),
        ]);
    }

//    public function handle(KYCResultFetched $event): void
//    {
//        $parsed = new ParsedKYCResult(
//            kyc: $event->data,
//            idCardModule: ExtractIdCardValidationModule::run($event->data),
//            selfieModule: ExtractSelfieValidationModule::run($event->data),
//        );
//
//        if (! $parsed->idValue() || ! $parsed->idType() || ! $parsed->fullName()) {
//            Log::warning('[CreateUserFromKYCResult] Incomplete parsed KYC data', [
//                'transactionId' => $event->transactionId,
//            ]);
//            return;
//        }
//
//        // Attempt to resolve user via identification table
//        $user = User::whereHas('identifications', fn ($query) => $query
//            ->where('id_type', $parsed->idType())
//            ->where('id_value', $parsed->idValue())
//        )->first();
//
//        // If not found, create new user
//        if (! $user) {
//            $user = User::create([
//                'name'      => $parsed->fullName(),
//                'birthdate' => $parsed->birthdate(),
//                'country'   => $parsed->country(),
//                'type'      => 'vendor',
//            ]);
//        }
//
//        // Ensure user has vendor role
//        if ($user->type !== 'vendor') {
//            $user->update(['type' => 'vendor']);
//        }
//
//        // Attach selfie photo if available
//        if ($parsed->photo()) {
//            $user->addMediaFromUrl($parsed->photo())
//                ->toMediaCollection('photo');
//        }
//
//        // Attach primary KYC identification
//        $user->identifications()->firstOrCreate([
//            'id_type'  => $parsed->idType(),
//            'id_value' => $parsed->idValue(),
//        ]);
//
//        // Attach optional identifiers (email, mobile, pin, etc.)
//        foreach (['email', 'mobile'] as $field) {
//            if ($value = $parsed->{$field}()) {
//                $user->identifications()->firstOrCreate([
//                    'id_type'  => KYCIdType::from($field),
//                    'id_value' => $value,
//                ]);
//            }
//        }
//
//        // Link transactionId to user ID for callback routing
//        cache()->put("onboard_user_{$event->transactionId}", $user->id);
//
//        Log::info('[CreateUserFromKYCResult] User created or updated from KYC result', [
//            'transactionId' => $event->transactionId,
//            'user_id' => $user->id,
//            'payload' => $parsed->toArray(),
//        ]);
//    }

//    public function handle(KYCResultFetched $event): void
//    {
//        $parsed = new ParsedKYCResult(
//            kyc: $event->data,
//            idCardModule: ExtractIdCardValidationModule::run($event->data),
//            selfieModule: ExtractSelfieValidationModule::run($event->data),
//        );
//
//        if (! $parsed->idValue() || ! $parsed->fullName()) {
//            Log::warning('[CreateUserFromKYCResult] Incomplete parsed KYC data', [
//                'transactionId' => $event->transactionId,
//            ]);
//            return;
//        }
//
//        $user = User::firstOrCreate(
//            ['id_value' => $parsed->idValue()], // stronger key
//            [
//                'name' => $parsed->fullName(),
//                'birthdate' => $parsed->birthdate(),
//                'id_type' => $parsed->idType(),
//                'country' => $parsed->country(),
//                'email' => null,
//                'type' => 'vendor',
//            ]
//        );
//
//        // Force upgrade if type was not previously set
//        if ($user->type !== 'vendor') {
//            $user->update(['type' => 'vendor']);
//        }
//
//        if ($parsed->photo()) {
//            $user->addMediaFromUrl($parsed->photo())
//                ->toMediaCollection('photo');
//        }
//
//        cache()->put("onboard_user_{$event->transactionId}", $user->id);
//
//        Log::info('[CreateUserFromKYCResult] User created from KYC result', [
//            'transactionId' => $event->transactionId,
//            'user_id' => $user->id,
//            'data' => $event->data,
//            'payload' => $parsed->toArray(),
//        ]);
//    }
}
