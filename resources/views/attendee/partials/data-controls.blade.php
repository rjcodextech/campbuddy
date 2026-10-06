{{-- "Your data" export/clear card — buttons are wired by data-controls.js.
     The PDF is made on the phone (export-pdf.js); the JSON is the raw dump. --}}
<div class="card">
    <p style="font-weight:700;margin:0 0 4px">Your data</p>
    <p class="footer-note" style="text-align:left;margin:0 0 12px">Everything you've entered stays on this device. Save it as a PDF or clear it any time.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn btn--outline btn--compact" id="dc-export"><x-attendee.line-icon name="download" /> Save as PDF</button>
        <button type="button" class="btn btn--outline btn--danger btn--compact" id="dc-clear">Clear my data</button>
    </div>
    <p class="footer-note" style="text-align:left;margin:10px 0 0"><button type="button" class="link-button" id="dc-export-json">Download the raw data (JSON)</button></p>
    <script type="application/json" id="dc-quests">{!! json_encode((object) \App\Models\Quest::where(fn ($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))->pluck('title', 'id')->all(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
</div>
