<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRdaAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'                => 'required|integer|min:1',
            'patient_document_type'  => 'required|string',
            'patient_document_number' => 'required|string',
            'tipo_rda_id' => [
                'required',
                'integer',
                Rule::exists(env('DB_CONNECTION') . '.ihce.ihce_cat_tipos_rda', 'id')
            ],
            'rda_id' => 'required|string',
        ];
    }
}
