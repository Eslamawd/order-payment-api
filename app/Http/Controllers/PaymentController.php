<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Services\Payment\PaymentService;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PaymentCollection;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->middleware('auth:api');
        $this->paymentService = $paymentService;
    }

    public function index(Request $request)
    {
        $query = Payment::with('order')
            ->whereHas('order', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by order
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        $payments = $query->paginate($request->get('per_page', 15));

        return new PaymentCollection($payments);
    }

    public function show($id)
    {
        $payment = Payment::with('order')
            ->whereHas('order', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => new PaymentResource($payment)
        ]);
    }

    public function process(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'gateway' => 'required|string',
            'payment_method' => 'required|string',
            'payment_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::where('user_id', auth()->id())
            ->findOrFail($request->order_id);

        try {
            $payment = $this->paymentService->processOrderPayment(
                $order,
                $request->gateway,
                array_merge(
                    $request->payment_data,
                    ['payment_method' => $request->payment_method]
                )
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'data' => new PaymentResource($payment->load('order'))
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => method_exists($e, 'getErrors') ? $e->getErrors() : []
            ], 400);
        }
    }

    public function availableGateways()
    {
        $gateways = $this->paymentService->getAvailableGateways();

        return response()->json([
            'status' => 'success',
            'data' => $gateways
        ]);
    }

    public function refund(Request $request, $id)
    {
        $payment = Payment::with('order')
            ->whereHas('order', function ($q) {
                $q->where('user_id', auth()->id());
            })
            ->findOrFail($id);

        if ($payment->status !== 'successful') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only successful payments can be refunded'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:0|max:' . $payment->amount,
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $gateway = $this->paymentService
                ->make($payment->gateway_name);

            $refundAmount = $request->amount ?? $payment->amount;
            $result = $gateway->refund($payment->gateway_reference, $refundAmount);

            if ($result['success']) {
                $payment->update([
                    'status' => 'refunded',
                    'gateway_response' => array_merge(
                        (array) $payment->gateway_response,
                        ['refund' => $result]
                    )
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment refunded successfully',
                    'data' => [
                        'refund' => $result,
                        'payment' => new PaymentResource($payment)
                    ]
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['error'] ?? 'Refund failed'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Refund failed: ' . $e->getMessage()
            ], 400);
        }
    }
}