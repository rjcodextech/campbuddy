<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The "Join attendee discovery" action. A fixed, curated tag
 * set drives matching — never free text (M3) — and the field set here is
 * deliberately narrower than onboarding's full answer set.
 *
 * Who the profile belongs to is the attendee's choice, one of three:
 *   - attendee_roster_id: "that's me" on the event's public attendee list —
 *     the name, photo and links shown are that entry's own, not typed here;
 *   - display_name: a name typed in (for someone not on the list);
 *   - neither: anonymous, as discovery always was.
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
     * A WordPress.org username can be pasted as the username or as the whole
     * profile address — keep just the username.
     */
    protected function prepareForValidation(): void
    {
        $wporg = trim((string) $this->input('wporg_username', ''));

        if (preg_match('#^(?:https?://)?profiles\.wordpress\.org/([^/?\#]+)#i', $wporg, $m)) {
            $wporg = $m[1];
        }

        $this->merge([
            'wporg_username' => $wporg === '' ? null : mb_strtolower(ltrim(rawurldecode($wporg), '@')),
            // Collapse whitespace and drop control characters: this is shown to other people.
            'display_name' => $this->filled('display_name')
                ? trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{C}]/u', '', (string) $this->input('display_name'))))
                : null,
        ]);
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
            'attendee_roster_id' => ['nullable', 'integer', 'min:1'],
            'display_name' => ['nullable', 'string', 'min:2', 'max:60'],
            // WordPress.org usernames: letters, digits and . _ - (and @ inside, from old email-style logins).
            'wporg_username' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9._@-]*$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'wporg_username.regex' => 'That doesn\'t look like a WordPress.org username — it\'s the part after profiles.wordpress.org/.',
        ];
    }
}
