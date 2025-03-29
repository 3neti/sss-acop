<?php

namespace App\Support;

use App\Data\IdCardValidationModuleData;
use App\Pipelines\MapIdType;
use Illuminate\Pipeline\Pipeline;
use App\Pipelines\TransformName;
use Illuminate\Support\Arr;
use App\Data\KYCResultData;
use Illuminate\Support\Str;

class ParsedKYCResult
{
    public function __construct(
        public readonly KYCResultData $kyc,
        public readonly ?IdCardValidationModuleData $idCardModule = null,
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

//    public function idType(): ?string
//    {
//        return $this->idCardModule?->idType;
//    }

//    public function fullName(): ?string
//    {
//        return $this->idCardModule?->fields['fullName'] ?? null;
//    }

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

//    public function idNumber(): ?string
//    {
//        return $this->idCardModule?->fields['idNumber'] ?? null;
//    }

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

    public function toArray(): array
    {
        return [
            'status' => $this->applicationStatus(),
            'idType' => $this->idType(),
            'fullName' => $this->fullName(),
            'address' => $this->address(),
            'idNumber' => $this->idNumber(),
            'birthDate' => $this->birthDate(),
        ];
    }

    public function getRaw(): array
    {
        return [
            'kyc' => $this->kyc,
            'idCardModule' => $this->idCardModule,
        ];
    }
}
