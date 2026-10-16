<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Contracts\View\View;

class LegalPageController extends Controller
{
    /**
     * Display the Privacy notice.
     */
    public function privacy(): View
    {
        $document = LegalDocument::forSlug('privacy');

        return view('attendee.legal.privacy', [
            'document' => $document,
        ]);
    }

    /**
     * Display the Terms of use.
     */
    public function terms(): View
    {
        $document = LegalDocument::forSlug('terms');

        return view('attendee.legal.terms', [
            'document' => $document,
        ]);
    }
}
