// node --test tests/js  — "Suggest a Free Steal": which typed links are sent, and how.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { suggestionUrl } from '../../resources/js/attendee/free-steal-suggest.js';

test('a link typed without https:// gets it', () => {
  assert.equal(suggestionUrl('github.com/lubusIN/visual-blueprint-builder'), 'https://github.com/lubusIN/visual-blueprint-builder');
  assert.equal(suggestionUrl('  wordpress.org/plugins/wp-avoid-slow/ '), 'https://wordpress.org/plugins/wp-avoid-slow/');
});

test('http and https links are kept as typed', () => {
  assert.equal(suggestionUrl('http://example.org/tool'), 'http://example.org/tool');
  assert.equal(suggestionUrl('https://example.org/'), 'https://example.org/');
});

test('empty, non-web and host-less values are refused', () => {
  assert.equal(suggestionUrl(''), null);
  assert.equal(suggestionUrl('   '), null);
  assert.equal(suggestionUrl('javascript:alert(1)'), null);
  assert.equal(suggestionUrl('ftp://example.org/file'), null);
  assert.equal(suggestionUrl('my plugin'), null);
  assert.equal(suggestionUrl('localhost'), null);
});
