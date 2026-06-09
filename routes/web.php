<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\CartRecoveryController;
use Illuminate\Support\Facades\Http;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/cart/recover/{cart}', [CartRecoveryController::class, 'recover'])
    ->middleware('signed')
    ->name('cart.recover');
Route::get('/test-ultramsg', function () {

    $baseUrl = config('services.ultramsg.base_url');
    $instance = config('services.ultramsg.instance_id');
    $token = config('services.ultramsg.token');

    $url = rtrim($baseUrl, '/') . '/' . $instance . '/messages/chat';

    return [
        'base_url' => $baseUrl,
        'instance' => $instance,
        'url_final' => $url,
        'token' => $token,
    ];
});

Route::get('/test-ultramsg-send', function () {

    $url = rtrim(config('services.ultramsg.base_url'), '/') 
        . '/' . config('services.ultramsg.instance_id') 
        . '/messages/chat';

    $response = Http::asForm()->post($url, [
        'token' => config('services.ultramsg.token'),
        'to' => '+523332244005',
        'body' => 'Mensaje de prueba desde Laravel 🚀',
    ]);

    return [
        'url' => $url,
        'status' => $response->status(),
        'response' => $response->body(),
    ];
});
