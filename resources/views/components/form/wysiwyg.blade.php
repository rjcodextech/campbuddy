@props([
    'name',
    'id' => null,
    'label' => null,
    'value' => null,
    'hint' => null,
    'required' => false,
    'defaultContent' => null,
    'minHeight' => '480px',
])

@php
    $id = $id ?? $name;
    $current = old($name, $value ?? $defaultContent ?? '');
@endphp

<div class="wysiwyg-editor-wrapper space-y-2" id="{{ $id }}-container">
    @if ($label)
        <div class="flex items-center justify-between">
            <label for="{{ $id }}" class="block text-sm font-semibold text-ink">
                {{ $label }}
                @if ($required)
                    <span class="text-maroon-text">*</span>
                @endif
            </label>
            <div class="flex items-center gap-2 text-xs text-muted">
                <span id="{{ $id }}-word-count" class="font-medium">0 words</span>
                <span>&bull;</span>
                <span id="{{ $id }}-char-count">0 characters</span>
            </div>
        </div>
    @endif

    {{-- The outer editor card --}}
    <div class="wysiwyg-box relative overflow-hidden rounded-xl border border-line bg-white shadow-xs transition-all focus-within:border-maroon focus-within:ring-2 focus-within:ring-maroon/20">
        
        {{-- Toolbar --}}
        <div class="wysiwyg-toolbar flex flex-wrap items-center justify-between gap-1 border-b border-line bg-paper/80 px-3 py-2 text-ink">
            
            {{-- Left group: Formatting tools --}}
            <div class="flex flex-wrap items-center gap-1">
                
                {{-- Format block dropdown --}}
                <div class="relative inline-block mr-1">
                    <select id="{{ $id }}-format-select"
                            class="h-8 rounded-lg border border-line bg-white px-2.5 py-1 text-xs font-medium text-ink shadow-xs hover:border-maroon focus:border-maroon focus:outline-hidden focus:ring-1 focus:ring-maroon">
                        <option value="p">Paragraph</option>
                        <option value="h2">Heading 2 (H2)</option>
                        <option value="h3">Heading 3 (H3)</option>
                        <option value="h4">Heading 4 (H4)</option>
                        <option value="blockquote">Quote block</option>
                        <option value="pre">Code block (pre)</option>
                    </select>
                </div>

                <div class="h-4 w-px bg-line/80 mx-0.5"></div>

                {{-- Bold --}}
                <button type="button" data-cmd="bold" title="Bold (Ctrl+B)"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 4h8a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
                        <path d="M6 12h9a4 4 0 0 1 4 4 4 4 0 0 1-4 4H6z"></path>
                    </svg>
                </button>

                {{-- Italic --}}
                <button type="button" data-cmd="italic" title="Italic (Ctrl+I)"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="19" y1="4" x2="10" y2="4"></line>
                        <line x1="14" y1="20" x2="5" y2="20"></line>
                        <line x1="15" y1="4" x2="9" y2="20"></line>
                    </svg>
                </button>

                {{-- Underline --}}
                <button type="button" data-cmd="underline" title="Underline (Ctrl+U)"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M6 3v7a6 6 0 0 0 6 6 6 6 0 0 0 6-6V3"></path>
                        <line x1="4" y1="21" x2="20" y2="21"></line>
                    </svg>
                </button>

                {{-- Strike --}}
                <button type="button" data-cmd="strikeThrough" title="Strikethrough"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 4H9a3 3 0 0 0-2.83 4"></path>
                        <path d="M14 12a4 4 0 0 1 0 8H6"></path>
                        <line x1="4" y1="12" x2="20" y2="12"></line>
                    </svg>
                </button>

                {{-- Inline code --}}
                <button type="button" id="{{ $id }}-code-inline-btn" title="Inline code"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 18 22 12 16 6"></polyline>
                        <polyline points="8 6 2 12 8 18"></polyline>
                    </svg>
                </button>

                <div class="h-4 w-px bg-line/80 mx-0.5"></div>

                {{-- Unordered list --}}
                <button type="button" data-cmd="insertUnorderedList" title="Bullet List"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                </button>

                {{-- Ordered list --}}
                <button type="button" data-cmd="insertOrderedList" title="Numbered List"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="10" y1="6" x2="21" y2="6"></line>
                        <line x1="10" y1="12" x2="21" y2="12"></line>
                        <line x1="10" y1="18" x2="21" y2="18"></line>
                        <path d="M4 6h1v4"></path>
                        <path d="M4 10h2"></path>
                        <path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path>
                    </svg>
                </button>

                <div class="h-4 w-px bg-line/80 mx-0.5"></div>

                {{-- Link --}}
                <button type="button" id="{{ $id }}-link-btn" title="Insert / Edit Link"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                </button>

                {{-- Unlink --}}
                <button type="button" data-cmd="unlink" title="Remove Link"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M5.16 11.75l-1.72 1.71a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        <line x1="2" y1="2" x2="22" y2="22"></line>
                    </svg>
                </button>

                <div class="h-4 w-px bg-line/80 mx-0.5"></div>

                {{-- Insert Table --}}
                <button type="button" id="{{ $id }}-table-btn" title="Insert Responsive Table"
                        class="wysiwyg-btn inline-flex items-center gap-1 rounded-lg border border-line bg-white px-2 py-1 text-xs font-medium text-ink shadow-xs hover:border-maroon hover:text-maroon-text focus:outline-hidden">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="3" y1="15" x2="21" y2="15"></line>
                        <line x1="12" y1="3" x2="12" y2="21"></line>
                    </svg>
                    <span>Table</span>
                </button>

                {{-- Callout Box (The signature peach box in CampBuddy) --}}
                <button type="button" id="{{ $id }}-callout-btn" title="Insert Highlight / Callout Box"
                        class="wysiwyg-btn inline-flex items-center gap-1 rounded-lg border border-line bg-white px-2 py-1 text-xs font-medium text-ink shadow-xs hover:border-maroon hover:text-maroon-text focus:outline-hidden">
                    <svg class="h-3.5 w-3.5 text-peach-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="3"></rect>
                        <line x1="7" y1="8" x2="17" y2="8"></line>
                        <line x1="7" y1="12" x2="17" y2="12"></line>
                        <line x1="7" y1="16" x2="13" y2="16"></line>
                    </svg>
                    <span>Callout</span>
                </button>

                {{-- Horizontal line --}}
                <button type="button" data-cmd="insertHorizontalRule" title="Horizontal line"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </button>

                {{-- Clear formatting --}}
                <button type="button" data-cmd="removeFormat" title="Clear Formatting"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-muted hover:bg-white hover:border-line hover:text-danger focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <path d="M4 6h16"></path>
                        <path d="M10 6v6"></path>
                    </svg>
                </button>

                {{-- Undo / Redo --}}
                <button type="button" data-cmd="undo" title="Undo (Ctrl+Z)"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                </button>
                <button type="button" data-cmd="redo" title="Redo (Ctrl+Y)"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-ink hover:bg-white hover:border-line hover:text-maroon-text focus:outline-hidden active:bg-paper">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                </button>
            </div>

            {{-- Right group: View Modes & Previews --}}
            <div class="flex items-center gap-1.5 mt-1 sm:mt-0">
                
                {{-- Source Code mode toggle --}}
                <button type="button" id="{{ $id }}-mode-toggle"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-white px-2.5 py-1 text-xs font-semibold text-ink shadow-xs hover:border-maroon hover:text-maroon-text focus:outline-hidden transition-colors"
                        title="Toggle HTML Source Code View">
                    <svg class="h-3.5 w-3.5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="16 18 22 12 16 6"></polyline>
                        <polyline points="8 6 2 12 8 18"></polyline>
                    </svg>
                    <span id="{{ $id }}-mode-label">&lt;/&gt; HTML</span>
                </button>

                {{-- Live preview button --}}
                <button type="button" id="{{ $id }}-preview-btn"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-white px-2.5 py-1 text-xs font-semibold text-ink shadow-xs hover:border-maroon hover:text-maroon-text focus:outline-hidden transition-colors"
                        title="Live Preview of Document">
                    <svg class="h-3.5 w-3.5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span>Preview</span>
                </button>

                {{-- Load default content button --}}
                @if ($defaultContent)
                    <button type="button" id="{{ $id }}-default-btn"
                            class="inline-flex items-center gap-1 rounded-lg border border-line bg-white px-2 py-1 text-xs font-medium text-muted shadow-xs hover:border-maroon hover:text-ink focus:outline-hidden"
                            title="Insert complete default legal template">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        <span>Reset Template</span>
                    </button>
                @endif

                {{-- Fullscreen toggle --}}
                <button type="button" id="{{ $id }}-fullscreen-btn" title="Toggle Fullscreen"
                        class="wysiwyg-btn inline-flex h-8 w-8 items-center justify-center rounded-lg border border-transparent text-muted hover:bg-white hover:border-line hover:text-ink focus:outline-hidden">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <polyline points="9 21 3 21 3 15"></polyline>
                        <line x1="21" y1="3" x2="14" y2="10"></line>
                        <line x1="3" y1="21" x2="10" y2="14"></line>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Visual WYSIWYG Contenteditable Canvas --}}
        <div id="{{ $id }}-visual"
             contenteditable="true"
             spellcheck="true"
             class="wysiwyg-content legal px-6 py-5 overflow-y-auto focus:outline-hidden text-ink selection:bg-peach"
             style="min-height: {{ $minHeight }}; max-height: 720px;"
             role="textbox"
             aria-multiline="true">{!! $current !!}</div>

        {{-- Raw HTML Code Editor Canvas (Monospace, syntax feel) --}}
        <textarea id="{{ $id }}-code"
                  class="wysiwyg-code hidden w-full px-5 py-4 font-mono text-xs leading-relaxed text-ink bg-paper/30 border-0 focus:ring-0 focus:outline-hidden resize-y selection:bg-peach"
                  style="min-height: {{ $minHeight }}; max-height: 720px;"
                  spellcheck="false">{{ $current }}</textarea>

        {{-- Hidden form input submitted with the form --}}
        <textarea name="{{ $name }}" id="{{ $id }}" class="hidden" @if($required) required @endif>{{ $current }}</textarea>

        {{-- Editor status bar --}}
        <div class="flex items-center justify-between border-t border-line bg-paper/40 px-4 py-2 text-xs text-muted">
            <div class="flex items-center gap-2">
                <span class="inline-block h-2 w-2 rounded-full bg-emerald-500" id="{{ $id }}-status-dot"></span>
                <span id="{{ $id }}-status-mode" class="font-medium text-ink">Visual Editor Mode</span>
                <span class="hidden sm:inline">&bull; Changes automatically synced with form</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden md:inline text-muted/80">Prose, tables &amp; callout boxes supported</span>
            </div>
        </div>
    </div>

    @if ($hint)
        <p class="text-xs text-muted" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif

    {{-- Link Modal Dialog --}}
    <dialog id="{{ $id }}-link-dialog" class="rounded-xl border border-line bg-white p-0 shadow-2xl backdrop:bg-ink/50 max-w-md w-full">
        <form method="dialog" class="p-5 space-y-4">
            <div class="flex items-center justify-between border-b border-line pb-3">
                <h3 class="text-sm font-semibold text-ink">Insert / Edit Link</h3>
                <button type="button" class="text-muted hover:text-ink" onclick="this.closest('dialog').close()">&times;</button>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">Target URL or Mailto <span class="text-maroon-text">*</span></label>
                    <input type="text" id="{{ $id }}-link-url" placeholder="https://... or mailto:..." class="cb-input w-full text-xs">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink mb-1">Link display text</label>
                    <input type="text" id="{{ $id }}-link-text" placeholder="e.g. Data Protection Notice" class="cb-input w-full text-xs">
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <input type="checkbox" id="{{ $id }}-link-newtab" class="rounded border-line text-maroon-text focus:ring-maroon">
                    <label for="{{ $id }}-link-newtab" class="text-xs text-ink">Open link in new browser tab</label>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-line pt-3">
                <button type="button" class="btn btn--secondary py-1 text-xs" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="button" id="{{ $id }}-link-submit" class="btn btn--primary py-1 text-xs">Insert Link</button>
            </div>
        </form>
    </dialog>

    {{-- Live Preview Modal Dialog --}}
    <dialog id="{{ $id }}-preview-dialog" class="rounded-2xl border border-line bg-white p-0 shadow-2xl backdrop:bg-ink/60 max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="flex flex-col h-full max-h-[90vh]">
            <div class="flex items-center justify-between border-b border-line px-6 py-4 bg-paper/60">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2.5 w-2.5 rounded-full bg-maroon"></span>
                    <h3 class="text-sm font-bold text-ink">Live Attendee Preview</h3>
                    <span class="text-xs text-muted">(Matches exact frontend stylesheet)</span>
                </div>
                <button type="button" class="rounded-lg p-1 text-muted hover:bg-paper hover:text-ink" onclick="this.closest('dialog').close()">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="6"></line></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-6 sm:p-10 bg-white">
                <article class="legal" id="{{ $id }}-preview-body">
                    {{-- Dynamically injected preview HTML --}}
                </article>
            </div>
            <div class="flex items-center justify-between border-t border-line px-6 py-3 bg-paper/40">
                <span class="text-xs text-muted">Previewing responsive legal layout container</span>
                <button type="button" class="btn btn--primary py-1 text-xs" onclick="this.closest('dialog').close()">Done</button>
            </div>
        </div>
    </dialog>
</div>

{{-- Styles specific to the visual contenteditable canvas in admin --}}
<style>
    /* Styling inside contenteditable to match CampBuddy frontend */
    .wysiwyg-content {
        line-height: 1.6;
        color: var(--ink, #1f1d1d);
        font-family: inherit;
    }
    .wysiwyg-content:focus {
        outline: none;
    }
    .wysiwyg-content h1 { font-size: 1.75rem; line-height: 1.2; margin: 16px 0 6px; font-weight: 800; }
    .wysiwyg-content h2 { font-size: 1.25rem; margin: 24px 0 8px; font-weight: 800; border-bottom: 1px solid #f0e6dc; padding-bottom: 4px; }
    .wysiwyg-content h3 { font-size: 1.05rem; margin: 18px 0 6px; font-weight: 700; color: #7a220d; }
    .wysiwyg-content h4 { font-size: 0.95rem; margin: 14px 0 4px; font-weight: 700; }
    .wysiwyg-content p, .wysiwyg-content li { font-size: 0.95rem; margin-bottom: 8px; }
    .wysiwyg-content ul { padding-left: 1.3rem; margin: 6px 0 12px; list-style-type: disc; }
    .wysiwyg-content ol { padding-left: 1.3rem; margin: 6px 0 12px; list-style-type: decimal; }
    .wysiwyg-content li + li { margin-top: 4px; }
    .wysiwyg-content blockquote {
        border-left: 3px solid #ff4d2d;
        background: #fff8f5;
        padding: 8px 14px;
        margin: 12px 0;
        font-style: italic;
        color: #555;
        border-radius: 0 6px 6px 0;
    }
    .wysiwyg-content .legal__box {
        background: #ffefe5;
        border: 1px solid #f4c2b3;
        border-radius: 8px;
        padding: 14px 16px;
        margin: 16px 0;
    }
    .wysiwyg-content table {
        width: 100%;
        border-collapse: collapse;
        margin: 14px 0;
        font-size: 0.9rem;
    }
    .wysiwyg-content th, .wysiwyg-content td {
        text-align: left;
        vertical-align: top;
        padding: 8px 10px;
        border: 1px solid #e5d8cc;
    }
    .wysiwyg-content th {
        background: #ffefe5;
        font-weight: 700;
        color: #1f1d1d;
    }
    .wysiwyg-content tr:nth-child(even) td {
        background: #fffdfb;
    }
    .wysiwyg-content a {
        color: #ff4d2d;
        text-decoration: underline;
    }
    .wysiwyg-content code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        background: #f4ece4;
        padding: 2px 4px;
        border-radius: 4px;
        font-size: 0.85em;
        color: #7a220d;
    }
    .wysiwyg-content hr {
        border: 0;
        border-top: 1px dashed #d9cbbe;
        margin: 20px 0;
    }

    /* Fullscreen state */
    .wysiwyg-box.is-fullscreen {
        position: fixed !important;
        inset: 0 !important;
        z-index: 9999 !important;
        border-radius: 0 !important;
        height: 100vh !important;
        display: flex !important;
        flex-direction: column !important;
    }
    .wysiwyg-box.is-fullscreen .wysiwyg-content,
    .wysiwyg-box.is-fullscreen .wysiwyg-code {
        flex: 1 1 auto !important;
        max-height: calc(100vh - 90px) !important;
        min-height: calc(100vh - 90px) !important;
    }
</style>

{{-- Client-side JavaScript Controller for WYSIWYG Editor --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editorId = @json($id);
    const container = document.getElementById(editorId + '-container');
    if (!container) return;

    const visual = document.getElementById(editorId + '-visual');
    const code = document.getElementById(editorId + '-code');
    const hidden = document.getElementById(editorId);
    const box = container.querySelector('.wysiwyg-box');
    const modeToggle = document.getElementById(editorId + '-mode-toggle');
    const modeLabel = document.getElementById(editorId + '-mode-label');
    const statusMode = document.getElementById(editorId + '-status-mode');
    const statusDot = document.getElementById(editorId + '-status-dot');
    const wordCountEl = document.getElementById(editorId + '-word-count');
    const charCountEl = document.getElementById(editorId + '-char-count');
    const previewBtn = document.getElementById(editorId + '-preview-btn');
    const previewDialog = document.getElementById(editorId + '-preview-dialog');
    const previewBody = document.getElementById(editorId + '-preview-body');
    const fullscreenBtn = document.getElementById(editorId + '-fullscreen-btn');
    const defaultBtn = document.getElementById(editorId + '-default-btn');
    const linkBtn = document.getElementById(editorId + '-link-btn');
    const linkDialog = document.getElementById(editorId + '-link-dialog');
    const linkUrlInput = document.getElementById(editorId + '-link-url');
    const linkTextInput = document.getElementById(editorId + '-link-text');
    const linkNewTabInput = document.getElementById(editorId + '-link-newtab');
    const linkSubmitBtn = document.getElementById(editorId + '-link-submit');
    const formatSelect = document.getElementById(editorId + '-format-select');
    const codeInlineBtn = document.getElementById(editorId + '-code-inline-btn');
    const tableBtn = document.getElementById(editorId + '-table-btn');
    const calloutBtn = document.getElementById(editorId + '-callout-btn');

    let isCodeMode = false;
    let savedSelection = null;

    // Helper: update word and character count
    function updateCounts() {
        const text = isCodeMode ? code.value.replace(/<[^>]+>/g, ' ') : visual.innerText || '';
        const words = text.trim() ? text.trim().split(/\s+/).length : 0;
        const chars = text.length;

        if (wordCountEl) wordCountEl.textContent = words.toLocaleString() + ' words';
        if (charCountEl) charCountEl.textContent = chars.toLocaleString() + ' chars';
    }

    // Helper: Sync visual editor HTML to code view and hidden textarea
    function syncFromVisual() {
        const html = visual.innerHTML;
        code.value = html;
        hidden.value = html;
        updateCounts();
    }

    // Helper: Sync code view HTML to visual editor and hidden textarea
    function syncFromCode() {
        const html = code.value;
        visual.innerHTML = html;
        hidden.value = html;
        updateCounts();
    }

    // Handle typing and inputs
    visual.addEventListener('input', syncFromVisual);
    visual.addEventListener('blur', syncFromVisual);
    code.addEventListener('input', syncFromCode);

    // Save selection range before opening dialogs
    function saveSelection() {
        const sel = window.getSelection();
        if (sel.getRangeAt && sel.rangeCount) {
            savedSelection = sel.getRangeAt(0);
        }
    }

    function restoreSelection() {
        if (savedSelection) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedSelection);
        }
    }

    // Toolbar execCommands (Bold, Italic, Lists, etc.)
    container.querySelectorAll('.wysiwyg-btn[data-cmd]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (isCodeMode) return;
            const cmd = btn.dataset.cmd;
            const val = btn.dataset.value || null;
            visual.focus();
            document.execCommand(cmd, false, val);
            syncFromVisual();
        });
    });

    // Format Block select dropdown (p, h2, h3, h4, blockquote, pre)
    if (formatSelect) {
        formatSelect.addEventListener('change', () => {
            if (isCodeMode) return;
            const tag = formatSelect.value;
            visual.focus();
            document.execCommand('formatBlock', false, tag);
            syncFromVisual();
        });
    }

    // Inline code button
    if (codeInlineBtn) {
        codeInlineBtn.addEventListener('click', () => {
            if (isCodeMode) return;
            visual.focus();
            const sel = window.getSelection();
            if (!sel.isCollapsed) {
                const range = sel.getRangeAt(0);
                const codeEl = document.createElement('code');
                codeEl.textContent = range.toString();
                range.deleteContents();
                range.insertNode(codeEl);
                syncFromVisual();
            } else {
                document.execCommand('insertHTML', false, '<code>code</code>');
                syncFromVisual();
            }
        });
    }

    // Callout Box insertion
    if (calloutBtn) {
        calloutBtn.addEventListener('click', () => {
            if (isCodeMode) return;
            visual.focus();
            const calloutHtml = `
<div class="legal__box">
    <strong>Key Highlight / The short version</strong>
    <ul>
        <li>First important summary point.</li>
        <li>Second key detail or guarantee for attendees.</li>
    </ul>
</div>
<p></p>`;
            document.execCommand('insertHTML', false, calloutHtml);
            syncFromVisual();
        });
    }

    // Responsive Table insertion
    if (tableBtn) {
        tableBtn.addEventListener('click', () => {
            if (isCodeMode) return;
            visual.focus();
            const tableHtml = `
<table>
    <thead>
        <tr>
            <th>What</th>
            <th>Why</th>
            <th>Where, and how long</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td data-label="What"><strong>Example Category</strong>: Description of data or clause.</td>
            <td data-label="Why">Purpose of processing or rule description.</td>
            <td data-label="Where, and how long">Kept locally or on server for 90 days.</td>
        </tr>
        <tr>
            <td data-label="What"><strong>Second Item</strong>: Details here.</td>
            <td data-label="Why">Reason or condition.</td>
            <td data-label="Where, and how long">Details here.</td>
        </tr>
    </tbody>
</table>
<p></p>`;
            document.execCommand('insertHTML', false, tableHtml);
            syncFromVisual();
        });
    }

    // Link Dialog
    if (linkBtn && linkDialog) {
        linkBtn.addEventListener('click', () => {
            if (isCodeMode) return;
            saveSelection();
            const selectedText = savedSelection ? savedSelection.toString() : '';
            if (linkTextInput) linkTextInput.value = selectedText;
            if (linkUrlInput) linkUrlInput.value = '';
            linkDialog.showModal();
        });
    }

    if (linkSubmitBtn && linkDialog) {
        linkSubmitBtn.addEventListener('click', () => {
            const url = (linkUrlInput.value || '').trim();
            const text = (linkTextInput.value || '').trim();
            const newTab = linkNewTabInput && linkNewTabInput.checked;

            linkDialog.close();
            if (!url) return;

            visual.focus();
            restoreSelection();

            const targetAttr = newTab ? ' target="_blank" rel="noopener noreferrer"' : '';
            const linkHtml = `<a href="${url}"${targetAttr}>${text || url}</a>`;

            document.execCommand('insertHTML', false, linkHtml);
            syncFromVisual();
        });
    }

    // Toggle between Visual and HTML Code modes
    if (modeToggle) {
        modeToggle.addEventListener('click', () => {
            isCodeMode = !isCodeMode;
            if (isCodeMode) {
                // Switching to Code mode
                code.value = visual.innerHTML;
                visual.classList.add('hidden');
                code.classList.remove('hidden');
                code.focus();
                modeLabel.textContent = '👁 Visual';
                statusMode.textContent = 'HTML Source Code Mode';
                statusDot.className = 'inline-block h-2 w-2 rounded-full bg-amber-500';
            } else {
                // Switching to Visual mode
                visual.innerHTML = code.value;
                code.classList.add('hidden');
                visual.classList.remove('hidden');
                visual.focus();
                modeLabel.textContent = '</> HTML';
                statusMode.textContent = 'Visual Editor Mode';
                statusDot.className = 'inline-block h-2 w-2 rounded-full bg-emerald-500';
            }
            hidden.value = isCodeMode ? code.value : visual.innerHTML;
            updateCounts();
        });
    }

    // Live Preview Modal
    if (previewBtn && previewDialog && previewBody) {
        previewBtn.addEventListener('click', () => {
            if (isCodeMode) syncFromCode();
            else syncFromVisual();

            previewBody.innerHTML = hidden.value;
            previewDialog.showModal();
        });
    }

    // Toggle Fullscreen
    if (fullscreenBtn && box) {
        fullscreenBtn.addEventListener('click', () => {
            box.classList.toggle('is-fullscreen');
            const isFull = box.classList.contains('is-fullscreen');
            fullscreenBtn.classList.toggle('text-maroon-text', isFull);
        });
    }

    // Reset / Load Default Content
    if (defaultBtn) {
        defaultBtn.addEventListener('click', () => {
            const defaultContent = @json($defaultContent ?? '');
            if (!defaultContent) return;

            if (confirm('Load the complete default legal template? Any unsaved edits will be replaced.')) {
                visual.innerHTML = defaultContent;
                code.value = defaultContent;
                hidden.value = defaultContent;
                updateCounts();
            }
        });
    }

    // Form submit listener to ensure latest content is flushed
    const form = container.closest('form');
    if (form) {
        form.addEventListener('submit', () => {
            if (isCodeMode) {
                hidden.value = code.value;
            } else {
                hidden.value = visual.innerHTML;
            }
        });
    }

    // Initial count calculation
    updateCounts();
});
</script>
