<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Streamer;
use App\Models\StreamerSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Get all payments with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = Payment::with(['streamer.user', 'subscription.subscription_plan'])
                        ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->whereHas('streamer', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('email', 'like', "%{$search}%");
                  });
            })->orWhere('transaction_id', 'like', "%{$search}%");
        }

        if ($request->has('start_date') && $request->start_date !== '') {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date !== '') {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $payments = $query->paginate(15);

        return response()->json([
            'payments' => $payments,
            'message' => 'Payments retrieved successfully'
        ]);
    }

    /**
     * Create a new payment
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'streamer_id' => 'required|exists:streamers,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'status' => 'required|in:pending,completed,failed,refunded',
            'transaction_id' => 'required|string|max:255|unique:payments,transaction_id',
            'subscription_id' => 'nullable|exists:streamer_subscriptions,id',
            'description' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $payment = Payment::create([
            'payee_id' => $request->streamer_id,
            'subscription_id' => $request->subscription_id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'status' => $request->status,
            'transaction_id' => $request->transaction_id,
            'description' => $request->description
        ]);

        $payment->load(['streamer.user', 'subscription.subscription_plan']);

        // Check if payment should activate subscription
        if ($payment->status === Payment::STATUS_COMPLETED && $payment->subscription) {
            $this->checkAndActivateSubscription($payment);
        }

        return response()->json([
            'payment' => $payment,
            'message' => 'Payment created successfully'
        ], 201);
    }

    /**
     * Get a specific payment
     */
    public function show(Payment $payment)
    {
        $payment->load(['streamer.user', 'subscription.subscription_plan']);

        return response()->json([
            'payment' => $payment,
            'message' => 'Payment retrieved successfully'
        ]);
    }

    /**
     * Update a payment
     */
    public function update(Request $request, Payment $payment)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'numeric|min:0',
            'payment_method' => 'string|max:50',
            'status' => 'in:pending,completed,failed,refunded',
            'transaction_id' => 'string|max:255|unique:payments,transaction_id,' . $payment->id,
            'description' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $payment->update($request->only([
            'amount', 'payment_method', 'status', 'transaction_id', 'description'
        ]));

        $payment->load(['streamer.user', 'subscription.subscription_plan']);

        // Check if payment status changed to completed and should activate subscription
        if ($payment->status === Payment::STATUS_COMPLETED && $payment->subscription) {
            $this->checkAndActivateSubscription($payment);
        }

        return response()->json([
            'payment' => $payment,
            'message' => 'Payment updated successfully'
        ]);
    }

    /**
     * Delete a payment
     */
    public function destroy(Payment $payment)
    {
        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully'
        ]);
    }

    /**
     * Process a refund for a payment
     */
    public function refund(Payment $payment)
    {
        if ($payment->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed payments can be refunded'
            ], 400);
        }

        $payment->update(['status' => 'refunded']);
        $payment->load(['streamer.user', 'subscription.subscription_plan']);

        return response()->json([
            'payment' => $payment,
            'message' => 'Payment refunded successfully'
        ]);
    }

    /**
     * Retry a failed payment
     */
    public function retry(Payment $payment)
    {
        if ($payment->status !== 'failed') {
            return response()->json([
                'message' => 'Only failed payments can be retried'
            ], 400);
        }

        $payment->update(['status' => 'pending']);
        $payment->load(['streamer.user', 'subscription.subscription_plan']);

        return response()->json([
            'payment' => $payment,
            'message' => 'Payment retry initiated'
        ]);
    }

    /**
     * Get payments for a specific streamer
     */
    public function getStreamerPayments(Request $request, $streamerId)
    {
        $streamer = Streamer::findOrFail($streamerId);
        
        $payments = Payment::where('payee_id', $streamerId)
                          ->with(['subscription.subscription_plan'])
                          ->orderBy('created_at', 'desc')
                          ->get();

        return response()->json([
            'payments' => $payments,
            'streamer' => $streamer,
            'message' => 'Streamer payments retrieved successfully'
        ]);
    }

    /**
     * Get payment statistics
     */
    public function getStats(Request $request)
    {
        $totalPayments = Payment::count();
        $totalRevenue = Payment::where('status', 'completed')->sum('amount');
        $pendingPayments = Payment::where('status', 'pending')->count();
        $failedPayments = Payment::where('status', 'failed')->count();
        $refundedAmount = Payment::where('status', 'refunded')->sum('amount');

        // Monthly statistics
        $monthlyStats = Payment::selectRaw('
            MONTH(created_at) as month,
            YEAR(created_at) as year,
            COUNT(*) as count,
            SUM(CASE WHEN status = "completed" THEN amount ELSE 0 END) as revenue
        ')
        ->whereYear('created_at', date('Y'))
        ->groupBy('year', 'month')
        ->orderBy('year', 'desc')
        ->orderBy('month', 'desc')
        ->get();

        return response()->json([
            'stats' => [
                'total_payments' => $totalPayments,
                'total_revenue' => $totalRevenue,
                'pending_payments' => $pendingPayments,
                'failed_payments' => $failedPayments,
                'refunded_amount' => $refundedAmount,
                'monthly_stats' => $monthlyStats
            ],
            'message' => 'Payment statistics retrieved successfully'
        ]);
    }

    /**
     * Check if payment should activate a subscription
     */
    private function checkAndActivateSubscription(Payment $payment)
    {
        $subscription = $payment->subscription;
        $subscriptionPlan = $subscription->subscriptionPlan;

        if (!$subscription || !$subscriptionPlan || $subscription->status === 'active') {
            return;
        }

        // Calculate total completed payments for this subscription
        $totalPaid = Payment::where('subscription_id', $subscription->id)
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');

        // Check if total payments meet or exceed the plan price
        if ($totalPaid >= $subscriptionPlan->price) {
            // Activate the subscription
            $subscription->update([
                'status' => 'active',
                'start_date' => now(),
                'end_date' => now()->addDays((int) $subscriptionPlan->duration_days),
            ]);

            // Log the activation
            \Log::info("Subscription activated for streamer {$subscription->streamer_id} after payment {$payment->id}");
        }
    }
}
