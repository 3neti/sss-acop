<?php

namespace App\KYC\Traits;

use App\KYC\Enums\KYCIdType;

trait HasBridgeIdentifiers
{
    //don't put email here
    protected array $bridgeAttributes = ['pin', 'mobile'];

    public function initializeHasBridgeIdentifiers(): void
    {
        $this->mergeFillable([
            'mobile',
        ]);
    }

    public function resolveIdentifier(KYCIdType $type): ?string
    {
        return $this->identifications
            ->firstWhere('id_type', $type)?->id_value;
    }

    public function setIdentifier(KYCIdType $type, ?string $value): void
    {
        $this->identifications()
            ->updateOrCreate(
                ['id_type' => $type],
                ['id_value' => $value]
            );
    }

    protected function mapBridgeAttribute(string $key): ?KYCIdType
    {
        return KYCIdType::tryFrom($key);
    }

    public function __get($key)
    {
        if (in_array($key, $this->bridgeAttributes)) {
            return $this->resolveIdentifier($this->mapBridgeAttribute($key));
        }

        return parent::__get($key);
    }

    public function __set($key, $value)
    {
        if (in_array($key, $this->bridgeAttributes)) {
            $this->setIdentifier($this->mapBridgeAttribute($key), $value);
            return;
        }

        parent::__set($key, $value);
    }
}
