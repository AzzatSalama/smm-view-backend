<?php

namespace App\Http\Controllers;

use App\Mail\SetupPasswordMail;
use App\Models\Streamer;
use App\Models\User;
use App\Models\PlannedStream;
use App\Models\StreamerWordsLists;
use App\Services\DiscordService;
use App\Traits\ManagesSixDigitTokens;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class StreamerController extends Controller
{
    use ManagesSixDigitTokens;

    // Register streamer (no password). Sends setup token email.
    public function register(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['required', 'string', 'max:255', 'unique:streamers,username'],
            'full_name' => ['required', 'string', 'max:255'],
        ]);

        // Create a user with a temporary random password to satisfy not-null constraint
        $user = User::create([
            'email' => $data['email'],
            'password' => Hash::make(str()->random(32)),
            'role' => 'streamer',
        ]);

        Streamer::create([
            'user_id' => $user->id,
            'username' => $data['username'],
            'full_name' => $data['full_name'],
        ]);

        // Create or update a setup token using password broker table
        $token = $this->createSixDigitToken($user);
        Mail::to($user->email)->send(new SetupPasswordMail($token));

        return response()->json(['message' => 'Registration successful. Setup token sent to email.']);
    }

    // Set password using token received via email
    public function setPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => $data['email'],
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ])->save();

                // Invalidate all existing tokens on first password set
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password set successfully.']);
        }

        return response()->json(['message' => __($status)], 422);
    }

    // Login returns Sanctum token
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

        // Check if user is a streamer
        if (!$user->isStreamer()) {
            return response()->json(['message' => 'Access denied. Streamer account required.'], 403);
        }

        // Get streamer profile
        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        // Load subscription with plan details
        $streamer->load(['activeSubscription.subscriptionPlan']);

        // Create streamer-specific token
        $token = $user->createToken('streamer-api', ['streamer'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'streamer' => $streamer,
        ]);
    }

    // Streamer logout
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Revoke all streamer tokens
            $user->tokens()->where('name', 'streamer-api')->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    // Forgot password: send reset token email
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Reset link sent.']);
        }

        return response()->json(['message' => __($status)], 422);
    }

    // Validate token without changing password
    public function validateToken(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
        ]);

        $repo = Password::getRepository();
        $user = User::whereEmail($data['email'])->firstOrFail();
        $valid = $repo->exists($user, $data['token']);

        return response()->json(['valid' => (bool) $valid]);
    }

    // Check if auth token is valid (lightweight endpoint)
    public function checkAuth(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['authenticated' => false], 401);
        }

        // Check if user is a streamer
        if (!$user->isStreamer()) {
            return response()->json(['authenticated' => false], 403);
        }

        return response()->json([
            'authenticated' => true,
            'user_id' => $user->id,
            'email' => $user->email
        ]);
    }

    // Get authenticated user profile
    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        // Load subscription with plan details
        $streamer->load(['activeSubscription.subscriptionPlan']);

        return response()->json([
            'user' => $user,
            'streamer' => $streamer,
        ]);
    }

    // Change password for authenticated user (requires Sanctum auth)
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
        // Optionally revoke other tokens
        $user->tokens()->where('id', '!=', optional($request->user()->currentAccessToken())->id)->delete();

        return response()->json(['message' => 'Password changed successfully.']);
    }

    // Get streamer's planned streams
    public function getStreams(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        $streams = $streamer->plannedStreams()
            ->with('wordlist')
            ->orderBy('scheduled_start', 'desc')
            ->paginate(15);

        return response()->json([
            'streams' => $streams,
            'daily_limit_hours' => $streamer->getDailyStreamingLimit(),
            'has_active_subscription' => $streamer->hasActiveSubscription(),
        ]);
    }

    // Add a new planned stream
    public function addStream(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        // Check if streamer has active subscription
        if (!$streamer->hasActiveSubscription()) {
            return response()->json([
                'message' => 'You need an active subscription to schedule streams'
            ], 422);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scheduled_start' => ['required', 'date', 'after:now'],
            'estimated_duration' => ['required', 'integer', 'min:1', 'max:480'], // Max 8 hours
            'wordlist_id' => ['nullable', 'integer', 'exists:streamer_wordslists,id'],
        ]);

        // Extract date from scheduled_start for daily limit check
        $scheduledDate = Carbon::parse($data['scheduled_start'])->format('Y-m-d');

        // Check if adding this stream would exceed daily limit
        if (!$streamer->canAddStreamForDate($scheduledDate, $data['estimated_duration'])) {
            $remainingTime = $streamer->getRemainingStreamTimeForDate($scheduledDate);
            $dailyLimit = $streamer->getDailyStreamingLimit();

            return response()->json([
                'message' => 'Adding this stream would exceed your daily streaming limit',
                'daily_limit_hours' => $dailyLimit,
                'remaining_hours' => round($remainingTime, 2),
                'requested_hours' => round($data['estimated_duration'] / 60, 2),
            ], 422);
        }

        // Check for scheduling conflicts (streams within 30 minutes of each other)
        $scheduledStart = Carbon::parse($data['scheduled_start']);
        $conflictingStream = $streamer->plannedStreams()
            ->where('status', PlannedStream::STATUS_SCHEDULED)
            ->where(function ($query) use ($scheduledStart, $data) {
                $streamEnd = $scheduledStart->copy()->addMinutes($data['estimated_duration']);
                $query->whereBetween('scheduled_start', [
                    $scheduledStart->copy()->subMinutes(30),
                    $streamEnd->copy()->addMinutes(30)
                ]);
            })
            ->first();

        if ($conflictingStream) {
            return response()->json([
                'message' => 'This stream conflicts with another scheduled stream',
                'conflicting_stream' => [
                    'title' => $conflictingStream->title,
                    'scheduled_start' => $conflictingStream->scheduled_start,
                ]
            ], 422);
        }

        $stream = $streamer->plannedStreams()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scheduled_start' => $data['scheduled_start'],
            'estimated_duration' => $data['estimated_duration'],
            'wordlist_id' => $data['wordlist_id'] ?? null,
            'status' => PlannedStream::STATUS_SCHEDULED,
        ]);

        // Send Discord notification
        $this->sendDiscordNotification($streamer, $stream);

        return response()->json([
            'message' => 'Stream scheduled successfully',
            'stream' => $stream,
            'remaining_daily_hours' => round($streamer->getRemainingStreamTimeForDate($scheduledDate), 2),
        ], 201);
    }

    // Update an existing planned stream
    public function updateStream(Request $request, $streamId)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        $stream = $streamer->plannedStreams()->find($streamId);
        if (!$stream) {
            return response()->json(['message' => 'Stream not found'], 404);
        }

        // Can't update live or completed streams
        if (in_array($stream->status, [PlannedStream::STATUS_LIVE, PlannedStream::STATUS_COMPLETED])) {
            return response()->json([
                'message' => 'Cannot update a stream that is live or completed'
            ], 422);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'scheduled_start' => ['sometimes', 'date', 'after:now'],
            'estimated_duration' => ['sometimes', 'integer', 'min:1', 'max:480'],
            'wordlist_id' => ['sometimes', 'nullable', 'integer', 'exists:streamer_wordslists,id'],
            'status' => ['sometimes', 'in:' . implode(',', [
                PlannedStream::STATUS_SCHEDULED,
                PlannedStream::STATUS_CANCELLED
            ])],
        ]);

        // If updating duration or scheduled_start, check daily limits
        if (isset($data['estimated_duration']) || isset($data['scheduled_start'])) {
            $newDuration = (int) ($data['estimated_duration'] ?? $stream->estimated_duration);
            $newScheduledStart = isset($data['scheduled_start'])
                ? Carbon::parse($data['scheduled_start'])
                : $stream->scheduled_start;

            $scheduledDate = $newScheduledStart->format('Y-m-d');

            // Calculate current duration for the date excluding this stream
            $currentDurationExcludingThis = $streamer->plannedStreams()
                ->forDate($scheduledDate)
                ->where('id', '!=', $stream->id)
                ->whereIn('status', [PlannedStream::STATUS_SCHEDULED, PlannedStream::STATUS_LIVE, PlannedStream::STATUS_COMPLETED])
                ->get()
                ->sum(function ($s) {
                    return $s->getDurationInHours();
                });

            $dailyLimit = $streamer->getDailyStreamingLimit();
            $newStreamDuration = $newDuration / 60;

            if (($currentDurationExcludingThis + $newStreamDuration) > $dailyLimit) {
                return response()->json([
                    'message' => 'Updating this stream would exceed your daily streaming limit',
                    'daily_limit_hours' => $dailyLimit,
                    'current_usage_hours' => round($currentDurationExcludingThis, 2),
                    'requested_hours' => round($newStreamDuration, 2),
                ], 422);
            }
        }

        // Check for scheduling conflicts if updating scheduled_start or duration
        if (isset($data['scheduled_start']) || isset($data['estimated_duration'])) {
            $newScheduledStart = isset($data['scheduled_start'])
                ? Carbon::parse($data['scheduled_start'])
                : $stream->scheduled_start;
            $newDuration = (int) ($data['estimated_duration'] ?? $stream->estimated_duration);

            $conflictingStream = $streamer->plannedStreams()
                ->where('id', '!=', $stream->id)
                ->where('status', PlannedStream::STATUS_SCHEDULED)
                ->where(function ($query) use ($newScheduledStart, $newDuration) {
                    $streamEnd = $newScheduledStart->copy()->addMinutes($newDuration);
                    $query->whereBetween('scheduled_start', [
                        $newScheduledStart->copy()->subMinutes(30),
                        $streamEnd->copy()->addMinutes(30)
                    ]);
                })
                ->first();

            if ($conflictingStream) {
                return response()->json([
                    'message' => 'This update would create a conflict with another scheduled stream',
                    'conflicting_stream' => [
                        'title' => $conflictingStream->title,
                        'scheduled_start' => $conflictingStream->scheduled_start,
                    ]
                ], 422);
            }
        }

        $stream->update($data);

        return response()->json([
            'message' => 'Stream updated successfully',
            'stream' => $stream->fresh(),
        ]);
    }

    // Delete a planned stream
    public function deleteStream(Request $request, $streamId)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        $stream = $streamer->plannedStreams()->find($streamId);
        if (!$stream) {
            return response()->json(['message' => 'Stream not found'], 404);
        }

        // Can't delete live streams
        if ($stream->status === PlannedStream::STATUS_LIVE) {
            return response()->json([
                'message' => 'Cannot delete a live stream'
            ], 422);
        }

        // If this is the current stream, clear the reference
        if ($streamer->current_stream_id === $stream->id) {
            $streamer->update(['current_stream_id' => null]);
        }

        $stream->delete();

        return response()->json(['message' => 'Stream deleted successfully']);
    }

    // Start a scheduled stream (set it to live)
    public function startStream(Request $request, $streamId)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        $stream = $streamer->plannedStreams()->find($streamId);
        if (!$stream) {
            return response()->json(['message' => 'Stream not found'], 404);
        }

        if (!$stream->canBeStarted()) {
            return response()->json([
                'message' => 'Stream cannot be started yet or is not in scheduled status'
            ], 422);
        }

        // Check if streamer is already streaming
        if ($streamer->isCurrentlyStreaming()) {
            return response()->json([
                'message' => 'You are already streaming. End your current stream first.'
            ], 422);
        }

        $stream->update(['status' => PlannedStream::STATUS_LIVE]);
        $streamer->update(['current_stream_id' => $stream->id]);

        return response()->json([
            'message' => 'Stream started successfully',
            'stream' => $stream->fresh(),
        ]);
    }

    // End a live stream
    public function endStream(Request $request, $streamId)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        $stream = $streamer->plannedStreams()->find($streamId);
        if (!$stream) {
            return response()->json(['message' => 'Stream not found'], 404);
        }

        if ($stream->status !== PlannedStream::STATUS_LIVE) {
            return response()->json([
                'message' => 'Stream is not currently live'
            ], 422);
        }

        $stream->update(['status' => PlannedStream::STATUS_COMPLETED]);

        // Clear current stream reference if this was the current stream
        if ($streamer->current_stream_id === $stream->id) {
            $streamer->update(['current_stream_id' => null]);
        }

        return response()->json([
            'message' => 'Stream ended successfully',
            'stream' => $stream->fresh(),
        ]);
    }

    // Get streaming statistics for a specific date or date range
    public function getStreamingStats(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        $data = $request->validate([
            'date' => ['sometimes', 'date'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
        ]);

        if (isset($data['date'])) {
            // Single date stats
            $date = $data['date'];
            $totalDuration = $streamer->getTotalStreamDurationForDate($date);
            $remainingTime = $streamer->getRemainingStreamTimeForDate($date);
            $dailyLimit = $streamer->getDailyStreamingLimit();

            $streams = $streamer->plannedStreams()
                ->forDate($date)
                ->orderBy('scheduled_start')
                ->get();

            return response()->json([
                'date' => $date,
                'daily_limit_hours' => $dailyLimit,
                'used_hours' => round($totalDuration, 2),
                'remaining_hours' => round($remainingTime, 2),
                'streams' => $streams,
            ]);
        } else {
            // Date range stats (default to current month if no range provided)
            $startDate = isset($data['start_date']) ? $data['start_date'] : now()->startOfMonth()->format('Y-m-d');
            $endDate = isset($data['end_date']) ? $data['end_date'] : now()->endOfMonth()->format('Y-m-d');

            $streams = $streamer->plannedStreams()
                ->whereBetween('scheduled_start', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ])
                ->orderBy('scheduled_start')
                ->get();

            $totalHours = $streams->sum(function ($stream) {
                return $stream->getDurationInHours();
            });

            $streamsByStatus = $streams->groupBy('status')->map(function ($group) {
                return $group->count();
            });

            return response()->json([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_streams' => $streams->count(),
                'total_hours' => round($totalHours, 2),
                'streams_by_status' => $streamsByStatus,
                'daily_limit_hours' => $streamer->getDailyStreamingLimit(),
                'has_active_subscription' => $streamer->hasActiveSubscription(),
            ]);
        }
    }

    // Get available wordlists for stream creation
    public function getAvailableWordlists(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isStreamer()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $streamer = $user->streamer;
        if (!$streamer) {
            return response()->json(['message' => 'Streamer profile not found'], 404);
        }

        $wordlists = StreamerWordsLists::where('streamer_id', $streamer->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($wordlist) {
                // Extract type from filename
                $type = 'unknown';
                if (strpos($wordlist->filename, '_kcip_') !== false) {
                    $type = 'kcip';
                } elseif (strpos($wordlist->filename, '_gamble_') !== false) {
                    $type = 'gamble';
                }

                return [
                    'id' => $wordlist->id,
                    'type' => $type,
                    'filename' => $wordlist->filename,
                    'created_at' => $wordlist->created_at,
                    'display_name' => ucfirst($type) . ' Wordlist'
                ];
            });

        return response()->json([
            'wordlists' => $wordlists
        ]);
    }

    /**
     * Send Discord notification for new planned stream
     */
    private function sendDiscordNotification(Streamer $streamer, PlannedStream $stream): void
    {
        try {
            $discordService = app(DiscordService::class);
            
            // Get streamer subscription and plan info
            $subscription = $streamer->activeSubscription();
            if (!$subscription || !$subscription->plan) {
                return; // Skip if no active subscription
            }

            $plan = $subscription->plan;
            
            // Prepare streamer data
            $streamerData = [
                'name' => $streamer->full_name,
                'username' => $streamer->username,
            ];

            // Prepare plan data
            $planData = [
                'views' => $plan->daily_views_limit ?? 'Unlimited',
                'chats' => $plan->daily_chats_limit ?? 'Unlimited', 
                'hours' => $plan->daily_streaming_hours ?? 'Unlimited',
            ];

            // Prepare planned streams data (just the new stream for now)
            $plannedStreamsData = [[
                'name' => $stream->title,
                'start_date' => $stream->scheduled_start,
                'duration' => round($stream->estimated_duration / 60, 1), // Convert minutes to hours
            ]];

            $discordService->sendStreamerPlan($streamerData, $planData, $plannedStreamsData);
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::error('Discord notification failed: ' . $e->getMessage());
        }
    }
}
