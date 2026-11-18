<?php

namespace App\Http\Requests;

use App\Enums\WafMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'waf_mode' => ['nullable', Rule::enum(WafMode::class)],
        ];
    }
}
