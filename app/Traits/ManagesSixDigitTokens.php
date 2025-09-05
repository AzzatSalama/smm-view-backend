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
        
        // Debug logging
        \Log::info("Token created for email {$user->email}:", [
            'plain_token' => $token,
            'hashed_token' => $hashedToken,
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
            \Log::error("Token verification failed: No record found for email {$user->email}");
            return false;
        }
        
        // Parse the created_at timestamp
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        $minutesDiff = now()->diffInMinutes($createdAt);
        
        // Debug logging
        \Log::info("Token verification debug:", [
            'email' => $user->email,
            'input_token' => $token,
            'stored_token_hash' => $record->token,
            'created_at' => $record->created_at,
            'created_at_parsed' => $createdAt->toDateTimeString(),
            'minutes_diff' => $minutesDiff,
        ]);
        
        // Check if token has expired (60 minutes)
        if ($minutesDiff > 60) {
            \Log::error("Token verification failed: Token expired for email {$user->email}. Minutes diff: {$minutesDiff}");
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            return false;
        }
        
        $hashCheck = Hash::check($token, $record->token);
        \Log::info("Hash check result: " . ($hashCheck ? 'PASS' : 'FAIL'), [
            'input_token' => $token,
            'input_token_length' => strlen($token),
            'hash_check_input' => $token,
            'stored_hash' => $record->token,
        ]);
        
        // Additional debug: try to verify with different token formats
        if (!$hashCheck) {
            \Log::info("Trying alternative token formats:");
            $tokenAsInt = (string)intval($token);
            $tokenPadded = str_pad($token, 6, '0', STR_PAD_LEFT);
            \Log::info("Token as int: {$tokenAsInt}, Hash check: " . (Hash::check($tokenAsInt, $record->token) ? 'PASS' : 'FAIL'));
            \Log::info("Token padded: {$tokenPadded}, Hash check: " . (Hash::check($tokenPadded, $record->token) ? 'PASS' : 'FAIL'));
        }
        
        return $hashCheck;
    }
}
