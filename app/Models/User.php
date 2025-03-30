<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;

/**
 * Class User.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string      $mobile
 * @property string      $country
 * @property \DateTime   $birthdate
 *
 * @method int getKey()
 */
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;
    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'id_number',
        'id_type',
        'email',
        'mobile',
        'country',
        'birthdate',
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function booted(): void
    {
        static::creating(function (User $user) {
            $user->password = empty($user->password) ? bcrypt('password') : $user->password;
            $user->country = empty($user->country) ? 'PH' : $user->country;
        });
    }

    public function getPhotoAttribute()
    {
        return $this->getFirstMedia('photo');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }
}
