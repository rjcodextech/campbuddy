// Reusable multi-entry autocomplete tag input for "Looking to meet"
// Allows picking from curated suggestions, typing custom entries,
// adding multiple entries, and serializes back to comma-separated text.

export const WHO_SUGGESTIONS = [
  'Plugin Developers',
  'Agency Owners',
  'Core Contributors',
  'Freelancers',
  'Designers & UI/UX',
  'Theme Developers',
  'Content Creators & Bloggers',
  'Marketers & SEO Experts',
  'WooCommerce Builders',
  'Beginners & First-Timers',
  'Students & Learners',
  'Community Organizers',
  'Educators & Trainers',
  'Potential Clients & Partners',
  'Hiring Managers',
  'Job Seekers',
  'QA & Testers',
  'Accessibility Advocates',
  'Hosting Providers',
  'WordPress Enthusiasts',
];

/**
 * Initializes a multi-entry tags input with autocomplete and suggestions.
 *
 * @param {HTMLElement} root Parent container containing the elements
 * @param {Object} selectors Map of element selectors
 * @returns {{ flush: () => void, getTags: () => string[] }}
 */
export function initWhoToMeetInput(root, {
  container = '.who-tags',
  items = '.who-tags__items',
  textInput = '.who-tags__input',
  hiddenInput = 'input[type="hidden"]',
  addBtn = '.who-tags__add-btn',
  suggestions = '.who-suggestions',
} = {}) {
  const containerEl = root.querySelector(container);
  const itemsEl = root.querySelector(items);
  const textInputEl = root.querySelector(textInput);
  const hiddenInputEl = root.querySelector(hiddenInput);
  const addBtnEl = root.querySelector(addBtn);
  const suggestionsEl = root.querySelector(suggestions);

  if (!containerEl || !itemsEl || !textInputEl || !hiddenInputEl) {
    return { flush: () => {}, getTags: () => [] };
  }

  const tags = new Set();

  const sync = () => {
    hiddenInputEl.value = Array.from(tags).join(', ');

    itemsEl.replaceChildren(
      ...Array.from(tags).map((tag) => {
        const chip = document.createElement('span');
        chip.className = 'who-tag-chip';
        chip.textContent = tag;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.innerHTML = '&times;';
        removeBtn.setAttribute('aria-label', `Remove ${tag}`);
        removeBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          tags.delete(tag);
          sync();
        });

        chip.appendChild(removeBtn);
        return chip;
      })
    );
  };

  const addTag = (raw) => {
    if (!raw) return;
    const parts = String(raw).split(',');
    for (const part of parts) {
      const clean = part.trim().replace(/^,\s*|\s*,$/g, '');
      if (clean && clean.length <= 60 && tags.size < 12) {
        tags.add(clean);
      }
    }
    textInputEl.value = '';
    sync();
  };

  // Pre-fill existing value if any
  if (hiddenInputEl.value) {
    addTag(hiddenInputEl.value);
  }

  // Focus text input when container is clicked
  containerEl.addEventListener('click', (e) => {
    if (!e.target.closest('button')) {
      textInputEl.focus();
    }
  });

  // Handle Enter, comma and Backspace in text input
  textInputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      addTag(textInputEl.value);
    } else if (e.key === 'Backspace' && textInputEl.value === '' && tags.size > 0) {
      const last = Array.from(tags).pop();
      if (last) {
        tags.delete(last);
        sync();
      }
    }
  });

  // Also catch comma in input or datalist pick
  textInputEl.addEventListener('input', () => {
    if (textInputEl.value.includes(',')) {
      addTag(textInputEl.value);
    }
  });

  // Add button click
  addBtnEl?.addEventListener('click', (e) => {
    e.preventDefault();
    addTag(textInputEl.value);
    textInputEl.focus();
  });

  // Suggestion pill clicks
  suggestionsEl?.querySelectorAll('[data-suggest]')?.forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      addTag(btn.dataset.suggest);
      textInputEl.focus();
    });
  });

  const flush = () => {
    if (textInputEl.value.trim()) {
      addTag(textInputEl.value);
    }
  };

  return {
    flush,
    getTags: () => Array.from(tags),
  };
}
