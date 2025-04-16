<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;
use App\Models\Guest;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clean slate
        DB::table('users')->truncate();

// Create Admin
        $admin = Admin::create([
            'name' => 'Rachelle Frazier',
            'email' => 'larissa82@example.org',
            'password' => bcrypt('secret'),
            'id_number' => '123456789',
            'birthdate' => '1990-01-01',
        ]);

// Create Guest
        $guest = Guest::create([
            'name' => 'Leah Brown',
            'email' => 'west.susana@example.org',
            'password' => bcrypt('secret'),
            'id_number' => '234567890',
            'birthdate' => '1990-01-01',
        ]);

// Fetch all users and dump their resolved class
        $users = User::all();
        foreach ($users as $user) {
            dump(get_class($user)); // Should be App\Models\Admin or App\Models\Guest
        }

// Factory-based approach (using the same UserFactory)
        $admin = Admin::factory()->create();
        $guest = Guest::factory()->create();

// Polymorphic resolution
        $resolvedAdmin = User::find($admin->id);
        $resolvedGuest = User::find($guest->id);

        dump(get_class($resolvedAdmin)); // Should be App\Models\Admin
        dump(get_class($resolvedGuest)); // Should be App\Models\Guest
    }
}
