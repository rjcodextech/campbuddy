{{--
    Social media → one-click Publish (App\Support\SocialPublisher): the
    organizers' own Make.com / Zapier / n8n / Pipedream webhook, which posts to
    their LinkedIn, Facebook, Instagram or X. Optional — without it, Copy +
    Download + Share still work.
--}}
<form method="POST" action="{{ $webhookUrl }}">
    @csrf
    <x-card title="One-click Publish (optional)" :description="$webhookSet ? 'On: every post has a Publish button. It goes to '.\App\Support\SocialPublisher::masked($event).'.' : 'Off. Set it up once and every post gets a Publish button.'">
        <ol class="mb-4 list-decimal space-y-1 pl-5 text-sm text-muted">
            <li>In <strong>Make.com</strong> (or Zapier, n8n, Pipedream) create a scenario that starts with a <strong>Custom webhook</strong>, and copy its address.</li>
            <li>Add the steps that post to your pages: LinkedIn, Facebook, Instagram, X. Use <code>caption</code> for the text and <code>image_url</code> for the picture (also sent: <code>title</code>, <code>hashtags</code>, <code>kind</code>, <code>event.name</code>, <code>event.site</code>).</li>
            <li>Paste the webhook address below and save. Then press <strong>Publish</strong> on any post.</li>
        </ol>
        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1 basis-72">
                <label for="social-webhook" class="cb-label">Webhook address</label>
                <input id="social-webhook" name="webhook" type="url" maxlength="500" placeholder="https://hook.eu1.make.com/…" class="cb-input" autocomplete="off">
                <x-input-error :messages="$errors->get('webhook')" class="mt-1" />
            </div>
            <x-button size="sm">{{ $webhookSet ? 'Replace' : 'Save' }}</x-button>
            @if ($webhookSet)
                <x-button size="sm" variant="danger-outline" name="remove" value="1" formnovalidate>Remove</x-button>
            @endif
        </div>
        <p class="mt-2 text-xs text-muted">The address is kept encrypted on the server and never shown in the browser. Published images are saved on CampBuddy so the scenario can fetch them.</p>
    </x-card>
</form>
