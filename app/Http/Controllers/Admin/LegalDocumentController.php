<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Support\SafeHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    /** Supported legal documents */
    protected array $supported = [
        'privacy' => [
            'name' => 'Privacy notice',
            'description' => 'User privacy disclosures, DPDPA 2023 compliance, purpose limitations, and user rights.',
            'view_route' => 'privacy',
        ],
        'terms' => [
            'name' => 'Terms of use',
            'description' => 'General terms, attendee code of conduct, liabilities, and service provisions.',
            'view_route' => 'terms',
        ],
    ];

    /**
     * List all legal documents and their customization status.
     */
    public function index(): View
    {
        $existing = LegalDocument::all()->keyBy('slug');

        $documents = collect($this->supported)->map(function ($meta, $slug) use ($existing) {
            $doc = $existing->get($slug);
            $hasCustom = $doc && !empty(trim($doc->content ?? ''));
            $defaultMeta = LegalDocument::defaultMeta($slug);

            return (object) [
                'slug' => $slug,
                'name' => $meta['name'],
                'description' => $meta['description'],
                'view_route' => $meta['view_route'],
                'is_customized' => $hasCustom,
                // Saved before the built-in notice last changed: the live page lacks what was added since.
                'is_outdated' => $hasCustom && $doc->updated_at !== null && $doc->updated_at->lt(LegalDocument::defaultChangedAt($slug)),
                'changes' => LegalDocument::defaultChanges($slug),
                'title' => $doc?->title ?: $defaultMeta['title'],
                'updated_at' => $doc?->updated_at,
            ];
        });

        return view('admin.legal.index', [
            'documents' => $documents,
        ]);
    }

    /**
     * Show the editor for direct document URLs.
     */
    public function show(string $slug): View
    {
        return $this->edit($slug);
    }

    /**
     * Show the editor for a specific legal document.
     */
    public function edit(string $slug): View
    {
        abort_unless(array_key_exists($slug, $this->supported), 404);

        $document = LegalDocument::forSlug($slug);
        $defaultMeta = LegalDocument::defaultMeta($slug);
        $defaultContent = LegalDocument::defaultContent($slug);

        return view('admin.legal.edit', [
            'slug' => $slug,
            'docMeta' => $this->supported[$slug],
            'document' => $document,
            'defaultMeta' => $defaultMeta,
            'defaultContent' => $defaultContent,
        ]);
    }

    /**
     * Update the legal document content.
     */
    public function update(Request $request, string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, $this->supported), 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        LegalDocument::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $validated['title'],
                'meta_description' => $validated['meta_description'],
                'content' => SafeHtml::clean($validated['content']),
            ]
        );

        return redirect()->route('admin.legal.index')
            ->with('status', 'legal-updated')
            ->with('message', "{$this->supported[$slug]['name']} has been updated successfully.");
    }

    /**
     * Reset the document back to default template.
     */
    public function reset(string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, $this->supported), 404);

        LegalDocument::where('slug', $slug)->delete();

        return redirect()->route('admin.legal.index')
            ->with('status', 'legal-reset')
            ->with('message', "{$this->supported[$slug]['name']} has been reset to default template.");
    }
}
