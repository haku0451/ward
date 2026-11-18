<?php

namespace App\Http\Requests;

use App\Enums\EventAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentEventBatchRequest extends FormRequest
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
        $max = (int) config('ward.event_batch_max');

        return [
            'events' => ['required', 'array', 'max:'.$max],
            'events.*.event_id' => ['required', 'uuid'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.hostname' => ['nullable', 'string', 'max:255'],
            'events.*.client_ip' => ['required', 'ip'],
            'events.*.method' => ['required', 'string', 'max:16'],
            'events.*.path' => ['required', 'string', 'max:2048'],
            'events.*.rule_id' => ['nullable', 'string', 'max:64'],
            'events.*.rule_message' => ['nullable', 'string', 'max:1024'],
            'events.*.action' => ['required', Rule::enum(EventAction::class)],
            'events.*.http_status' => ['nullable', 'integer', 'min:100', 'max:599'],
            'events.*.correlation_id' => ['nullable', 'string', 'max:64'],
        ];
    }
}
