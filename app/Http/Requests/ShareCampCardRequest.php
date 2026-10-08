<?php

namespace App\Http\Requests;

use App\Support\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Show my Camp Card on the attendee list": which list entry is me, and what
 * my card shows. The phone sends only the fields that are on the card; the
 * limits match the Camp Card form's own (camp-card.js / camp-card.blade.php).
 */
class ShareCampCardRequest extends FormRequest
{
    public const QR_TYPES = ['linkedin', 'website', 'wordpressOrg', 'twitter'];

    public function authorize(): bool
    {
        return true;
    }

    /** Collapse whitespace and drop control characters: all of this is shown to other people. */
    protected function prepareForValidation(): void
    {
        $clean = fn ($value) => is_string($value) ? trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{C}]/u', '', $value))) : $value;
        $card = is_array($this->input('card')) ? $this->input('card') : [];

        foreach (['role', 'company', 'city', 'askMeAbout'] as $key) {
            if (array_key_exists($key, $card)) {
                $card[$key] = $clean($card[$key]) === '' ? null : $clean($card[$key]);
            }
        }
        if (isset($card['interests']) && is_array($card['interests'])) {
            $card['interests'] = array_values(array_filter(array_map($clean, $card['interests']), fn ($t) => is_string($t) && $t !== ''));
        }

        $this->merge(['card' => $card]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'attendee_roster_id' => ['required', 'integer'],
            // Proof that a discovery profile on this same name is this phone's (optional).
            'discovery_id' => ['nullable', 'string', 'max:64'],
            'discovery_token' => ['nullable', 'string', 'max:64'],
            'card' => ['required', 'array'],
            'card.role' => ['nullable', 'string', 'max:60'],
            'card.company' => ['nullable', 'string', 'max:60'],
            'card.city' => ['nullable', 'string', 'max:60'],
            'card.askMeAbout' => ['nullable', 'string', 'max:80'],
            'card.interests' => ['nullable', 'array', 'max:8'],
            'card.interests.*' => ['string', 'max:30'],
            'card.qr' => ['nullable', 'array'],
            'card.qr.type' => ['required_with:card.qr', Rule::in(self::QR_TYPES)],
            'card.qr.url' => ['required_with:card.qr', 'string', 'max:300', function ($attribute, $value, $fail) {
                if (SafeUrl::web($value) === null) {
                    $fail('The card\'s link must be a web address.');
                }
            }],
        ];
    }

    /**
     * What is stored: only the card fields that have something in them.
     *
     * @return array<string, mixed>
     */
    public function cardFields(): array
    {
        $card = $this->validated('card');

        return array_filter([
            'role' => $card['role'] ?? null,
            'company' => $card['company'] ?? null,
            'city' => $card['city'] ?? null,
            'interests' => array_values(array_unique($card['interests'] ?? [])) ?: null,
            'askMeAbout' => $card['askMeAbout'] ?? null,
            'qr' => isset($card['qr']) ? ['type' => $card['qr']['type'], 'url' => SafeUrl::web($card['qr']['url'])] : null,
        ], fn ($v) => $v !== null);
    }
}
