// node --test tests/js  — the picker's bottom bar waits for the hero button to scroll away.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { TUCKED, initLandingBar } from '../../resources/js/attendee/landing-bar.js';

function page({ hero = true } = {}) {
  const classes = new Set([TUCKED]);
  const bar = { inert: false, classList: { toggle: (c, on) => (on ? classes.add(c) : classes.delete(c)), has: (c) => classes.has(c) } };
  const heroEl = {};
  const doc = { querySelector: (s) => (s === '.landing-bottom-bar' ? bar : s === '.landing-hero__actions' && hero ? heroEl : null) };
  let callback = null;
  let watched = null;
  class IntersectionObserver {
    constructor(fn) { callback = fn; }
    observe(el) { watched = el; }
    disconnect() {}
  }

  return { bar, heroEl, doc, win: { IntersectionObserver }, see: (isIntersecting) => callback([{ isIntersecting }]), watched: () => watched };
}

test('tucked while the hero button shows, up once it scrolls away, tucked again on the way back', () => {
  const p = page();
  initLandingBar({ doc: p.doc, win: p.win });
  assert.equal(p.watched(), p.heroEl);

  p.see(true);
  assert.ok(p.bar.classList.has(TUCKED));
  assert.equal(p.bar.inert, true, 'not focusable while off screen');

  p.see(false);
  assert.ok(!p.bar.classList.has(TUCKED));
  assert.equal(p.bar.inert, false);

  p.see(true);
  assert.ok(p.bar.classList.has(TUCKED));
});

test('no hero button or no IntersectionObserver: the bar just shows', () => {
  const noHero = page({ hero: false });
  assert.equal(initLandingBar({ doc: noHero.doc, win: noHero.win }), null);
  assert.ok(!noHero.bar.classList.has(TUCKED));

  const old = page();
  assert.equal(initLandingBar({ doc: old.doc, win: {} }), null);
  assert.ok(!old.bar.classList.has(TUCKED));
});

test('no bar on the page: nothing happens', () => {
  assert.equal(initLandingBar({ doc: { querySelector: () => null }, win: {} }), null);
});
