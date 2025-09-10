<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Streamer;
use App\Models\SubscriptionPlan;
use App\Models\Payment;
use App\Models\PlannedStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Admin login
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

        // Check if user is admin
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Access denied. Admin privileges required.'], 403);
        }

        // Create admin-specific token
        $token = $user->createToken('admin-api', ['admin'])->plainTextToken;
        
        return response()->json([
            'token' => $token,
            'user' => $user,
            'role' => 'admin'
        ]);
    }

    /**
     * Get admin profile
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'user' => $user,
            'role' => 'admin'
        ]);
    }

    /**
     * Admin logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            // Revoke all admin tokens
            $user->tokens()->where('name', 'admin-api')->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    // ============ USER MANAGEMENT ============

    /**
     * Get all users
     */
    public function getUsers(Request $request)
    {
        $query = User::query();

        // Filter by role if specified
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'users' => $users,
            'message' => 'Users retrieved successfully'
        ]);
    }

    /**
     * Create new user
     */
    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'role' => ['required', Rule::in(['admin', 'moderator', 'streamer'])],
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(), // Admin-created users are auto-verified
        ]);

        return response()->json([
            'user' => $user,
            'message' => 'User created successfully'
        ], 201);
    }

    /**
     * Update user
     */
    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'email' => ['email', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => [Rule::in(['admin', 'moderator', 'streamer'])],
            'password' => 'sometimes|string|min:8',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'user' => $user->fresh(),
            'message' => 'User updated successfully'
        ]);
    }

    /**
     * Delete user
     */
    public function deleteUser(User $user)
    {
        // Prevent deleting the last admin
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return response()->json([
                'message' => 'Cannot delete the last admin user'
            ], 422);
        }

        // Delete associated streamer if exists
        if ($user->streamer) {
            $user->streamer->delete();
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Toggle user status (activate/deactivate)
     */
    public function toggleUserStatus(User $user)
    {
        // For now, we'll use email_verified_at as status indicator
        // NULL = inactive, timestamp = active
        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        $status = $user->email_verified_at ? 'active' : 'inactive';

        return response()->json([
            'user' => $user,
            'status' => $status,
            'message' => "User {$status} successfully"
        ]);
    }

    // ============ STREAMER MANAGEMENT ============

    /**
     * Get all streamers with their details
     */
    public function getStreamers(Request $request)
    {
        $query = Streamer::with(['user', 'activeSubscription.subscriptionPlan', 'plannedStreams'])
            ->withCount(['plannedStreams as total_streams']);

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhereHas('user', function($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by subscription status
        if ($request->has('subscription_status')) {
            if ($request->subscription_status === 'active') {
                $query->whereHas('activeSubscription');
            } elseif ($request->subscription_status === 'inactive') {
                $query->whereDoesntHave('activeSubscription');
            }
        }

        $streamers = $query->orderBy('created_at', 'desc')->paginate(20);

        // Calculate additional stats for each streamer
        $streamers->getCollection()->transform(function ($streamer) {
            $totalRevenue = Payment::where('payee_id', $streamer->id)
                ->where('status', Payment::STATUS_COMPLETED)
                ->sum('amount');

            $streamer->total_revenue = $totalRevenue;
            $streamer->status = $streamer->user->email_verified_at ? 'active' : 'inactive';
            
            return $streamer;
        });

        return response()->json([
            'streamers' => $streamers,
            'message' => 'Streamers retrieved successfully'
        ]);
    }

    /**
     * Get streamer details
     */
    public function getStreamerDetails(Streamer $streamer)
    {
        $streamer->load([
            'user',
            'activeSubscription.subscriptionPlan',
            'subscriptions.subscriptionPlan' => function($query) {
                $query->orderBy('created_at', 'desc');
            },
            'plannedStreams' => function($query) {
                $query->orderBy('scheduled_start', 'desc')->limit(10);
            }
        ]);

        // Get payment history
        $payments = Payment::where('payee_id', $streamer->id)
            ->with('subscription.subscriptionPlan')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Calculate stats
        $totalRevenue = Payment::where('payee_id', $streamer->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');

        $totalStreams = $streamer->plannedStreams()->count();
        $completedStreams = $streamer->plannedStreams()
            ->where('status', PlannedStream::STATUS_COMPLETED)
            ->count();

        return response()->json([
            'streamer' => $streamer,
            'payments' => $payments,
            'stats' => [
                'total_revenue' => $totalRevenue,
                'total_streams' => $totalStreams,
                'completed_streams' => $completedStreams,
                'status' => $streamer->user->email_verified_at ? 'active' : 'inactive'
            ],
            'message' => 'Streamer details retrieved successfully'
        ]);
    }

    /**
     * Toggle streamer status
     */
    public function toggleStreamerStatus(Streamer $streamer)
    {
        // Toggle the associated user's email_verified_at
        $user = $streamer->user;
        $user->email_verified_at = $user->email_verified_at ? null : now();
        $user->save();

        $status = $user->email_verified_at ? 'active' : 'inactive';

        return response()->json([
            'streamer' => $streamer->load('user'),
            'status' => $status,
            'message' => "Streamer {$status} successfully"
        ]);
    }

    /**
     * Update streamer subscription details
     */
    public function updateStreamerSubscription(Request $request, Streamer $streamer, $subscriptionId)
    {
        $subscription = $streamer->subscriptions()->findOrFail($subscriptionId);

        $validated = $request->validate([
            'status' => 'required|in:pending,active,canceled,expired',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $subscription->update([
            'status' => $validated['status'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        $subscription->load('subscriptionPlan');

        return response()->json([
            'subscription' => $subscription,
            'message' => 'Subscription updated successfully'
        ]);
    }

    // ============ SUBSCRIPTION PLAN MANAGEMENT ============

    /**
     * Get all subscription plans
     */
    public function getPlans(Request $request)
    {
        $query = SubscriptionPlan::withCount('streamerSubscriptions');

        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $plans = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'plans' => $plans,
            'message' => 'Plans retrieved successfully'
        ]);
    }

    /**
     * Create subscription plan
     */
    public function createPlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'duration_hours' => 'required|numeric|min:0',
            'views_delivered' => 'integer|min:0',
            'chat_messages_delivered' => 'integer|min:0',
            'features' => 'array',
            'features.*' => 'string',
            'is_active' => 'boolean',
            'is_most_popular' => 'boolean',
        ]);

        // If this plan is set as most popular, remove the flag from others
        if ($validated['is_most_popular'] ?? false) {
            SubscriptionPlan::where('is_most_popular', true)->update(['is_most_popular' => false]);
        }

        $plan = SubscriptionPlan::create($validated);

        return response()->json([
            'plan' => $plan,
            'message' => 'Plan created successfully'
        ], 201);
    }

    /**
     * Update subscription plan
     */
    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'string',
            'price' => 'numeric|min:0',
            'duration_days' => 'integer|min:1',
            'duration_hours' => 'numeric|min:0',
            'views_delivered' => 'integer|min:0',
            'chat_messages_delivered' => 'integer|min:0',
            'features' => 'array',
            'features.*' => 'string',
            'is_active' => 'boolean',
            'is_most_popular' => 'boolean',
        ]);

        // If this plan is set as most popular, remove the flag from others
        if (($validated['is_most_popular'] ?? false) && !$plan->is_most_popular) {
            SubscriptionPlan::where('is_most_popular', true)->update(['is_most_popular' => false]);
        }

        $plan->update($validated);

        return response()->json([
            'plan' => $plan->fresh(),
            'message' => 'Plan updated successfully'
        ]);
    }

    /**
     * Delete subscription plan
     */
    public function deletePlan(SubscriptionPlan $plan)
    {
        // Check if plan has active subscriptions
        $activeSubscriptions = $plan->streamerSubscriptions()
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->count();

        if ($activeSubscriptions > 0) {
            return response()->json([
                'message' => 'Cannot delete plan with active subscriptions'
            ], 422);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan deleted successfully'
        ]);
    }

    /**
     * Toggle plan status
     */
    public function togglePlanStatus(SubscriptionPlan $plan)
    {
        $plan->is_active = !$plan->is_active;
        $plan->save();

        $status = $plan->is_active ? 'active' : 'inactive';

        return response()->json([
            'plan' => $plan,
            'status' => $status,
            'message' => "Plan {$status} successfully"
        ]);
    }

    /**
     * Get active plans for public display
     */
    public function getActivePlans()
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json([
            'plans' => $plans,
            'message' => 'Active plans retrieved successfully'
        ]);
    }

    // ============ PAYMENT MANAGEMENT ============

    /**
     * Get all payments with filtering
     */
    public function getPayments(Request $request)
    {
        $query = Payment::with([
            'payer',
            'payee.user',
            'subscription.subscriptionPlan'
        ]);

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                  ->orWhereHas('payee.user', function($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('payee', function($streamerQuery) use ($search) {
                      $streamerQuery->where('full_name', 'like', "%{$search}%")
                                   ->orWhere('username', 'like', "%{$search}%");
                  });
            });
        }

        // Date range filter
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate(20);

        // Calculate summary stats
        $totalRevenue = Payment::completed()->sum('amount');
        $pendingPayments = Payment::pending()->count();
        $failedPayments = Payment::failed()->count();

        return response()->json([
            'payments' => $payments,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'pending_count' => $pendingPayments,
                'failed_count' => $failedPayments,
            ],
            'message' => 'Payments retrieved successfully'
        ]);
    }

    /**
     * Process refund
     */
    public function refundPayment(Payment $payment)
    {
        if ($payment->status !== Payment::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Only completed payments can be refunded'
            ], 422);
        }

        // In a real implementation, you would integrate with payment processor
        $payment->status = Payment::STATUS_REFUNDED;
        $payment->save();

        return response()->json([
            'payment' => $payment->fresh(),
            'message' => 'Payment refunded successfully'
        ]);
    }

    /**
     * Retry failed payment
     */
    public function retryPayment(Payment $payment)
    {
        if ($payment->status !== Payment::STATUS_FAILED) {
            return response()->json([
                'message' => 'Only failed payments can be retried'
            ], 422);
        }

        // In a real implementation, you would retry the payment with payment processor
        $payment->status = Payment::STATUS_PENDING;
        $payment->save();

        return response()->json([
            'payment' => $payment->fresh(),
            'message' => 'Payment retry initiated successfully'
        ]);
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        $totalUsers = User::count();
        $totalStreamers = Streamer::count();
        $activeStreamers = Streamer::whereHas('user', function($query) {
            $query->whereNotNull('email_verified_at');
        })->count();
        
        $totalRevenue = Payment::completed()->sum('amount');
        $monthlyRevenue = Payment::completed()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
            
        $totalPlans = SubscriptionPlan::count();
        $activePlans = SubscriptionPlan::where('is_active', true)->count();
        
        $pendingPayments = Payment::pending()->count();
        $failedPayments = Payment::failed()->count();

        return response()->json([
            'stats' => [
                'users' => [
                    'total' => $totalUsers,
                    'streamers' => $totalStreamers,
                    'active_streamers' => $activeStreamers,
                ],
                'revenue' => [
                    'total' => $totalRevenue,
                    'monthly' => $monthlyRevenue,
                ],
                'plans' => [
                    'total' => $totalPlans,
                    'active' => $activePlans,
                ],
                'payments' => [
                    'pending' => $pendingPayments,
                    'failed' => $failedPayments,
                ],
            ],
            'message' => 'Dashboard statistics retrieved successfully'
        ]);
    }

    /**
     * Get streamer plan total price
     */
    public function getStreamerPlanPrice($streamerId)
    {
        $streamer = Streamer::with(['activeSubscription.subscriptionPlan'])->where('discord_id', $streamerId)->first();
        
        if (!$streamer) {
            return response()->json(['message' => 'Streamer not found'], 404);
        }

        if (!$streamer->activeSubscription || !$streamer->activeSubscription->subscriptionPlan) {
            return response()->json([
                'streamer_id' => $streamerId,
                'streamer_name' => $streamer->full_name ?? $streamer->username,
                'has_active_plan' => false,
                'plan_price' => null,
                'message' => 'Streamer has no active subscription plan'
            ]);
        }

        $plan = $streamer->activeSubscription->subscriptionPlan;
        
        return response()->json([
            'streamer_id' => $streamerId,
            'streamer_name' => $streamer->full_name ?? $streamer->username,
            'has_active_plan' => true,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'plan_price' => $plan->price,
            'subscription_status' => $streamer->activeSubscription->status,
            'subscription_start_date' => $streamer->activeSubscription->start_date,
            'subscription_end_date' => $streamer->activeSubscription->end_date,
            'message' => 'Plan price retrieved successfully'
        ]);
    }
}
