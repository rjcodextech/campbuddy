// The contact form a deal asks for before it opens, as the admin set it
// (App\Support\DealForm, sent in the card's data-lead-form). Pure logic,
// no DOM: which fields to send and how, for deal-leads.js and its tests.

export const TEXT_FIELDS = ['name', 'company', 'email', 'mobile'];

// The form every deal had before forms were set per deal.
export const DEFAULT_FORM = {
  intro: null,
  fields: {
    name: { mode: 'required', label: 'Name', hint: null },
    company: { mode: 'off', label: 'Company name', hint: null },
    email: { mode: 'required', label: 'Email', hint: null },
    mobile: { mode: 'optional', label: 'Mobile', hint: 'Optional.' },
  },
  choices: { mode: 'off', label: '', multiple: true, options: [] },
};

/** The deal's form from its data attribute; the old default if it's missing or unreadable. */
export function readForm(json) {
  try {
    const form = JSON.parse(json || 'null');
    return form && form.fields ? form : DEFAULT_FORM;
  } catch {
    return DEFAULT_FORM;
  }
}

/**
 * What to send for this form: only the fields it asks for, trimmed, empty
 * optional ones as null, and the ticked products.
 */
export function leadBody(form, values, picked) {
  const body = {};
  for (const field of TEXT_FIELDS) {
    if (form.fields[field]?.mode === 'off') continue;
    body[field] = (values[field] ?? '').trim() || null;
  }
  if (form.choices?.mode && form.choices.mode !== 'off') {
    body.choices = [...picked];
  }
  return body;
}
