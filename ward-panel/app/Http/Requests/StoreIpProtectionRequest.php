<?php

namespace App\Http\Requests;

use App\Rules\Cidr;
use Illuminate\Foundation\Http\FormRequest;

class StoreIpProtectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cidr' => ['required', 'string', 'max:49', 'unique:ip_protections,cidr', new Cidr],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
