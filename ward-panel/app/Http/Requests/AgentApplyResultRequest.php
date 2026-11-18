<?php

namespace App\Http\Requests;

use App\Enums\ApplyStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentApplyResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'config_version' => ['required', 'integer', 'min:1'],
            'config_hash' => ['required', 'string', 'max:64'],
            'ok' => ['required', 'boolean'],
            'message' => ['nullable', 'string', 'max:255'],
            'ip_blocks' => ['nullable', 'array', 'max:1000'],
            'ip_blocks.*.id' => ['required', 'integer', 'exists:ip_blocks,id'],
            'ip_blocks.*.status' => ['required', Rule::enum(ApplyStatus::class)],
            'ip_blocks.*.message' => ['nullable', 'string', 'max:255'],
        ];
    }
}
