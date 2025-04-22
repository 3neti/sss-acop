<?php

namespace Tests\Browser;

use App\Commerce\Models\System;
use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\DuskTestCase;

class FacePaymentPageTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_displays_face_payment_page_with_provided_props()
    {
//        $user = User::factory()->create([
//            'email_verified_at' => now(),
//            'id_type' => 'philsys',
//            'id_number' => '6302-5389-1879-5682',
//        ]);

        $system = System::factory()->create();

        $this->browse(function (Browser $browser) use ($system) {
            $browser->loginAs($system)
                ->visit('/vendor/face-payment')
//                ->assertSee('Scan your face') // or any known text from the component
                ->assertSee('Amount')
//                ->assertSee('₱250')
                ->screenshot('face-payment-page')
            ;
        });
    }
}
