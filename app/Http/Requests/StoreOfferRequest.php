<?php

namespace App\Http\Requests;

use App\Models\Offer;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('offer') ? 'update' : 'create', $this->route('offer') ?: Offer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
            'icon' => ['nullable', 'string', 'max:10'],
            'media_asset_id' => ['nullable', 'exists:media_assets,id'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
