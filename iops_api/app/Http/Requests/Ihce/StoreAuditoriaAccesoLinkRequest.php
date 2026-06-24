<?php

namespace App\Http\Requests\Ihce;

use Illuminate\Foundation\Http\FormRequest;

class StoreAuditoriaAccesoLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Require the client middleware, therefore authorization is handled there for now.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'uuid' => 'required|uuid',
            'usuario_id' => 'required|string|max:50',
            'ip' => 'required|ip',
            'estado' => 'nullable|string|max:20',
        ];
    }
}
