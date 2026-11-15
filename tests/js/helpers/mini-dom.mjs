// A small DOM for attacking the attendee app's renderers in Node: real
// <template> markup from the Blade files is parsed into nodes, template.js
// clones and fills them exactly as in the browser, and every value that lands
// in the page can be inspected afterwards.
//
// Deliberately strict about the one thing that matters for these tests:
// setting innerHTML / outerHTML / insertAdjacentHTML records the string
// (`htmlWrites`), so a test can prove hostile text never went through an HTML
// parser. href/src/action properties reflect to attributes, as in a browser.

import { readFileSync } from 'node:fs';

export const htmlWrites = [];

const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr', 'use', 'path', 'circle', 'rect', 'line', 'polyline']);

export class Node {
  constructor() {
    this.parentNode = null;
    this.childNodes = [];
  }

  get firstChild() { return this.childNodes[0] ?? null; }

  get textContent() {
    return this.childNodes.map((c) => c.textContent).join('');
  }

  set textContent(value) {
    this.childNodes.forEach((c) => { c.parentNode = null; });
    this.childNodes = [];
    const text = String(value ?? '');
    if (text !== '') this.appendChild(new Text(text));
  }

  get children() { return this.childNodes.filter((c) => c instanceof Element); }
  get firstElementChild() { return this.children[0] ?? null; }
  get childElementCount() { return this.children.length; }

  appendChild(node) {
    if (node instanceof DocumentFragment) {
      [...node.childNodes].forEach((c) => this.appendChild(c));
      return node;
    }
    node.parentNode?.removeChild(node);
    node.parentNode = this;
    this.childNodes.push(node);
    return node;
  }

  append(...nodes) {
    nodes.forEach((n) => this.appendChild(typeof n === 'string' ? new Text(n) : n));
  }

  removeChild(node) {
    this.childNodes = this.childNodes.filter((c) => c !== node);
    node.parentNode = null;
    return node;
  }

  replaceChildren(...nodes) {
    this.childNodes.forEach((c) => { c.parentNode = null; });
    this.childNodes = [];
    this.append(...nodes);
  }

  remove() { this.parentNode?.removeChild(this); }

  replaceWith(...nodes) {
    const parent = this.parentNode;
    if (!parent) return;
    const at = parent.childNodes.indexOf(this);
    const list = nodes.flatMap((n) => (n instanceof DocumentFragment ? n.childNodes : [typeof n === 'string' ? new Text(n) : n]));
    list.forEach((n) => { n.parentNode?.removeChild(n); n.parentNode = parent; });
    parent.childNodes.splice(at, 1, ...list);
    this.parentNode = null;
  }

  before(...nodes) {
    const parent = this.parentNode;
    const list = nodes.map((n) => (typeof n === 'string' ? new Text(n) : n));
    list.forEach((n) => { n.parentNode?.removeChild(n); n.parentNode = parent; });
    parent.childNodes.splice(parent.childNodes.indexOf(this), 0, ...list);
  }

  after(...nodes) {
    const parent = this.parentNode;
    const list = nodes.map((n) => (typeof n === 'string' ? new Text(n) : n));
    list.forEach((n) => { n.parentNode?.removeChild(n); n.parentNode = parent; });
    parent.childNodes.splice(parent.childNodes.indexOf(this) + 1, 0, ...list);
  }

  /** Every element below this node, depth first. */
  *walk() {
    for (const child of this.childNodes) {
      if (child instanceof Element) {
        yield child;
        yield* child.walk();
      }
    }
  }

  querySelectorAll(selector) {
    const alternatives = selector.split(',').map((s) => s.trim());

    return [...this.walk()].filter((el) => alternatives.some((s) => el.matches(s)));
  }

  querySelector(selector) {
    return this.querySelectorAll(selector)[0] ?? null;
  }

  cloneNode(deep = false) {
    const copy = this.shallowCopy();
    if (deep) this.childNodes.forEach((c) => copy.appendChild(c.cloneNode(true)));
    return copy;
  }
}

export class Text extends Node {
  constructor(data) {
    super();
    this.data = String(data);
  }

  get textContent() { return this.data; }
  set textContent(value) { this.data = String(value); }
  shallowCopy() { return new Text(this.data); }
}

export class DocumentFragment extends Node {
  shallowCopy() { return new DocumentFragment(); }
}

class ClassList {
  constructor(el) { this.el = el; }
  get list() { return (this.el.getAttribute('class') ?? '').split(/\s+/).filter(Boolean); }
  contains(name) { return this.list.includes(name); }
  add(...names) { this.el.setAttribute('class', [...new Set([...this.list, ...names])].join(' ')); }
  remove(...names) { this.el.setAttribute('class', this.list.filter((n) => !names.includes(n)).join(' ')); }
  toggle(name, force) {
    const on = force === undefined ? !this.contains(name) : Boolean(force);
    if (on) this.add(name); else this.remove(name);
    return on;
  }
}

export class Element extends Node {
  constructor(tagName, namespaceURI = null) {
    super();
    this.tagName = tagName.toUpperCase();
    this.localName = tagName.toLowerCase();
    this.namespaceURI = namespaceURI;
    this.attributes = new Map();
    this.style = {};
    this.listeners = {};
    this.classList = new ClassList(this);
    this.dataset = new Proxy({}, {
      get: (_, key) => this.getAttribute(`data-${String(key).replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}`) ?? undefined,
      set: (_, key, value) => { this.setAttribute(`data-${String(key).replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`)}`, value); return true; },
    });
  }

  shallowCopy() {
    const copy = createElementFor(this.localName, this.namespaceURI);
    for (const [k, v] of this.attributes) copy.attributes.set(k, v);
    return copy;
  }

  getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
  setAttribute(name, value) { this.attributes.set(name, String(value)); }
  removeAttribute(name) { this.attributes.delete(name); }
  hasAttribute(name) { return this.attributes.has(name); }
  toggleAttribute(name, force) {
    const on = force === undefined ? !this.hasAttribute(name) : Boolean(force);
    if (on) this.setAttribute(name, ''); else this.removeAttribute(name);
    return on;
  }

  get id() { return this.getAttribute('id') ?? ''; }
  set id(v) { this.setAttribute('id', v); }
  get className() { return this.getAttribute('class') ?? ''; }
  set className(v) { this.setAttribute('class', v); }
  get href() { return this.getAttribute('href') ?? ''; }
  set href(v) { this.setAttribute('href', v); }
  get src() { return this.getAttribute('src') ?? ''; }
  set src(v) { this.setAttribute('src', v); }
  get target() { return this.getAttribute('target') ?? ''; }
  set target(v) { this.setAttribute('target', v); }
  get rel() { return this.getAttribute('rel') ?? ''; }
  set rel(v) { this.setAttribute('rel', v); }
  get type() { return this.getAttribute('type') ?? ''; }
  set type(v) { this.setAttribute('type', v); }
  get title() { return this.getAttribute('title') ?? ''; }
  set title(v) { this.setAttribute('title', v); }
  get hidden() { return this.hasAttribute('hidden'); }
  set hidden(v) { this.toggleAttribute('hidden', Boolean(v)); }

  get innerHTML() { return this.childNodes.map(serialize).join(''); }
  set innerHTML(html) {
    htmlWrites.push({ element: this, html: String(html) });
    this.replaceChildren(...parseHtml(String(html)).childNodes);
  }

  set outerHTML(html) { htmlWrites.push({ element: this, html: String(html) }); }
  insertAdjacentHTML(_, html) { htmlWrites.push({ element: this, html: String(html) }); }

  addEventListener(type, fn) { (this.listeners[type] ??= []).push(fn); }
  removeEventListener() {}
  dispatchEvent(event) { (this.listeners[event.type] ?? []).forEach((fn) => fn(event)); return true; }
  click() { this.dispatchEvent({ type: 'click', preventDefault() {}, stopPropagation() {} }); }
  closest(selector) {
    for (let el = this; el instanceof Element; el = el.parentNode) if (el.matches(selector)) return el;
    return null;
  }

  /** Simple selectors only: tag, #id, .class, [attr], [attr="v"], and combinations of them (no descendant combinators). */
  matches(selector) {
    const re = /([a-z][a-z0-9-]*)|#([\w-]+)|\.([\w-]+)|\[([\w-]+)(?:([~^$*]?=)"?([^"\]]*)"?)?\]/gi;
    let match;
    let consumed = '';
    while ((match = re.exec(selector))) {
      consumed += match[0];
      const [, tag, id, cls, attr, op, value] = match;
      if (tag && this.localName !== tag.toLowerCase()) return false;
      if (id && this.id !== id) return false;
      if (cls && !this.classList.contains(cls)) return false;
      if (attr) {
        if (!this.hasAttribute(attr)) return false;
        const actual = this.getAttribute(attr);
        if (op === '=' && actual !== value) return false;
        if (op === '^=' && !actual.startsWith(value)) return false;
      }
    }

    return consumed.length > 0 && consumed.length === selector.replace(/\s+/g, '').length;
  }
}

export class HTMLTemplateElement extends Element {
  constructor() {
    super('template');
    this.content = new DocumentFragment();
  }

  shallowCopy() { return new HTMLTemplateElement(); }
}

export class HTMLSlotElement extends Element {
  constructor() { super('slot'); }
  shallowCopy() { const c = new HTMLSlotElement(); for (const [k, v] of this.attributes) c.attributes.set(k, v); return c; }
}

export class HTMLDialogElement extends Element {
  constructor() { super('dialog'); }
  showModal() { this.setAttribute('open', ''); }
  close() { this.removeAttribute('open'); }
}

function createElementFor(tag, ns = null) {
  const lower = tag.toLowerCase();
  if (lower === 'template') return new HTMLTemplateElement();
  if (lower === 'slot') return new HTMLSlotElement();
  if (lower === 'dialog') return new HTMLDialogElement();

  return new Element(tag, ns);
}

const ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", times: '×', nbsp: ' ', middot: '·', hellip: '…', rsquo: '’', lsquo: '‘', ldquo: '“', rdquo: '”', ndash: '–', mdash: '—' };
const decode = (s) => s.replace(/&(#x[0-9a-f]+|#\d+|\w+);/gi, (m, e) => (e[0] === '#' ? String.fromCodePoint(e[1] === 'x' || e[1] === 'X' ? parseInt(e.slice(2), 16) : Number(e.slice(1))) : ENTITIES[e] ?? m));

/** Parses well-formed markup (as the Blade templates are) into a fragment. */
export function parseHtml(html) {
  const root = new DocumentFragment();
  const stack = [root];
  const tokens = html.replace(/<!--[\s\S]*?-->|\{\{--[\s\S]*?--\}\}/g, '').matchAll(/<\/([\w.:-]+)\s*>|<([\w.:-]+)((?:\s+[^\s=>/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*(\/?)>|([^<]+)/g);

  for (const [, close, open, attrs, selfClose, text] of tokens) {
    const top = stack[stack.length - 1];
    const into = top instanceof HTMLTemplateElement ? top.content : top;
    if (text !== undefined) {
      if (text.trim() !== '') into.appendChild(new Text(decode(text)));
      continue;
    }
    if (close) {
      while (stack.length > 1 && stack.pop().localName !== close.toLowerCase());
      continue;
    }
    const el = createElementFor(open);
    for (const [, name, , dq, sq, bare] of (attrs ?? '').matchAll(/([^\s=>/]+)(\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g)) {
      el.setAttribute(name, decode(dq ?? sq ?? bare ?? ''));
    }
    into.appendChild(el);
    if (!selfClose && !VOID.has(el.localName)) stack.push(el);
  }

  return root;
}

function serialize(node) {
  if (node instanceof Text) return node.data.replace(/&/g, '&amp;').replace(/</g, '&lt;');
  const attrs = [...node.attributes].map(([k, v]) => ` ${k}="${v.replace(/"/g, '&quot;')}"`).join('');

  return `<${node.localName}${attrs}>${node.childNodes.map(serialize).join('')}</${node.localName}>`;
}

/** The <template> elements of a Blade file (resources/views/…), by id. */
export function templatesFrom(file) {
  const source = readFileSync(new URL(`../../../resources/views/${file}`, import.meta.url), 'utf8');
  const fragment = parseHtml(source);
  const found = new Map();
  const visit = (node) => {
    for (const child of node.childNodes) {
      if (child instanceof HTMLTemplateElement && child.id) found.set(child.id, child);
      if (child instanceof Element) visit(child instanceof HTMLTemplateElement ? child.content : child);
    }
  };
  visit(fragment);

  return found;
}

/** Installs a document holding the given templates (and any other elements by id) as globals. Returns { document, restore }. */
export function installMiniDom({ templates = new Map(), elements = {} } = {}) {
  const body = new Element('body');
  const document = {
    body,
    documentElement: new Element('html'),
    visibilityState: 'visible',
    createElement: (tag) => createElementFor(tag),
    createElementNS: (ns, tag) => createElementFor(tag, ns),
    createTextNode: (text) => new Text(text),
    createDocumentFragment: () => new DocumentFragment(),
    importNode: (node, deep) => node.cloneNode(deep),
    getElementById: (id) => templates.get(id) ?? elements[id] ?? [...body.walk()].find((el) => el.id === id) ?? null,
    querySelector: (selector) => body.querySelector(selector),
    querySelectorAll: (selector) => body.querySelectorAll(selector),
    addEventListener() {},
  };
  const globals = { document, Node, Element, Text, DocumentFragment, HTMLTemplateElement, HTMLSlotElement, HTMLDialogElement, HTMLElement: Element };
  const saved = {};
  for (const [name, value] of Object.entries(globals)) {
    saved[name] = Object.getOwnPropertyDescriptor(globalThis, name);
    Object.defineProperty(globalThis, name, { value, configurable: true, writable: true });
  }
  htmlWrites.length = 0;

  return {
    document,
    restore() {
      for (const [name, descriptor] of Object.entries(saved)) {
        if (descriptor) Object.defineProperty(globalThis, name, descriptor);
        else delete globalThis[name];
      }
    },
  };
}

/** Every attribute value in a tree that would make a link or frame run script. */
export function scriptUrls(root) {
  const bad = [];
  for (const el of root instanceof Element ? [root, ...root.walk()] : root.walk()) {
    for (const name of ['href', 'src', 'action', 'formaction', 'xlink:href', 'srcdoc']) {
      const value = el.getAttribute(name);
      if (value !== null && /^\s*(javascript|data|vbscript):/i.test(value.replace(/[\u0000-\u001f]/g, ''))) bad.push(`${el.localName}[${name}]=${value}`);
    }
    for (const name of el.attributes.keys()) if (/^on/i.test(name)) bad.push(`${el.localName}[${name}]`);
  }

  return bad;
}
