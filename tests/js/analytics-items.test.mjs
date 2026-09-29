// node --test tests/js  — Deals / Free Steals as GA4 ecommerce events: only
// GA's own item fields leave the page, and the cards' data becomes items.
import assert from 'node:assert/strict';
import { test } from 'node:test';

const sent = [];
globalThis.window = { gtag: (_kind, name, params) => sent.push([name, params]) };
globalThis.location = { href: 'http://campbuddy.test/event/x/explore', search: '' };

const { track, normalizeItems } = await import('../../resources/js/attendee/analytics.js');
const { promoItem, stealItem } = await import('../../resources/js/attendee/explore.js');

test('an item keeps only GA4 item fields, trimmed like any param', () => {
  assert.deepEqual(normalizeItems([{ item_id: 'steal-1', item_name: '  GoDAM ', index: 3, email: 'x@y.z', name: 'Asha' }]), [{ item_id: 'steal-1', item_name: 'GoDAM', index: 3 }]);
  assert.equal(normalizeItems('nope'), undefined);
  assert.equal(normalizeItems([{ email: 'x@y.z' }]), undefined);
  assert.equal(normalizeItems(Array.from({ length: 40 }, (_, i) => ({ item_id: `i${i}` }))).length, 25);
});

test('select_item sends the list and the item, and nothing unlisted', () => {
  sent.length = 0;
  track('select_item', { item_list_id: 'free_steals', item_list_name: 'Free Steals', items: [{ item_id: 'steal-2', item_name: 'The Off Switch', item_brand: 'Abhishek Deshpande', item_category: 'Performance', index: 2 }], secret: 'x' });
  assert.deepEqual(sent, [['select_item', { item_list_id: 'free_steals', item_list_name: 'Free Steals', items: [{ item_id: 'steal-2', item_name: 'The Off Switch', item_brand: 'Abhishek Deshpande', item_category: 'Performance', index: 2 }] }]]);
});

test('items are only allowed where listed', () => {
  sent.length = 0;
  track('deal_open', { offer_title: 'X', items: [{ item_id: 'a' }] });
  assert.deepEqual(sent, [['deal_open', { offer_title: 'X' }]]);
});

test('a deal card becomes a promotion, a free steal card a list item', () => {
  const deal = { dataset: { promoId: '5', promoName: '100 free transactions', promoCreative: 'Knit Pay Pro', position: '4' } };
  assert.deepEqual(promoItem(deal), {
    promotion_id: 'deal-5', promotion_name: '100 free transactions', creative_name: 'Knit Pay Pro', creative_slot: 'deals_4',
    item_id: 'deal-5', item_name: 'Knit Pay Pro', index: 4,
  });

  const steal = { dataset: { stealId: '13', stealName: 'WordPress Skills', stealMaker: 'Gaurav Tiwari', stealCategory: 'AI / Developer Tools', position: '1' } };
  assert.deepEqual(stealItem(steal), {
    item_list_id: 'free_steals', item_list_name: 'Free Steals', item_id: 'steal-13', item_name: 'WordPress Skills',
    item_brand: 'Gaurav Tiwari', item_category: 'AI / Developer Tools', index: 1,
  });
});

test('session and meet status say which session and where from, never who', () => {
  sent.length = 0;
  track('session_status', { plan_status: 'attended', schedule_session_id: 42, session_title: 'Opening Remarks', name: 'Asha' });
  track('meet_status', { plan_status: 'met', source: 'roster', personKey: 'r:9', name: 'Asha' });
  assert.deepEqual(sent, [
    ['session_status', { plan_status: 'attended', schedule_session_id: 42, session_title: 'Opening Remarks' }],
    ['meet_status', { plan_status: 'met', source: 'roster' }],
  ]);
});

test('"Details" opens and closes a card\'s description, and marks the card collapsible', async () => {
  const { setUpCardDetails } = await import('../../resources/js/attendee/explore.js');
  const classes = new Set();
  const card = { classList: { add: (c) => classes.add(c), toggle: (c, on) => (on ? classes.add(c) : classes.delete(c)) } };
  const attrs = { 'aria-expanded': 'false' };
  let onClick;
  const btn = {
    closest: () => card,
    getAttribute: (name) => attrs[name],
    setAttribute: (name, value) => { attrs[name] = value; },
    addEventListener: (_type, fn) => { onClick = fn; },
  };

  setUpCardDetails({ querySelectorAll: () => [btn] });
  assert.ok(classes.has('is-collapsible'));
  assert.ok(!classes.has('is-open'));

  onClick();
  assert.equal(attrs['aria-expanded'], 'true');
  assert.ok(classes.has('is-open'));

  onClick();
  assert.equal(attrs['aria-expanded'], 'false');
  assert.ok(!classes.has('is-open'));
});

test('a Free Steal row opens its sheet; the backdrop, close and the link shut it', async () => {
  const { setUpStealSheets } = await import('../../resources/js/attendee/explore.js');
  const on = {};
  const listen = (key) => (_type, fn) => { on[key] = fn; };
  let open = false;
  const closeBtn = { addEventListener: listen('close') };
  const link = { addEventListener: listen('link') };
  const dialog = {
    showModal: () => { open = true; },
    close: () => { open = false; },
    addEventListener: listen('backdrop'),
    querySelector: (sel) => (sel === '[data-steal-close]' ? closeBtn : link),
  };
  const btn = { closest: () => ({ querySelector: () => dialog }), addEventListener: listen('row') };

  setUpStealSheets({ querySelectorAll: () => [btn] });

  on.row();
  assert.ok(open);
  on.backdrop({ target: {} });
  assert.ok(open, 'a tap inside the sheet keeps it open');
  on.backdrop({ target: dialog });
  assert.ok(!open);

  on.row();
  on.close();
  assert.ok(!open);

  on.row();
  on.link();
  await new Promise((resolve) => setTimeout(resolve, 5));
  assert.ok(!open);
});
