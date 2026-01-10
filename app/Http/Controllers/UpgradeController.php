<?php

namespace App\Http\Controllers;

use App\Models\PaymentProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UpgradeController extends Controller
{
    /**
     * Display the upgrade page with active payment products.
     */
    public function index(): View
    {
        $products = PaymentProduct::active()
            ->orderBy('price', 'asc')
            ->get();

        return view('upgrade.index', compact('products'));
    }

    /**
     * Handle return from Portaly after payment.
     * Refreshes user session to reflect updated premium status.
     *
     * FR-052: Provide success return page at /upgrade/success
     * FR-053: Refresh user model from database
     * FR-054: Update authentication session with refreshed user data
     * FR-055: Redirect to upgrade page with success flash message
     */
    public function success(Request $request): RedirectResponse
    {
        // FR-053: Refresh user data from database to get updated premium_expires_at
        if ($user = $request->user()) {
            $user->refresh();
            // FR-054: Re-authenticate to update session with fresh user data
            Auth::setUser($user);
        }

        // FR-055: Redirect with success message
        return redirect()->route('upgrade')
            ->with('success', '付款成功！您的會員權限已更新。');
    }
}
