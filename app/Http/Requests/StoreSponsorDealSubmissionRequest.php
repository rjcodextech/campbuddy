<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSponsorDealSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Publicly accessible submission form
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'sponsor_name' => ['required', 'string', 'max:191'],
            'brand' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:120'],
            'highlight' => ['nullable', 'string', 'max:40'],
            'description' => ['required', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:60'],
            'url' => ['required', 'url:http,https', 'max:500'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'icon' => ['nullable', 'string', 'max:10'],
            'logo_image' => ['nullable', 'file', 'image', 'mimes:png', 'max:100'],
            'logo_url' => ['nullable', 'url:http,https', 'max:500'],
            'submitter_name' => ['required', 'string', 'max:191'],
            'submitter_email' => ['required', 'email:filter', 'max:191'],
            'submitter_phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'submission_token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_id.required' => 'Please select a WordCamp event.',
            'event_id.exists' => 'The selected event could not be found.',
            'sponsor_name.required' => 'Please select or enter your sponsor name.',
            'title.required' => 'Please provide an offer title / headline.',
            'description.required' => 'Please provide the deal details.',
            'url.required' => 'A deal link is required.',
            'url.url' => 'The deal link must start with http:// or https://',
            'submitter_name.required' => 'Please provide your name for verification.',
            'submitter_email.required' => 'Please provide your work email address.',
            'submitter_email.email' => 'Please provide a valid email address.',
            'logo_image.image' => 'The uploaded logo must be an image.',
            'logo_image.mimes' => 'Only PNG (.png) images are allowed.',
            'logo_image.max' => 'The logo image must not exceed 100 KB in size.',
        ];
    }
}
