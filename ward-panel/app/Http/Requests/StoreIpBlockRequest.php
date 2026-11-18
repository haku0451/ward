<?php

namespace App\Http\Requests;

use App\Models\IpProtection;
use App\Rules\Cidr;
use App\Support\Cidr as CidrParser;
use Illuminate\Foundation\Http\FormRequest;

class StoreIpBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['node_id', 'site_id', 'expires_at'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cidr' => ['required', 'string', 'max:49', new Cidr],
            'reason' => ['nullable', 'string', 'max:255'],
            'node_id' => ['nullable', 'integer', 'exists:nodes,id'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $cidr = CidrParser::normalize((string) $this->input('cidr'));

                foreach (IpProtection::query()->cursor() as $protection) {
                    if (CidrParser::overlaps($cidr, $protection->cidr)) {
                        $validator->errors()->add('cidr', 'This address overlaps a protected management range.');

                        return;
                    }
                }
            },
        ];
    }
}
