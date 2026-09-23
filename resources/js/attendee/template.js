// The attendee app's markup lives in Blade, not in JS. Every piece of UI
// this app has to stamp out at runtime (a roster row, a schedule item, a
// dialog…) is a <template id="tpl-…"> emitted into the page shell by
// resources/views/attendee/templates/*.blade.php — so it's cached by the
// service worker along with the page and keeps rendering offline. JS only
// clones a template and fills its slots; it never authors HTML strings, so
// every value goes in as text or an attribute (never parsed as markup) and
// there's nothing to escape by hand.
//
// A slot is any element marked data-slot="name" (the template's own root
// counts). What a value means for its slot:
//
//   undefined / true   leave the template's default as-is
//   false / null       remove the element (an optional bit that doesn't apply)
//   string / number    its text
//   Node / Node[]      its children (replaces what was there)
//   { text, children, attrs, class }
//                      any combination of the above — attrs: string sets it,
//                      true sets an empty (boolean) attribute, false/null
//                      removes it (ARIA states need String(bool), not a
//                      boolean); class: { name: on/off }
//
// A <slot data-slot="name"> is an unwrapped placeholder instead: nodes
// replace the <slot> itself, adding no wrapper element — for lists whose
// items must stay direct children of their parent (e.g. :last-child rules).

function templateEl(id) {
  const el = document.getElementById(id);

  if (!(el instanceof HTMLTemplateElement)) {
    throw new Error(`Missing <template id="${id}"> — is its Blade partial included on this page?`);
  }

  return el;
}

/** Clones a single-root template and fills it; returns the root element. */
export function render(id, slots = {}) {
  const content = templateEl(id).content;

  if (content.childElementCount !== 1) {
    throw new Error(`<template id="${id}"> must have exactly one root element — use renderFragment().`);
  }

  return fill(document.importNode(content, true).firstElementChild, slots);
}

/** Clones a template with several top-level elements; returns a DocumentFragment. */
export function renderFragment(id, slots = {}) {
  return fill(document.importNode(templateEl(id).content, true), slots);
}

/** Fills the slots inside an Element or DocumentFragment (in place) and returns it. */
export function fill(root, slots) {
  for (const [name, value] of Object.entries(slots)) {
    const selector = `[data-slot="${name}"]`;
    const targets = [...root.querySelectorAll(selector)];

    if (root instanceof Element && root.matches(selector)) {
      targets.unshift(root);
    }

    targets.forEach((el) => apply(el, value));
  }

  return root;
}

function apply(el, value) {
  if (value === undefined || value === true) return;

  if (value === false || value === null) {
    el.remove();
    return;
  }

  if (value instanceof Node || Array.isArray(value)) {
    setChildren(el, value);
    return;
  }

  if (typeof value === 'object') {
    const { text, children, attrs = {}, class: classes = {} } = value;

    if (text !== undefined) el.textContent = text ?? '';
    if (children !== undefined) setChildren(el, children);

    for (const [name, attrValue] of Object.entries(attrs)) {
      if (attrValue === false || attrValue == null) {
        el.removeAttribute(name);
      } else {
        el.setAttribute(name, attrValue === true ? '' : String(attrValue));
      }
    }

    for (const [name, on] of Object.entries(classes)) {
      el.classList.toggle(name, Boolean(on));
    }

    return;
  }

  el.textContent = String(value);
}

function setChildren(el, nodes) {
  const list = Array.isArray(nodes) ? nodes : [nodes];

  if (el instanceof HTMLSlotElement) {
    el.replaceWith(...list);
  } else {
    el.replaceChildren(...list);
  }
}
