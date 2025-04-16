<?php

namespace Database\Seeders;

use App\Commerce\Models\Vendor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendor = Vendor::factory()->create();
        $token = $vendor->createToken('vendor-api')->plainTextToken;
        echo $token;
    }
}
