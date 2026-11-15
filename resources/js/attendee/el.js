// Builds a small piece of UI as nodes — never from an HTML string, so nothing
// is ever parsed as markup (ready for Trusted Types). For anything bigger, use
// a <template> (template.js).
//
//   el('p', { class: 'card__title' }, 'Hello')
//   el('button', { type: 'button', class: 'btn', 'data-yes': '' }, 'Yes')

/** An element with attributes (a value of true sets an empty attribute; false/null/undefined leaves it off) and children (nodes or text). */
export function el(tag, attrs = {}, ...children) {
  const node = document.createElement(tag);

  for (const [name, value] of Object.entries(attrs)) {
    if (value === false || value == null) continue;
    node.setAttribute(name, value === true ? '' : String(value));
  }
  node.append(...children.flat().filter((child) => child != null && child !== false));

  return node;
}
