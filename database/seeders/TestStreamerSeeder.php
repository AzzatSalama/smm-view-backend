<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Streamer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestStreamerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the user
        $user = User::create([
            'email' => 'salamaazzat8@gmail.com',
            'password' => Hash::make('12345678'),
            'role' => 'streamer',
            'email_verified_at' => now(), // Mark email as verified
        ]);

        // Create the streamer profile
        Streamer::create([
            'user_id' => $user->id,
            'username' => 'salamaazzat',
            'full_name' => 'Salama Azzat',
            'current_stream_id' => null,
        ]);

        $this->command->info('Test streamer created successfully!');
        $this->command->info('Email: salamaazzat8@gmail.com');
        $this->command->info('Password: 12345678');
    }
}
