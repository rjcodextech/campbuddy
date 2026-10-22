<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\EventTime;
use App\Support\NativePush;
use App\Support\SocialKit;
use App\Support\SocialPlan;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Tells the organizers who asked for it ("Remind me on my phone when a post is
 * due", Social media studio) which posts to share today, on their phone: the
 * CampBuddy app, signed in as an admin or event manager. One push per post per
 * day (SocialPlan's tentative days, at the venue), never twice — runs every
 * morning from the schedule (routes/console.php).
 *
 *   php artisan campbuddy:post-reminders --dry-run   # what is due today, nothing sent
 */
class SendPostRemindersCommand extends Command
{
    protected $signature = 'campbuddy:post-reminders
        {--dry-run : List what is due today and send nothing}
        {--any-time : Send now, not only in the venue\'s morning (9–11)}';

    protected $description = 'Push today\'s social posts to the organizers who turned on post reminders';

    public function handle(): int
    {
        $now = CarbonImmutable::now();
        $due = 0;
        $sent = 0;

        foreach (Event::where('status', 'active')->where('is_visible', true)->orderBy('id')->get() as $event) {
            // In the venue's morning only: a reminder at 5 a.m. helps nobody.
            $hour = (int) $now->setTimezone(EventTime::zone($event))->format('G');
            if (! $this->option('any-time') && ! $this->option('dry-run') && ($hour < 9 || $hour >= 11)) {
                continue;
            }
            $keys = SocialPlan::dueToday($event, $now);
            if ($keys === []) {
                continue;
            }
            $labels = collect(SocialKit::posts($event, $now))->keyBy('key');
            foreach ($keys as $key) {
                $post = $labels->get($key);
                if ($post === null) {
                    continue;
                }
                $due++;
                $this->line("{$event->display_name}: {$post['label']}");
                if ($this->option('dry-run')) {
                    continue;
                }
                // Once per post and day, however often this runs.
                if (! Cache::add("post-reminder:{$event->id}:{$key}:{$now->toDateString()}", true, $now->addDays(2))) {
                    continue;
                }
                $sent += NativePush::toOrganizers($event, [
                    'title' => "Post today: {$post['label']}",
                    'body' => "{$event->display_name} · Open Social media to download it with its caption.",
                ], 'post_reminder', 'social', 86400, socialReminders: true);
            }
        }

        $this->info($this->option('dry-run') ? "{$due} posts due today." : "{$due} posts due today; reminders handed to {$sent} phones.");

        return self::SUCCESS;
    }
}
