<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class ProviderOrderStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'provider_code' => ['nullable', 'string', 'max:50'],
            'remote_service_id' => ['nullable', 'string', 'max:50'],
            'user_login' => ['nullable', 'string', 'max:255'],
            'user_remote_id' => ['nullable', 'string', 'max:50'],
            'user_sort' => ['nullable', 'string', 'in:orders_count,total_charge'],
            'user_dir' => ['nullable', 'string', 'in:asc,desc'],
            'service_sort' => ['nullable', 'string', 'in:orders_count,total_charge'],
            'service_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * Filters array for ProviderOrder::scopeFilter().
     */
    public function filters(): array
    {
        $filters = [];
        if ($this->filled('date_from')) {
            $filters['date_from'] = \Carbon\Carbon::parse($this->date_from)->startOfDay();
        }
        if ($this->filled('date_to')) {
            $filters['date_to'] = \Carbon\Carbon::parse($this->date_to)->endOfDay();
        }
        if ($this->filled('provider_code')) {
            $filters['provider_code'] = $this->provider_code;
        }
        if ($this->filled('remote_service_id')) {
            $filters['remote_service_id'] = $this->remote_service_id;
        }
        if ($this->filled('user_login')) {
            $filters['user_login'] = $this->user_login;
        }
        if ($this->filled('user_remote_id')) {
            $filters['user_remote_id'] = $this->user_remote_id;
        }
        return $filters;
    }
}
