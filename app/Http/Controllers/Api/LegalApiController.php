<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\JsonResponse;

class LegalApiController extends Controller
{
    /**
     * Retrieve legal document content for direct in-app native rendering.
     */
    public function show(string $slug): JsonResponse
    {
        abort_unless(in_array($slug, ['privacy', 'terms'], true), 404);

        $document = LegalDocument::forSlug($slug);
        $defaultMeta = LegalDocument::defaultMeta($slug);

        $title = $document?->title ?: $defaultMeta['title'];
        $updated = $document?->updated_at ? $document->updated_at->format('d F Y') : $defaultMeta['updated'];
        $customContent = $document?->content;

        return response()->json([
            'slug' => $slug,
            'title' => $title,
            'updated' => $updated,
            'has_custom_content' => !empty(trim($customContent ?? '')),
            'content' => $customContent,
            'entity' => config('campbuddy.legal.entity'),
            'grievance_email' => config('campbuddy.legal.grievance_email'),
            'reply_days' => config('campbuddy.legal.reply_days', 30),
        ]);
    }
}
