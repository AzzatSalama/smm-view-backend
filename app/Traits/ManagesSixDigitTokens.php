<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

trait ManagesSixDigitTokens
{
    /**
     * Generate a 6-digit token and store it in the password_reset_tokens table
     */
    private function createSixDigitToken(User $user): string
    {
        // Generate a 6-digit token
        $token = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Delete any existing tokens for this user
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        
        $hashedToken = Hash::make($token);
        
        // Store the new token
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $hashedToken,
            'created_at' => now(),
        ]);
        
        return $token;
    }

    /**
     * Verify a 6-digit token
     */
    private function verifySixDigitToken(User $user, string $token): bool
    {
        // Ensure token is trimmed and clean
        $token = trim($token);
        
        $record = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();
            
        if (!$record) {
            return false;
        }
        
        // Parse the created_at timestamp
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        $minutesDiff = now()->diffInMinutes($createdAt);
        
        // Check if token has expired (60 minutes)
        if ($minutesDiff > 60) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            return false;
        }
        
        return Hash::check($token, $record->token);
    }
}
