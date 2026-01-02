<?php

namespace App\Http\Controllers;

use App\Models\PaymentProduct;
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
}
