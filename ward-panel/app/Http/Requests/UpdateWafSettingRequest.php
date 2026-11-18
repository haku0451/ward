<?php

namespace App\Http\Requests;

use App\Enums\WafMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWafSettingRequest extends FormRequest
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
            'waf_mode' => ['required', Rule::enum(WafMode::class)],
            'auto_block_enabled' => ['required', 'boolean'],
            'auto_block_threshold' => ['required', 'integer', 'min:2', 'max:10000'],
            'auto_block_window_seconds' => ['required', 'integer', 'min:30', 'max:86400'],
            'auto_block_duration_seconds' => ['required', 'integer', 'min:60', 'max:2592000'],
            'confirm_auto_block' => ['required_if:auto_block_enabled,true', 'accepted'],
        ];
    }
}
