<?php

namespace App\Http\Requests;

use App\Enums\WafMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('node_id') === '') {
            $this->merge(['node_id' => null]);
        }

        if ($this->input('waf_mode') === '') {
            $this->merge(['waf_mode' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255', 'unique:sites,hostname'],
            'origin_url' => ['required', 'url:http,https', 'max:2048'],
            'waf_mode' => ['nullable', Rule::enum(WafMode::class)],
            'node_id' => ['nullable', 'integer', 'exists:nodes,id'],
        ];
    }
}
