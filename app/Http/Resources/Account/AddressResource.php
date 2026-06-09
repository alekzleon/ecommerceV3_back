<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dir_cli_id' => $this->dir_cli_id,
            'cliente_id' => $this->cliente_id,
            'alias' => $this->alias,
            'street' => $this->street,
            'address_line_2' => $this->address_line_2,
            'zip_code' => $this->zip_code,
            'neighborhood' => $this->neighborhood,
            'state' => $this->state,
            'delivery_note' => $this->references,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'is_default' => (bool) $this->is_default,
            'es_dir_ppal' => $this->es_dir_ppal,
            'usar_para_envios' => $this->usar_para_envios,
            'usar_para_facturar' => $this->usar_para_facturar,
            'full_address' => $this->full_address,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
