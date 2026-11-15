// Reads the SCSS sources into flat rules — full selectors (nesting and `&`
// resolved), the @media/@supports they sit in, and their declarations — so
// the brand and accessibility rules can be checked against the source itself.
// Not a Sass compiler: mixins, @each and interpolation are kept as written,
// which is enough for "what does this selector do, and under which media".

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const ROOT = new URL('../../../', import.meta.url);

export function scssFiles(dir = 'resources/scss') {
  const base = new URL(dir + '/', ROOT);
  const found = [];
  const walk = (path) => {
    for (const name of readdirSync(path)) {
      const full = join(path, name);
      if (statSync(full).isDirectory()) walk(full);
      else if (name.endsWith('.scss')) found.push(full);
    }
  };
  walk(base.pathname.replace(/^\/([A-Za-z]:)/, '$1'));

  return found;
}

function stripComments(source) {
  // Keep line breaks so line numbers still match.
  return source
    .replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, ' '))
    .replace(/(^|[^:"'])\/\/[^\n]*/g, (m, lead) => lead + ' '.repeat(m.length - lead.length));
}

function splitSelectors(text) {
  const parts = [];
  let depth = 0;
  let current = '';
  for (const ch of text) {
    if (ch === '(') depth++;
    if (ch === ')') depth--;
    if (ch === ',' && depth === 0) {
      parts.push(current.trim());
      current = '';
    } else current += ch;
  }
  if (current.trim()) parts.push(current.trim());

  return parts;
}

function combine(parents, own) {
  const out = [];
  for (const child of splitSelectors(own)) {
    if (!parents.length) {
      out.push(child);
      continue;
    }
    for (const parent of parents) out.push(child.includes('&') ? child.replace(/&/g, parent) : `${parent} ${child}`);
  }

  return out;
}

/**
 * @returns {Array<{ file: string, line: number, selectors: string[], media: string[], declarations: Array<{ property: string, value: string, line: number }> }>}
 */
export function parseScss(file) {
  const source = stripComments(readFileSync(file, 'utf8'));
  const rel = relative(new URL('.', ROOT).pathname.replace(/^\/([A-Za-z]:)/, '$1'), file).replace(/\\/g, '/');
  const rules = [];
  const stack = [{ selectors: [], media: [], rule: null }];
  let buffer = '';
  let bufferLine = 1;
  let line = 1;
  let quote = null;
  let parenDepth = 0;

  const flushDeclaration = () => {
    const text = buffer.trim();
    const top = stack[stack.length - 1];
    if (text && top.rule && !text.startsWith('@')) {
      const at = text.indexOf(':');
      if (at > 0) top.rule.declarations.push({ property: text.slice(0, at).trim(), value: text.slice(at + 1).trim(), line: bufferLine });
    }
    buffer = '';
  };

  for (let i = 0; i < source.length; i++) {
    const ch = source[i];
    if (ch === '\n') line++;
    if (quote) {
      buffer += ch;
      if (ch === quote && source[i - 1] !== '\\') quote = null;
      continue;
    }
    if (ch === '"' || ch === "'") {
      quote = ch;
      buffer += ch;
      continue;
    }
    if (ch === '(') parenDepth++;
    if (ch === ')') parenDepth--;
    // `#{...}` interpolation is not a block.
    if (ch === '{' && source[i - 1] === '#') {
      const end = source.indexOf('}', i);
      buffer += source.slice(i, end + 1);
      i = end;
      continue;
    }
    if (!buffer.trim()) bufferLine = line;
    if (ch === ';' && parenDepth === 0) {
      flushDeclaration();
      continue;
    }
    if (ch === '{') {
      const prelude = buffer.trim();
      buffer = '';
      const top = stack[stack.length - 1];
      // The project's own block mixins (abstracts/_mixins.scss, components/_effects.scss), as what they expand to.
      const mixin = prelude.match(/^@include\s+(?:m\.)?([\w-]+)/)?.[1];
      if (mixin === 'hover') {
        // m.hover = :hover on devices that hover, plus the same on :focus-visible.
        const selectors = [...combine(top.selectors, '&:hover'), ...combine(top.selectors, '&:focus-visible')];
        const rule = { file: rel, line: bufferLine, selectors, media: [...top.media, '@media (hover: hover)'], declarations: [] };
        rules.push(rule);
        stack.push({ selectors, media: rule.media, rule });
      } else if (mixin === 'tablet' || mixin === 'motion-safe' || prelude.startsWith('@media') || prelude.startsWith('@supports') || prelude.startsWith('@container')) {
        const media = [...top.media, mixin === 'tablet' ? '@media (min-width: tablet)' : mixin === 'motion-safe' ? '@media (prefers-reduced-motion: no-preference)' : prelude];
        // Declarations straight inside it belong to the enclosing selector, under this media.
        const rule = top.rule ? { file: rel, line: bufferLine, selectors: top.selectors, media, declarations: [] } : null;
        if (rule) rules.push(rule);
        stack.push({ selectors: top.selectors, media, rule });
      } else if (prelude.startsWith('@')) {
        // @keyframes, @font-face, @mixin, @each, @if…: their insides are not page selectors.
        stack.push({ selectors: top.selectors, media: [...top.media, prelude], rule: null, opaque: true });
      } else if (top.opaque) {
        stack.push({ selectors: [], media: top.media, rule: null, opaque: true });
      } else {
        const selectors = combine(top.selectors, prelude);
        const rule = { file: rel, line: bufferLine, selectors, media: top.media, declarations: [] };
        rules.push(rule);
        stack.push({ selectors, media: top.media, rule });
      }
      continue;
    }
    if (ch === '}') {
      flushDeclaration();
      if (stack.length > 1) stack.pop();
      continue;
    }
    buffer += ch;
  }

  return rules;
}

export function allRules(dir) {
  return scssFiles(dir).flatMap((file) => parseScss(file));
}
