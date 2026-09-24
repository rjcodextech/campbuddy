<?php

namespace App\Http\Requests;

use App\Support\SvgGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreMediaAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120', function (string $attribute, mixed $value, \Closure $fail) {
                // Same rule as event logos: an SVG can carry <script>, and files here
                // are served from CampBuddy's own origin.
                if ($value instanceof UploadedFile
                    && $value->guessExtension() === 'svg'
                    && ! SvgGuard::isSafe((string) file_get_contents($value->getRealPath()))) {
                    $fail("That SVG contains scripts or embedded content, which CampBuddy won't host. Export it as a plain SVG, or use a PNG.");
                }
            }],
        ];
    }
}
