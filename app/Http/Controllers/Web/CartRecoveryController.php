<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\RedirectResponse;

class CartRecoveryController extends Controller
{
    public function recover(Cart $cart): RedirectResponse
    {
        if ($cart->status === 'abandoned') {
            Cart::query()
                ->where('user_id', $cart->user_id)
                ->where('id', '!=', $cart->id)
                ->where('status', 'active')
                ->update(['status' => 'archived']);

            $cart->update([
                'status' => 'active',
                'recovered_at' => now(),
                'last_activity_at' => now(),
            ]);
        }

        return redirect()->away(rtrim(config('services.frontend.url'), '/') . '/carrito?recovered=1');
    }
}
