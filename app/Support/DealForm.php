<?php

namespace App\Support;

/**
 * The contact form a deal asks for before it opens (Offer::capture_leads),
 * as set per deal in the admin (offers.lead_form).
 *
 * Four fields, each off / optional / required with its own label and hint,
 * plus an optional list of products to tick ("Need a special plan for:
 * Knit Pay Pro / Knit Pay UPI"). Email is always asked: it is how the
 * sponsor gets back to the attendee, and how a second submission of the
 * same deal updates the first instead of adding a duplicate.
 *
 * An empty lead_form is the form deals always had: name and email
 * required, phone optional — so older deals behave as before.
 */
class DealForm
{
    public const MODES = ['off', 'optional', 'required'];

    /** Field → [default label, max length, default mode]. */
    public const FIELDS = [
        'name' => ['Name', 191, 'required'],
        'company' => ['Company name', 191, 'off'],
        'email' => ['Email', 191, 'required'],
        'mobile' => ['Mobile', 32, 'optional'],
    ];

    public const MAX_OPTIONS = 12;

    public const OPTION_LENGTH = 80;

    /**
     * The stored form with every gap filled, safe to render and validate.
     *
     * @return array{intro: ?string, fields: array<string, array{mode: string, label: string, hint: ?string}>, choices: array{mode: string, label: string, multiple: bool, options: list<string>}}
     */
    public static function normalize(?array $raw): array
    {
        $raw ??= [];
        $fields = [];

        foreach (self::FIELDS as $key => [$label, , $mode]) {
            $given = is_array($raw['fields'][$key] ?? null) ? $raw['fields'][$key] : [];
            $fields[$key] = [
                'mode' => $key === 'email' ? 'required' : (in_array($given['mode'] ?? null, self::MODES, true) ? $given['mode'] : $mode),
                'label' => self::text($given['label'] ?? null, 80) ?? $label,
                'hint' => self::text($given['hint'] ?? null, 120),
            ];
        }

        $choices = is_array($raw['choices'] ?? null) ? $raw['choices'] : [];
        $options = self::options($choices['options'] ?? []);

        return [
            'intro' => self::text($raw['intro'] ?? null, 255),
            'fields' => $fields,
            'choices' => [
                // A list with nothing in it can't be asked.
                'mode' => $options === [] ? 'off' : (in_array($choices['mode'] ?? null, self::MODES, true) ? $choices['mode'] : 'off'),
                'label' => self::text($choices['label'] ?? null, 80) ?? 'Choose',
                'multiple' => (bool) ($choices['multiple'] ?? true),
                'options' => $options,
            ],
        ];
    }

    /**
     * What the admin form sent (form[fields][name][mode] …, options one per
     * line), as the JSON to store.
     */
    public static function fromInput(?array $input): array
    {
        $input ??= [];
        $choices = is_array($input['choices'] ?? null) ? $input['choices'] : [];

        if (is_string($choices['options'] ?? null)) {
            $choices['options'] = preg_split('/\R/u', $choices['options']) ?: [];
        }
        $choices['multiple'] = filter_var($choices['multiple'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $input['choices'] = $choices;

        return self::normalize($input);
    }

    /**
     * Validation rules for an attendee's submission of this form.
     *
     * @return array<string, mixed>
     */
    public static function rules(array $form): array
    {
        $rules = [];

        foreach (self::FIELDS as $key => [, $max]) {
            $rules[$key] = match ($form['fields'][$key]['mode']) {
                'required' => ['required', 'string', "max:{$max}"],
                'optional' => ['nullable', 'string', "max:{$max}"],
                default => ['prohibited'],
            };
        }
        $rules['email'][] = 'email';

        $choices = $form['choices'];
        if ($choices['mode'] === 'off') {
            $rules['choices'] = ['prohibited'];
        } else {
            $rules['choices'] = [$choices['mode'] === 'required' ? 'required' : 'nullable', 'array', 'max:'.($choices['multiple'] ? count($choices['options']) : 1)];
            $rules['choices.*'] = ['string', 'distinct', \Illuminate\Validation\Rule::in($choices['options'])];
        }

        return $rules;
    }

    /** @return list<string> */
    private static function options(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->map(fn ($option) => self::text(is_string($option) ? $option : null, self::OPTION_LENGTH))
            ->filter()
            ->unique()
            ->take(self::MAX_OPTIONS)
            ->values()
            ->all();
    }

    private static function text(?string $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
