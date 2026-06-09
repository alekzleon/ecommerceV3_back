<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => 'Dashboard admin',
        ]);
    }
}
