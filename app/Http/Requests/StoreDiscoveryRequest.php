<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The "Join attendee discovery" action (§3.4 M2). A fixed, curated tag
 * set drives matching — never free text (M3) — and the field set here is
 * deliberately narrower than onboarding's full answer set (§8.3).
 */
class StoreDiscoveryRequest extends FormRequest
{
    public const TAGS = [
        'developer', 'designer', 'content creator', 'site builder',
        'community organizer', 'marketer', 'business owner', 'blogger',
        'translator', 'speaker',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tags' => ['required', 'array', 'min:1', 'max:5'],
            'tags.*' => [Rule::in(self::TAGS)],
            'profession' => ['nullable', 'string', 'max:100'],
            'who_to_meet' => ['nullable', 'string', 'max:255'],
        ];
    }
}
