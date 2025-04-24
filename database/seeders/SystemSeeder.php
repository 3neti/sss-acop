<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Seeder;
use App\Commerce\Models\System;

class SystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Artisan::call('user:create-system');
        echo Artisan::output();
        $token = tap(System::first(), function($system) {
            $system->email = 'lester@hurtado.ph';
            $system->mobile = '09173011987';
            $system->save();
        })->createToken('vendor-api')->plainTextToken;
//        $token = $vendor->createToken('vendor-api')->plainTextToken;
        echo 'API Token ' . $token . "\n";

        // Copy to clipboard based on OS
        if (PHP_OS_FAMILY === 'Darwin') {
            // macOS
            exec("echo '{$token}' | pbcopy");
        } elseif (PHP_OS_FAMILY === 'Linux') {
            // Linux (with xclip installed)
            exec("echo '{$token}' | xclip -selection clipboard");
        } elseif (PHP_OS_FAMILY === 'Windows') {
            // Windows
            exec("echo {$token} | clip");
        }

        echo "Vendor token copied to clipboard!\n";
    }
}
