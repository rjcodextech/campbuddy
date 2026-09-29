<?php

namespace App\Http\Requests;

use App\Models\Offer;
use App\Support\DealForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One deal, from an event's Deals page or from Default deals (which also
 * sends `countries`). Only title, description and link are required, so
 * a quick deal stays quick; everything else fills out the card.
 */
class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('offer') ? 'update' : 'create', $this->route('offer') ?: Offer::class);
    }

    protected function prepareForValidation(): void
    {
        // "IN, bd" → ["IN", "BD"].
        if (is_string($this->input('countries'))) {
            $this->merge([
                'countries' => collect(preg_split('/[\s,;]+/', strtoupper($this->input('countries'))) ?: [])
                    ->filter()->unique()->values()->all(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'brand' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'highlight' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'opens_in_app' => ['boolean'],
            'countries' => ['nullable', 'array', 'max:60'],
            'countries.*' => ['string', 'size:2', 'alpha'],
            'icon' => ['nullable', 'string', 'max:10'],
            'media_asset_id' => ['nullable', 'exists:media_assets,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'capture_leads' => ['boolean'],
            'lead_form' => ['nullable', 'array'],
            'lead_form.intro' => ['nullable', 'string', 'max:255'],
            'lead_form.fields' => ['nullable', 'array'],
            'lead_form.fields.*.mode' => ['nullable', 'in:'.implode(',', DealForm::MODES)],
            'lead_form.fields.*.label' => ['nullable', 'string', 'max:80'],
            'lead_form.fields.*.hint' => ['nullable', 'string', 'max:120'],
            'lead_form.choices.mode' => ['nullable', 'in:'.implode(',', DealForm::MODES)],
            'lead_form.choices.label' => ['nullable', 'string', 'max:80'],
            'lead_form.choices.multiple' => ['nullable', 'boolean'],
            'lead_form.choices.options' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The validated deal, ready to save: the contact form as stored JSON,
     * and countries only where they mean something (a default deal).
     *
     * @return array<string, mixed>
     */
    public function deal(bool $isDefault): array
    {
        $data = $this->validated();

        // An emptied order box keeps the deal where it is (or puts a new one last).
        if (array_key_exists('sort_order', $data) && $data['sort_order'] === null) {
            unset($data['sort_order']);
        }

        if (array_key_exists('lead_form', $data)) {
            $data['lead_form'] = DealForm::fromInput($data['lead_form']);
        }

        if ($isDefault) {
            $data['countries'] = array_values($data['countries'] ?? []) ?: null;
        } else {
            unset($data['countries']);
        }

        return $data;
    }
}
