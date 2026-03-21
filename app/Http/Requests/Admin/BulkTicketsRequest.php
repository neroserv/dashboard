<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('assigned_to') && $this->input('assigned_to') === '') {
            $this->merge(['assigned_to' => null]);
        }
        if ($this->has('ticket_priority_id') && $this->input('ticket_priority_id') === '') {
            $this->merge(['ticket_priority_id' => null]);
        }
        if ($this->has('ticket_category_id') && $this->input('ticket_category_id') === '') {
            $this->merge(['ticket_category_id' => null]);
        }
        if ($this->has('status') && $this->input('status') === '') {
            $this->merge(['status' => null]);
        }
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Validation\ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['assign', 'status', 'priority', 'category'])],
            'ticket_ids' => ['required', 'array', 'min:1', 'max:150'],
            'ticket_ids.*' => ['integer', 'distinct', 'exists:tickets,id'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_admin', true)],
            'status' => ['nullable', 'string', Rule::in(['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'])],
            'ticket_priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'ticket_category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $action = $this->input('action');
            if ($action === 'status' && ! $this->filled('status')) {
                $validator->errors()->add('status', 'Bitte einen Status wählen.');
            }
            if ($action === 'category' && ! $this->filled('ticket_category_id')) {
                $validator->errors()->add('ticket_category_id', 'Bitte eine Kategorie wählen.');
            }
        });
    }
}
