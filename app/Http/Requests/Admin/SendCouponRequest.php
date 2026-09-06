<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', Rule::in(['email', 'whatsapp'])],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'emails' => ['nullable', 'array'],
            'emails.*' => ['email', 'max:255'],
            'whatsapp_numbers' => ['nullable', 'array'],
            'whatsapp_numbers.*' => ['string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'channels.required' => 'Debes seleccionar al menos un canal de envío.',
            'channels.array' => 'Los canales deben enviarse como una lista.',
            'channels.min' => 'Debes seleccionar al menos un canal de envío.',
            'channels.*.in' => 'El canal seleccionado no es válido.',
            'user_ids.array' => 'Los usuarios deben enviarse como una lista.',
            'user_ids.*.exists' => 'Uno de los usuarios seleccionados no existe.',
            'emails.array' => 'Los correos deben enviarse como una lista.',
            'emails.*.email' => 'Uno de los correos no tiene un formato válido.',
            'emails.*.max' => 'Uno de los correos supera el máximo de 255 caracteres.',
            'whatsapp_numbers.array' => 'Los números de WhatsApp deben enviarse como una lista.',
            'whatsapp_numbers.*.max' => 'Uno de los números de WhatsApp supera el máximo de 30 caracteres.',
            'subject.max' => 'El asunto no puede superar 255 caracteres.',
            'message.max' => 'El mensaje no puede superar 1000 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'channels' => 'canales',
            'user_ids' => 'usuarios',
            'emails' => 'correos',
            'whatsapp_numbers' => 'números de WhatsApp',
            'subject' => 'asunto',
            'message' => 'mensaje',
        ];
    }
}
