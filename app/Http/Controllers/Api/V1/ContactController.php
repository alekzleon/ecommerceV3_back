<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\ContactAdminMail;
use App\Mail\ContactCustomerMail;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'message.required' => 'El mensaje es obligatorio.',
        ]);

        $contactEmail = SiteSetting::current()->forms_recipient_email ?: 'alekzleon03.aa@gmail.com';

        try {
            Mail::to($contactEmail)->send(new ContactAdminMail($validated));
            Mail::to($validated['email'])->send(new ContactCustomerMail($validated));
        } catch (\Throwable $exception) {
            Log::error('Contact form email failed.', [
                'email' => $validated['email'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible enviar el mensaje. Intenta de nuevo más tarde.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Mensaje enviado correctamente.',
        ]);
    }
}
