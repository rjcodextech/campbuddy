// node --test tests/js  — a deal's contact form: what is read and what is sent.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { DEFAULT_FORM, leadBody, readForm } from '../../resources/js/attendee/deal-form.js';

const knitPay = {
  intro: null,
  fields: {
    name: { mode: 'optional', label: 'Name', hint: null },
    company: { mode: 'optional', label: 'Company name', hint: null },
    email: { mode: 'required', label: 'Registered email at RapidAPI', hint: null },
    mobile: { mode: 'optional', label: 'Phone number', hint: 'Recommended' },
  },
  choices: { mode: 'required', label: 'Need a special plan for', multiple: true, options: ['Knit Pay - Pro', 'Knit Pay - UPI'] },
};

test('a deal without a readable form gets the form deals always had', () => {
  assert.equal(readForm(''), DEFAULT_FORM);
  assert.equal(readForm('{not json'), DEFAULT_FORM);
  assert.equal(readForm('{"intro":"x"}'), DEFAULT_FORM);
  assert.deepEqual(readForm(JSON.stringify(knitPay)), knitPay);
});

test('only the fields the form asks for are sent, trimmed, empty ones as null', () => {
  const body = leadBody(knitPay, { name: '  ', company: ' Ariham ', email: ' dev@example.com ', mobile: '' }, new Set(['Knit Pay - UPI']));

  assert.deepEqual(body, { name: null, company: 'Ariham', email: 'dev@example.com', mobile: null, choices: ['Knit Pay - UPI'] });
});

test('the old form sends no company and no choices', () => {
  const body = leadBody(DEFAULT_FORM, { name: 'Ada', company: 'ignored', email: 'a@example.com', mobile: '98765' }, new Set());

  assert.deepEqual(body, { name: 'Ada', email: 'a@example.com', mobile: '98765' });
  assert.equal('company' in body, false);
  assert.equal('choices' in body, false);
});
