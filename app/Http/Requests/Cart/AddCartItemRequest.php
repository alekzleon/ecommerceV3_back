<?php

namespace App\Http\Requests\Cart;

use App\Models\Product;
use App\Models\VariantAttributeValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator as ValidationContract;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Validator;


class AddCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'attribute_value_ids' => ['sometimes', 'array'],
            'attribute_value_ids.*' => ['integer', 'exists:variant_attribute_values,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('product_id')) {
                return;
            }

            $product = Product::query()
                ->with(['activeVariantAttributes.activeValues'])
                ->find($this->integer('product_id'));

            if (! $product) {
                return;
            }

            $requiredAttributeIds = $product->activeVariantAttributes
                ->filter(fn ($attribute) => $attribute->activeValues->isNotEmpty())
                ->pluck('id')
                ->values();

            $ids = collect($this->input('attribute_value_ids', []))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($requiredAttributeIds->isNotEmpty() && $ids->isEmpty()) {
                $validator->errors()->add(
                    'attribute_value_ids',
                    'Selecciona los atributos del producto antes de agregarlo al carrito.'
                );

                return;
            }

            if ($ids->isEmpty()) {
                return;
            }

            $values = VariantAttributeValue::query()
                ->with('attribute')
                ->whereIn('id', $ids)
                ->where('is_active', true)
                ->whereHas('attribute', fn ($query) => $query
                    ->where('product_id', $product->id)
                    ->where('is_active', true))
                ->get();

            if ($values->count() !== $ids->count()) {
                $validator->errors()->add(
                    'attribute_value_ids',
                    'Todos los atributos seleccionados deben pertenecer al producto.'
                );

                return;
            }

            $selectedAttributeIds = $values->pluck('variant_attribute_id');

            if ($selectedAttributeIds->count() !== $selectedAttributeIds->unique()->count()) {
                $validator->errors()->add(
                    'attribute_value_ids',
                    'Selecciona solo un valor por atributo.'
                );

                return;
            }

            $missingAttributeIds = $requiredAttributeIds->diff($selectedAttributeIds);

            if ($missingAttributeIds->isNotEmpty()) {
                $missingNames = $product->activeVariantAttributes
                    ->whereIn('id', $missingAttributeIds)
                    ->pluck('name')
                    ->implode(', ');

                $validator->errors()->add(
                    'attribute_value_ids',
                    "Selecciona un valor para: {$missingNames}."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Debes enviar el producto.',
            'product_id.integer' => 'El identificador del producto no es válido.',
            'product_id.exists' => 'El producto no existe.',
            'quantity.required' => 'Debes indicar la cantidad.',
            'quantity.numeric' => 'La cantidad debe ser un número.',
            'quantity.min' => 'La cantidad mínima es 0.1',
            'attribute_value_ids.array' => 'Los atributos seleccionados deben enviarse como arreglo.',
            'attribute_value_ids.*.integer' => 'El valor de atributo seleccionado no es válido.',
            'attribute_value_ids.*.exists' => 'El valor de atributo seleccionado no existe.',
        ];
    }

    protected function failedValidation(ValidationContract $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
