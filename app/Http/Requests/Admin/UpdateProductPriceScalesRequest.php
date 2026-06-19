<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPriceScalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'scales' => ['required', 'array', 'min:1'],
            'scales.*.from_quantity' => ['required', 'integer', 'min:1'],
            'scales.*.to_quantity' => ['nullable', 'integer', 'min:1'],
            'scales.*.discount_percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'scales.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateScales($validator);
            $this->validateRemovalOrder($validator);
        });
    }

    public function normalizedScales(): array
    {
        return collect($this->input('scales', []))
            ->values()
            ->map(fn ($scale) => [
                'from_quantity' => (int) ($scale['from_quantity'] ?? 0),
                'to_quantity' => isset($scale['to_quantity']) && $scale['to_quantity'] !== ''
                    ? (int) $scale['to_quantity']
                    : null,
                'discount_percentage' => round((float) ($scale['discount_percentage'] ?? 0), 2),
                'is_active' => filter_var($scale['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            ])
            ->sortBy('from_quantity')
            ->values()
            ->all();
    }

    protected function validateScales($validator): void
    {
        $scales = collect($this->normalizedScales());

        foreach ($scales as $index => $scale) {
            if ($scale['to_quantity'] !== null && $scale['to_quantity'] < $scale['from_quantity']) {
                $validator->errors()->add("scales.{$index}.to_quantity", 'La cantidad final debe ser mayor o igual a la cantidad inicial.');
            }
        }

        $activeScales = $scales
            ->filter(fn ($scale) => $scale['is_active'])
            ->values();

        for ($index = 0; $index < $activeScales->count(); $index++) {
            $current = $activeScales[$index];
            $next = $activeScales[$index + 1] ?? null;

            if ($current['to_quantity'] === null && $next) {
                $validator->errors()->add('scales', 'Solo la última escala activa puede quedar abierta hasta infinito.');
            }

            if (!$next) {
                continue;
            }

            if ($current['to_quantity'] === null) {
                continue;
            }

            $expectedNextFrom = $current['to_quantity'] + 1;

            if ($next['from_quantity'] !== $expectedNextFrom) {
                $validator->errors()->add('scales', "Las escalas activas deben ser consecutivas. Después de {$current['to_quantity']} debe iniciar {$expectedNextFrom}.");
            }
        }
    }

    protected function validateRemovalOrder($validator): void
    {
        $promotion = $this->route('product')?->promotions()
            ->where('type', 'price_scale_percentage')
            ->first();

        if (!$promotion) {
            return;
        }

        $oldActiveStarts = collect(data_get($promotion->config, 'scales', []))
            ->filter(fn ($scale) => filter_var($scale['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true)
            ->pluck('from_quantity')
            ->map(fn ($value) => (int) $value)
            ->sort()
            ->values();

        $newActiveStarts = collect($this->normalizedScales())
            ->filter(fn ($scale) => $scale['is_active'])
            ->pluck('from_quantity')
            ->sort()
            ->values();

        foreach ($oldActiveStarts as $oldStart) {
            if ($newActiveStarts->contains($oldStart)) {
                continue;
            }

            $hasOldAboveStillActive = $oldActiveStarts
                ->filter(fn ($candidate) => $candidate > $oldStart)
                ->contains(fn ($candidate) => $newActiveStarts->contains($candidate));

            if ($hasOldAboveStillActive) {
                $validator->errors()->add('scales', 'No puedes eliminar o desactivar una escala menor si todavía existe una escala superior activa. Elimina desde la última escala hacia atrás.');
                return;
            }
        }
    }
}
