<?php

namespace App\KYC\Support;

use App\KYC\Data\{IdCardValidationModuleData, KYCResultData, SelfieValidationModuleData};
use App\KYC\Enums\HypervergeCountry;
use App\KYC\Pipelines\TransformName;
use Illuminate\Support\{Arr, Str};
use Illuminate\Pipeline\Pipeline;
use App\KYC\Pipelines\MapIdType;

class ParsedKYCResult
{
    public function __construct(
        public readonly KYCResultData $kyc,
        public readonly ?IdCardValidationModuleData $idCardModule = null,
        public ?SelfieValidationModuleData $selfieModule = null,
    ) {}

    public function applicationStatus(): string
    {
        return $this->kyc->result->applicationStatus ?? 'unknown';
    }

    public function idType(): ?string
    {
        $raw = $this->idCardModule?->idType;

        return $raw
            ? app(Pipeline::class)->send($raw)
                ->through([MapIdType::class])
                ->thenReturn()
            : null;
    }

    public function country(): ?HypervergeCountry
    {
        return $this->idCardModule?->countrySelected;
    }

    public function fullName(): ?string
    {
        $raw = Arr::get($this->idCardModule?->fields, 'fullName');

        return $raw
            ? app(Pipeline::class)->send($raw)
                ->through([TransformName::class])
                ->thenReturn()
            : null;
    }

    public function address(): ?string
    {
        return Str::of(Arr::get($this->idCardModule?->fields, 'address'))->title();
    }

    public function idNumber(): ?string
    {
        return Arr::get($this->idCardModule?->fields, 'idNumber');
    }

    public function birthdate(): ?string
    {
        return Arr::get($this->idCardModule?->fields, 'dateOfBirth');
    }

    public function gender(): ?string
    {
        return Str::of(Arr::get($this->idCardModule?->fields, 'gender'))
            ->upper()
            ->replace('M', 'Male')
            ->replace('F', 'Female')
            ->toString();
    }

    /**
     * Get the selfie image URL from the Selfie Validation module.
     */
    public function photo(): ?string
    {
        return $this->selfieModule?->imageUrl ?? null;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->applicationStatus(),
            'idType' => $this->idType(),
            'fullName' => $this->fullName(),
            'address' => $this->address(),
            'idNumber' => $this->idNumber(),
            'birthDate' => $this->birthdate(),
            'photo' => $this->photo(),
        ];
    }

    public function getRaw(): array
    {
        return [
            'kyc' => $this->kyc,
            'idCardModule' => $this->idCardModule,
            'selfieModule' => $this->selfieModule,
        ];
    }
}
