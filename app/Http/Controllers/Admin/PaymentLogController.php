<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentLog;
use App\Models\PaymentProduct;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * T054: Admin Payment Log Controller (US4)
 * Provides viewing and filtering of payment transaction history
 */
class PaymentLogController extends Controller
{
    /**
     * Display a listing of payment logs with filtering and pagination.
     * T057: Implement pagination and filtering
     * T059: Add timezone conversion for display
     */
    public function index(Request $request): View
    {
        $query = PaymentLog::with(['product', 'user'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->status($request->status);
        }

        if ($request->filled('email')) {
            $query->email($request->email);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->dateRange($request->date_from, $request->date_to);
        }

        // Pagination (default 20 per page)
        $perPage = min($request->input('per_page', 20), 100);
        $logs = $query->paginate($perPage)->withQueryString();

        // Get all products for filter dropdown
        $products = PaymentProduct::withTrashed()->orderBy('name')->get();

        // Get all statuses for filter dropdown
        $statuses = [
            PaymentLog::STATUS_SUCCESS => '成功',
            PaymentLog::STATUS_USER_NOT_FOUND => '用戶未找到',
            PaymentLog::STATUS_PRODUCT_NOT_FOUND => '商品未找到',
            PaymentLog::STATUS_PRODUCT_INACTIVE => '商品已停用',
            PaymentLog::STATUS_SIGNATURE_INVALID => '簽名無效',
            PaymentLog::STATUS_DUPLICATE => '重複訂單',
            PaymentLog::STATUS_REFUND => '退款',
            PaymentLog::STATUS_SETTINGS_NOT_CONFIGURED => '設定未配置',
        ];

        return view('admin.payment-logs.index', [
            'logs' => $logs,
            'products' => $products,
            'statuses' => $statuses,
            'filters' => [
                'status' => $request->status,
                'email' => $request->email,
                'product_id' => $request->product_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ],
        ]);
    }

    /**
     * Display the specified payment log details.
     * T058: Implement log detail modal/view
     */
    public function show(PaymentLog $paymentLog): View
    {
        $paymentLog->load(['product', 'user']);

        return view('admin.payment-logs.show', [
            'log' => $paymentLog,
        ]);
    }
}
