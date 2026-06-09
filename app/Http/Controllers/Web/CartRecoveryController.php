<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CartRecoveryController extends Controller
{
    public function recover(Cart $cart): RedirectResponse
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if ((int) $cart->user_id !== (int) Auth::id()) {
            abort(403);
        }

        if ($cart->status === 'abandoned') {
            $cart->update([
                'status' => 'active',
                'recovered_at' => now(),
            ]);
        }

        return redirect()->route('cart.index');
    }
}