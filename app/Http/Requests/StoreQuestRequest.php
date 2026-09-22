<?php

namespace App\Http\Requests;

use App\Models\Quest;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('quest') ? 'update' : 'create', $this->route('quest') ?: Quest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
