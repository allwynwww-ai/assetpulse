<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\TenantFormRequest;
use App\Models\FinancialYear;

class FinancialYearRequest extends TenantFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->booleans(['is_current']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:30', $this->uniqueInCompany('financial_years', 'name')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'in:open,closed'],
            'is_current' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $overlap = FinancialYear::query()
                ->when($this->recordId(), fn ($q, $id) => $q->whereKeyNot($id))
                ->where('start_date', '<=', $this->end_date)
                ->where('end_date', '>=', $this->start_date)
                ->first();
            if ($overlap) {
                $v->errors()->add('start_date', "These dates overlap with {$overlap->name}.");
            }
            if ($this->is_current && $this->status === 'closed') {
                $v->errors()->add('is_current', 'A closed year cannot be the current year.');
            }
        });
    }
}
