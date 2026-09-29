<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The deal-leads CSV, the same for one event's leads and for a default
 * deal's leads across every event.
 */
class DealLeadCsv
{
    /** @param  Collection<int, \App\Models\OfferLead>  $leads */
    public static function download(Collection $leads, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($leads) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Deal', 'Event', 'Name', 'Company', 'Email', 'Mobile', 'Options', 'Submitted at']);

            foreach ($leads as $lead) {
                fputcsv($handle, [
                    self::safe($lead->offer?->displayName()),
                    self::safe($lead->event?->display_name),
                    self::safe($lead->name),
                    self::safe($lead->company),
                    self::safe($lead->email),
                    self::safe($lead->mobile),
                    self::safe(implode('; ', (array) $lead->choices)),
                    $lead->created_at->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * These cells hold whatever an anonymous visitor typed, and a spreadsheet
     * runs a cell that starts with = + - @ (or a tab / carriage return) as a
     * formula — so an admin opening the export could be running a stranger's
     * formula. A leading apostrophe makes it plain text. Phone-style values
     * ("+91 98765 43210") are just digits and are left alone.
     */
    public static function safe(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $looksLikeFormula = preg_match('/^[=+\-@\t\r]/', $value) === 1;
        $isPlainNumber = preg_match('/^[+\-]?[0-9][0-9 ().\-]*$/', $value) === 1;

        return $looksLikeFormula && ! $isPlainNumber ? "'".$value : $value;
    }
}
