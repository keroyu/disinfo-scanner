<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentSettingsRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * T046: Admin Payment Settings Controller (US3)
 * Manages global payment settings including Portaly webhook secret
 */
class PaymentSettingsController extends Controller
{
    /**
     * Setting key for Portaly webhook secret.
     */
    private const WEBHOOK_SECRET_KEY = 'portaly_webhook_secret';

    /**
     * Display the payment settings page.
     */
    public function index(): View
    {
        $hasSecret = $this->hasWebhookSecret();

        return view('admin.payment-settings.index', [
            'hasSecret' => $hasSecret,
            'webhookUrl' => config('app.url') . '/api/webhooks/portaly',
        ]);
    }

    /**
     * Update the payment settings.
     * T049: Implement encrypted storage using Crypt::encryptString()
     * T050: Add audit logging for settings changes
     */
    public function update(PaymentSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Encrypt the secret before storing
        $encryptedSecret = Crypt::encryptString($validated['portaly_webhook_secret']);
        Setting::setValue(self::WEBHOOK_SECRET_KEY, $encryptedSecret);

        // Audit logging (without the actual secret value)
        $this->logSettingsUpdate();

        Log::info('Payment settings updated', [
            'admin_id' => auth()->id(),
            'setting' => 'portaly_webhook_secret',
            // Do NOT log the actual secret value
        ]);

        return redirect()
            ->route('admin.payment-settings.index')
            ->with('success', 'Webhook 金鑰已更新');
    }

    /**
     * Check if webhook secret is configured.
     */
    private function hasWebhookSecret(): bool
    {
        $value = Setting::getValue(self::WEBHOOK_SECRET_KEY);
        return $value !== null && $value !== '';
    }

    /**
     * Log settings update to audit_logs.
     * T050: Add audit logging for settings changes (without secret value)
     */
    private function logSettingsUpdate(): void
    {
        AuditLog::log(
            actionType: 'payment_settings_updated',
            description: '更新付款設定（Webhook 金鑰）',
            userId: null,
            adminId: auth()->id(),
            resourceType: 'payment_settings',
            resourceId: null,
            changes: [
                'updated_keys' => ['portaly_webhook_secret'],
                // Intentionally NOT including the actual secret value
            ]
        );
    }
}
