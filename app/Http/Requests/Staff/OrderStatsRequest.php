<?php

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

class OrderStatsRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'max:50'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }

    /**
     * Filters array for Order::scopeFilter().
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
        if ($this->filled('status') && $this->status !== 'all') {
            $filters['status'] = $this->status;
        }
        if ($this->filled('service_id')) {
            $filters['service_id'] = (int) $this->service_id;
        }
        if ($this->filled('client_id')) {
            $filters['client_id'] = (int) $this->client_id;
        }
        return $filters;
    }
}
