<?php

namespace App\Http\Controllers;

use App\Mail\SetupPasswordMail;
use App\Mail\ResetPasswordMail;
use App\Models\Streamer;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\StreamerSubscription;
use App\Traits\ManagesSixDigitTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ManagesSixDigitTokens;

    /**
     * Universal login for all user types (streamers, admins, moderators)
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();
        
        if (!$user || !Hash::check($credentials['password'], $user->password ?? '')) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Check if account is active (email verified)
        if (!$user->email_verified_at) {
            return response()->json(['message' => 'Account not activated. Please check your email for activation instructions.'], 403);
        }

        // Create appropriate token based on role
        $tokenName = $user->role . '-api';
        $abilities = [$user->role];
        $token = $user->createToken($tokenName, $abilities)->plainTextToken;

        $response = [
            'token' => $token,
            'user' => $user,
            'role' => $user->role,
        ];

        // Include streamer data if user is a streamer
        if ($user->isStreamer()) {
            $streamer = $user->streamer;
            if ($streamer) {
                $streamer->load(['activeSubscription.subscriptionPlan']);
                $response['streamer'] = $streamer;
            }
        }

        return response()->json($response);
    }

    /**
     * Universal logout for all user types
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke all tokens for this user
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get user profile (works for all user types)
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $response = [
            'user' => $user,
            'role' => $user->role,
        ];

        // Include streamer data if user is a streamer
        if ($user->isStreamer()) {
            $streamer = $user->streamer;
            if ($streamer) {
                $streamer->load(['activeSubscription.subscriptionPlan']);
                $response['streamer'] = $streamer;
            }
        }

        return response()->json($response);
    }

    /**
     * Check authentication status
     */
    public function checkAuth(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['authenticated' => false], 401);
        }

        return response()->json([
            'authenticated' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role
        ]);
    }

    /**
     * Forgot password - Send reset token via email
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Check if user exists
        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'If an account with that email exists, a reset code has been sent.']);
        }

        // Generate 6-digit token and send email
        $token = $this->createSixDigitToken($user);
        Mail::to($user->email)->send(new ResetPasswordMail($token));

        return response()->json(['message' => 'Password reset code sent to your email.']);
    }

    /**
     * Validate password reset token
     */
    public function validateResetToken(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['valid' => false, 'message' => 'Invalid email address.']);
        }

        $repo = Password::getRepository();
        $valid = $repo->exists($user, $data['token']);

        return response()->json([
            'valid' => (bool) $valid,
            'message' => $valid ? 'Token is valid.' : 'Invalid or expired token.'
        ]);
    }

    /**
     * Reset password using token
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::where('email', $data['email'])->first();
        
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        
        // Verify the 6-digit token
        if (!$this->verifySixDigitToken($user, $data['token'])) {
            return response()->json(['message' => 'Invalid or expired reset code.'], 422);
        }

        // Update user password
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'email_verified_at' => $user->email_verified_at ?? now(), // Verify email if not already verified
        ])->save();

        // Invalidate all existing tokens for security
        $user->tokens()->delete();
        
        // Delete the used token
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }

    /**
     * Change password for authenticated users
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $request->user();
        
        if (!$user || !Hash::check($request->input('current_password'), $user->password ?? '')) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        // Invalidate all tokens except current one for security
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json(['message' => 'Password changed successfully']);
    }

    /**
     * Register streamer (specific to streamers)
     */
    public function registerStreamer(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['required', 'string', 'max:255', 'unique:streamers,username'],
            'full_name' => ['required', 'string', 'max:255'],
            'plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'custom_plan' => ['nullable', 'array'],
            'custom_plan.views_delivered' => ['required_if:custom_plan,!=,null', 'integer', 'min:1'],
            'custom_plan.chat_messages_delivered' => ['required_if:custom_plan,!=,null', 'integer', 'min:1'],
            'custom_plan.duration_hours' => ['required_if:custom_plan,!=,null', 'integer', 'min:1'],
            'custom_plan.duration_days' => ['required_if:custom_plan,!=,null', 'integer', 'min:1'],
            'custom_plan.price' => ['required_if:custom_plan,!=,null', 'numeric', 'min:0'],
        ]);

        // Create a user with a temporary random password
        $user = User::create([
            'email' => $data['email'],
            'password' => Hash::make(Str::random(32)),
            'role' => 'streamer',
        ]);

        $streamer = Streamer::create([
            'user_id' => $user->id,
            'username' => $data['username'],
            'full_name' => $data['full_name'],
        ]);

        // Handle plan selection
        $planId = null;
        
        if (isset($data['custom_plan'])) {
            // Create custom plan
            $customPlan = SubscriptionPlan::create([
                'name' => "Custom Plan for {$data['username']}",
                'description' => 'Custom plan created during registration',
                'price' => $data['custom_plan']['price'],
                'duration_days' => $data['custom_plan']['duration_days'],
                'duration_hours' => $data['custom_plan']['duration_hours'],
                'views_delivered' => $data['custom_plan']['views_delivered'],
                'chat_messages_delivered' => $data['custom_plan']['chat_messages_delivered'],
                'features' => [],
                'is_active' => false,
                'is_most_popular' => false,
            ]);
            $planId = $customPlan->id;
        } elseif (isset($data['plan_id'])) {
            $planId = $data['plan_id'];
        }

        // Create subscription if plan is selected
        if ($planId) {
            // Get the plan duration
            $durationDays = 30; // Default duration
            
            if (isset($data['custom_plan'])) {
                $durationDays = $data['custom_plan']['duration_days'];
            } else {
                // For regular plans, get duration from the subscription plan
                $plan = SubscriptionPlan::find($planId);
                if ($plan) {
                    $durationDays = $plan->duration_days;
                }
            }
            
            StreamerSubscription::create([
                'streamer_id' => $streamer->id,
                'subscription_plan_id' => $planId,
                'status' => 'pending', // Will be activated after payment
                'start_date' => now()->addDay(),
                'end_date' => now()->addDay()->addDays($durationDays),
            ]);
        }

        // Create setup token and send email
        $token = $this->createSixDigitToken($user);
        Mail::to($user->email)->send(new SetupPasswordMail($token));

        return response()->json([
            'message' => 'Registration successful. Setup instructions sent to your email.',
            'plan_selected' => $planId !== null,
            'custom_plan_created' => isset($data['custom_plan'])
        ]);
    }

    /**
     * Set password using setup token (for new registrations)
     */
    public function setPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::where('email', $data['email'])->first();
        
        // Verify the 6-digit token
        if (!$this->verifySixDigitToken($user, $data['token'])) {
            return response()->json(['message' => 'Invalid or expired token.'], 422);
        }

        // Update user password and activate account
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(), // Activate account
        ])->save();

        // Clear any existing tokens
        $user->tokens()->delete();
        
        // Delete the used token
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        return response()->json(['message' => 'Password set successfully. You can now login.']);
    }

    /**
     * Resend setup email for new accounts
     */
    public function resendSetupEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $user = User::where('email', $data['email'])->first();
        
        // Only allow resending if account is not yet activated
        if ($user->email_verified_at) {
            return response()->json(['message' => 'Account is already activated.'], 422);
        }

        // Create new setup token and send email
        $token = $this->createSixDigitToken($user);
        Mail::to($user->email)->send(new SetupPasswordMail($token));

        return response()->json(['message' => 'Setup instructions sent to your email.']);
    }

    /**
     * Validate setup token (for new registrations)
     */
    public function validateSetupToken(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        
        // Debug the incoming token
        \Log::info("Validating setup token:", [
            'email' => $data['email'],
            'received_token' => $data['token'],
            'token_length' => strlen($data['token']),
            'token_type' => gettype($data['token']),
        ]);
        
        $valid = $this->verifySixDigitToken($user, $data['token']);

        return response()->json([
            'valid' => (bool) $valid,
            'message' => $valid ? 'Token is valid.' : 'Invalid or expired token.'
        ]);
    }
}
