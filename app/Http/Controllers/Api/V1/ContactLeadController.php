<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactLeadAdminMail;
use App\Mail\ContactLeadCustomerMail;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactLeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        $contactEmail = SiteSetting::current()->forms_recipient_email ?: 'alekzleon03.aa@gmail.com';

        try {
            Mail::to($contactEmail)->send(new ContactLeadAdminMail($validated));
            Mail::to($validated['email'])->send(new ContactLeadCustomerMail($validated));
        } catch (\Throwable $exception) {
            Log::error('Contact lead email failed.', [
                'email' => $validated['email'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible enviar la información. Intenta de nuevo más tarde.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Información enviada correctamente.',
        ]);
    }
}
