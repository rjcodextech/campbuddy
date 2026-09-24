{{--
    A fetch log's outcome as a badge. <x-fetch-status :status="$log->status" />
    ok → "ok" (green), partial → "partly" (amber: some lists kept their last
    good copy), anything else → red.
--}}
@props(['status'])

@php
    [$variant, $label] = match ($status) {
        'ok' => ['success', 'ok'],
        'partial' => ['warning', 'partly'],
        default => ['danger', $status ?: 'unknown'],
    };
@endphp

<x-badge :variant="$variant" {{ $attributes }}>{{ $label }}</x-badge>
