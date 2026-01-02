<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentProductRequest;
use App\Models\AuditLog;
use App\Models\PaymentProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * T036, T041, T042: Admin Payment Product Controller (US2)
 */
class PaymentProductController extends Controller
{
    /**
     * Display a listing of payment products.
     */
    public function index(Request $request): View
    {
        $query = PaymentProduct::query();

        // Filter by status if provided
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        return view('admin.payment-products.index', [
            'products' => $products,
            'currentStatus' => $request->status ?? 'all',
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        return view('admin.payment-products.create');
    }

    /**
     * Store a newly created product.
     */
    public function store(PaymentProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Set default currency if not provided
        $validated['currency'] = $validated['currency'] ?? 'TWD';

        $product = PaymentProduct::create($validated);

        // Audit logging
        $this->logProductAction('payment_product_created', $product, [
            'product_id' => $product->id,
            'name' => $product->name,
            'portaly_product_id' => $product->portaly_product_id,
            'price' => $product->price,
        ]);

        Log::info('Payment product created', [
            'admin_id' => auth()->id(),
            'product_id' => $product->id,
            'name' => $product->name,
        ]);

        return redirect()
            ->route('admin.payment-products.index')
            ->with('success', "商品「{$product->name}」已建立");
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(PaymentProduct $paymentProduct): View
    {
        return view('admin.payment-products.edit', [
            'product' => $paymentProduct,
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(PaymentProductRequest $request, PaymentProduct $paymentProduct): RedirectResponse
    {
        $validated = $request->validated();

        // Track changes for audit log
        $changes = $this->getChanges($paymentProduct, $validated);

        $paymentProduct->update($validated);

        // Audit logging
        $this->logProductAction('payment_product_updated', $paymentProduct, [
            'product_id' => $paymentProduct->id,
            'changes' => $changes,
        ]);

        Log::info('Payment product updated', [
            'admin_id' => auth()->id(),
            'product_id' => $paymentProduct->id,
            'changes' => $changes,
        ]);

        return redirect()
            ->route('admin.payment-products.index')
            ->with('success', "商品「{$paymentProduct->name}」已更新");
    }

    /**
     * Remove the specified product (soft delete).
     */
    public function destroy(PaymentProduct $paymentProduct): RedirectResponse
    {
        $productName = $paymentProduct->name;
        $productId = $paymentProduct->id;

        $paymentProduct->delete();

        // Audit logging
        $this->logProductAction('payment_product_deleted', $paymentProduct, [
            'product_id' => $productId,
            'name' => $productName,
        ]);

        Log::info('Payment product deleted', [
            'admin_id' => auth()->id(),
            'product_id' => $productId,
            'name' => $productName,
        ]);

        return redirect()
            ->route('admin.payment-products.index')
            ->with('success', "商品「{$productName}」已刪除");
    }

    /**
     * Toggle the product status between active and inactive.
     * T041: Implement toggleStatus action
     */
    public function toggleStatus(PaymentProduct $paymentProduct): RedirectResponse
    {
        $oldStatus = $paymentProduct->status;
        $newStatus = $oldStatus === 'active' ? 'inactive' : 'active';

        $paymentProduct->update(['status' => $newStatus]);

        // Audit logging
        $this->logProductAction('payment_product_status_changed', $paymentProduct, [
            'product_id' => $paymentProduct->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        Log::info('Payment product status toggled', [
            'admin_id' => auth()->id(),
            'product_id' => $paymentProduct->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        $statusLabel = $newStatus === 'active' ? '啟用' : '停用';

        return redirect()
            ->back()
            ->with('success', "商品「{$paymentProduct->name}」已{$statusLabel}");
    }

    /**
     * Log product actions to audit_logs.
     * T042: Add audit logging for product CRUD operations
     */
    protected function logProductAction(string $actionType, PaymentProduct $product, array $details): void
    {
        $productName = $details['name'] ?? $product->name;

        $descriptions = [
            'payment_product_created' => "建立付款商品「{$productName}」",
            'payment_product_updated' => "更新付款商品「{$productName}」",
            'payment_product_deleted' => "刪除付款商品「{$productName}」",
            'payment_product_status_changed' => "變更付款商品「{$productName}」狀態",
        ];

        AuditLog::log(
            actionType: $actionType,
            description: $descriptions[$actionType] ?? $actionType,
            adminId: auth()->id(),
            resourceType: 'payment_product',
            resourceId: $product->id,
            changes: $details
        );
    }

    /**
     * Get changed fields for audit logging.
     */
    protected function getChanges(PaymentProduct $product, array $newValues): array
    {
        $changes = [];
        $trackFields = ['name', 'portaly_product_id', 'portaly_url', 'price', 'duration_days', 'action_type', 'status'];

        foreach ($trackFields as $field) {
            if (isset($newValues[$field]) && $product->{$field} != $newValues[$field]) {
                $changes[$field] = [
                    'old' => $product->{$field},
                    'new' => $newValues[$field],
                ];
            }
        }

        return $changes;
    }
}
