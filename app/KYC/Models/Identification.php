<?php

namespace App\KYC\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Database\Factories\IdentificationFactory;
use Illuminate\Database\Eloquent\Model;
use App\KYC\Enums\KYCIdType;
use App\Models\User;

class Identification extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_type',
        'id_value',
    ];

    protected $casts = [
        'id_type' => KYCIdType::class,
        'meta' => 'array',
    ];

    protected static function newFactory(): IdentificationFactory
    {
        return IdentificationFactory::new();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
