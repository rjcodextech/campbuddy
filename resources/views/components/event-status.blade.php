{{-- Lifecycle status pill: draft → neutral, approved → info, active → success, archived → warning. --}}
@props(['status'])

<x-badge :variant="['draft' => 'neutral', 'approved' => 'info', 'active' => 'success', 'archived' => 'warning'][$status] ?? 'neutral'" {{ $attributes }}>{{ ucfirst($status) }}</x-badge>
