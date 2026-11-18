<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:64'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'config_version' => ['nullable', 'integer', 'min:0'],
            'config_hash' => ['nullable', 'string', 'max:64'],
        ];
    }
}
