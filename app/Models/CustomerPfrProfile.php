<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomerPfrProfile extends Model
{
    public const PAYMENT_EFECTIVO = 'efectivo';
    public const PAYMENT_TRANSFERENCIA = 'transferencia_electronica';
    public const PAYMENT_CHEQUE = 'cheque';
    public const PAYMENT_EFECTIVO_TRANSFERENCIA = 'efectivo_transferencia';
    public const PAYMENT_OTRO = 'otro';

    protected $fillable = [
        'user_id',
        'commercial_name',
        'purchasing_contact_name',
        'quote_email',
        'business_phone',
        'secondary_contact_name',
        'secondary_phone',
        'business_activity',
        'payment_method',
        'price_list',
        'requires_invoice',
        'fiscal_name',
        'rfc',
        'fiscal_street',
        'fiscal_external_number',
        'fiscal_internal_number',
        'fiscal_zip_code',
        'fiscal_neighborhood',
        'fiscal_city',
        'fiscal_state',
        'xml_email',
        'cfdi_use',
        'tax_certificate_disk',
        'tax_certificate_path',
        'tax_certificate_original_name',
        'tax_certificate_mime',
        'tax_certificate_size',
        'delivery_same_as_fiscal',
        'delivery_address',
        'delivery_schedule',
        'delivery_observations',
        'distintivo_h',
    ];

    protected $casts = [
        'requires_invoice' => 'boolean',
        'delivery_same_as_fiscal' => 'boolean',
        'tax_certificate_size' => 'integer',
    ];

    protected $appends = [
        'tax_certificate_url',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function completionSummary(): array
    {
        $sections = [
            'general' => $this->sectionCompletion([
                'commercial_name' => 'Nombre comercial',
                'purchasing_contact_name' => 'Contacto de compras',
                'business_phone' => 'Telefono del comercio',
                'business_activity' => 'Giro comercial',
                'payment_method' => 'Metodo de pago',
                'price_list' => 'Lista',
            ]),
            'billing' => $this->billingCompletion(),
            'delivery' => $this->deliveryCompletion(),
        ];

        $totalFields = collect($sections)->sum('total_fields');
        $completedFields = collect($sections)->sum('completed_fields');

        return [
            'percentage' => $totalFields > 0
                ? (int) round(($completedFields / $totalFields) * 100)
                : 0,
            'completed_fields' => $completedFields,
            'total_fields' => $totalFields,
            'missing_fields' => collect($sections)
                ->flatMap(fn (array $section) => $section['missing_fields'])
                ->values()
                ->all(),
            'sections' => $sections,
        ];
    }

    private function billingCompletion(): array
    {
        if ($this->requires_invoice === false) {
            return [
                'percentage' => 100,
                'completed_fields' => 1,
                'total_fields' => 1,
                'missing_fields' => [],
                'applies' => false,
            ];
        }

        $fields = [
            'requires_invoice' => 'Facturacion',
        ];

        if ($this->requires_invoice === true) {
            $fields = array_merge($fields, [
                'fiscal_name' => 'Nombre fiscal',
                'rfc' => 'RFC',
                'fiscal_street' => 'Calle fiscal',
                'fiscal_external_number' => 'Numero exterior fiscal',
                'fiscal_zip_code' => 'Codigo postal fiscal',
                'fiscal_neighborhood' => 'Colonia fiscal',
                'fiscal_city' => 'Ciudad fiscal',
                'fiscal_state' => 'Estado fiscal',
                'xml_email' => 'Correo XML',
                'cfdi_use' => 'Uso de CFDI',
                'tax_certificate_path' => 'Constancia de situacion fiscal',
            ]);
        }

        return $this->sectionCompletion($fields, $this->requires_invoice === true);
    }

    private function deliveryCompletion(): array
    {
        $fields = [
            'delivery_same_as_fiscal' => 'Entrega en direccion fiscal',
        ];

        if ($this->delivery_same_as_fiscal === false) {
            $fields['delivery_address'] = 'Direccion de entrega';
        }

        return $this->sectionCompletion($fields);
    }

    private function sectionCompletion(array $fields, bool $applies = true): array
    {
        $missingFields = [];
        $completedFields = 0;

        foreach ($fields as $field => $label) {
            if ($this->fieldHasValue($field)) {
                $completedFields++;
                continue;
            }

            $missingFields[] = [
                'field' => $field,
                'label' => $label,
            ];
        }

        $totalFields = count($fields);

        return [
            'percentage' => $totalFields > 0
                ? (int) round(($completedFields / $totalFields) * 100)
                : 0,
            'completed_fields' => $completedFields,
            'total_fields' => $totalFields,
            'missing_fields' => $missingFields,
            'applies' => $applies,
        ];
    }

    private function fieldHasValue(string $field): bool
    {
        $value = $this->{$field};

        if (is_bool($value)) {
            return true;
        }

        return !blank($value);
    }

    public function getTaxCertificateUrlAttribute(): ?string
    {
        if (!$this->tax_certificate_disk || !$this->tax_certificate_path) {
            return null;
        }

        return Storage::disk($this->tax_certificate_disk)->url($this->tax_certificate_path);
    }
}
