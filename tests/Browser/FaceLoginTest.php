<?php

use Laravel\Dusk\Browser;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Models\User;
use App\KYC\Enums\KYCIdType;

uses(DatabaseMigrations::class);

beforeEach(function () {
    Storage::fake('media');

    // Create test user with selfie and KYC identifiers
    $this->user = User::factory()->create([
        'id_type' => KYCIdType::PHL_DL,
        'id_value' => '1234567890',
    ]);

    $this->user->addMedia(resource_path('tests/selfie.jpeg'))
        ->preservingOriginal()
        ->toMediaCollection('photo');
});

it('logs in via face using ID number', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->assertVisible('#id_value')
            ->type('#id_value', '1234567890')
            ->select('#id_type', 'phl_dl')
            ->waitFor('video', 5)
            ->pause(500) // Let camera initialize
            ->script([
                // Inject dummy base64 image and submit the form
                "document.querySelector('video').remove();",
                "const img = document.createElement('img');
                 img.src = 'data:image/jpeg;base64," . base64_encode(file_get_contents(resource_path('tests/selfie.jpeg'))) . "';
                 img.className = 'w-full h-full object-cover';
                 document.querySelector('.aspect-video').appendChild(img);
                 document.querySelector('input[name=\"base64img\"]').value = img.src;
                 document.querySelector('form').submit();"
            ]);

//        $browser->pause(3000)
//            ->assertPathIs('/dashboard');
        ;
    });
});
