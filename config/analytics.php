<?php

/*
|--------------------------------------------------------------------------
| Google Analytics 4 — what the property needs to show every event
|--------------------------------------------------------------------------
| GA4 collects every event parameter, but a parameter only appears in
| reports once it's registered as a custom dimension (text) or custom metric
| (numbers you sum/average). `php artisan campbuddy:ga-setup` registers
| everything below through the GA Admin API — see spec §22.4.
|
| Kept in step with the EVENTS allowlist in resources/js/attendee/analytics.js
| by tests/Feature/AnalyticsRegistryTest.php: a param sent from the app but
| missing here fails the build, so nothing is ever silently unreportable.
|
| Standard GA4 properties allow 50 event-scoped custom dimensions and 50
| custom metrics.
*/

return [

    // GA4 property ID (a number, e.g. 412345678 — Admin → Property details),
    // and a Google Cloud service account JSON key that has the "Editor" role
    // on that property. Only needed to run campbuddy:ga-setup.
    'property_id' => env('GA_PROPERTY_ID'),
    'credentials' => env('GA_CREDENTIALS_PATH'),

    'dimensions' => [
        // Every hit
        'event_slug' => 'Which WordCamp the page belongs to',
        'display_mode' => 'standalone (installed app) or browser',
        'page_type' => 'Which screen: home, my_day, quest, guide, picker…',
        'event_phase' => 'Before, during or after the event',
        'is_online' => 'Whether the device was online when the page opened',

        // Navigation, install, guide, home
        'tab' => 'Bottom-nav or Explore tab',
        'platform' => 'Install prompt kind: native, ios, android_firefox, in_app_browser',
        'outcome' => 'Install prompt outcome',
        'via' => 'How something was opened: auto, button, header, fallback',
        'surface' => 'Where an action happened: home, explore, topbar, picker…',
        'target' => 'Which link was tapped',
        'section' => 'Guide section',
        'term' => 'Glossary term opened',
        'question' => 'FAQ question opened',

        // My Day
        'view' => 'Full or My schedule',
        'filter_type' => 'Schedule filter kind: day, track, type, topic',
        'filter_value' => 'Schedule filter value',
        'session_id' => 'Session ID on the WordCamp site',
        'session_title' => 'Session title',
        'overlap' => 'Saved session overlaps another saved one',
        'link_type' => 'Kind of link: slides, video, wporg, linkedin…',
        'result' => 'Reminder offer result',

        // Quest, Contribute
        'quest_id' => 'Quest ID',
        'quest_title' => 'Quest title',
        'quest_group' => 'things_to_do or checklist',
        'destination' => 'Where a quest button led',
        'answers' => 'Contribute answers (the chosen work kinds)',
        'team_id' => 'Contributor team ID',
        'team_name' => 'Contributor team name',
        'source' => 'Contribute list: matches or all',

        // Explore, deals
        'sponsor_name' => 'Sponsor opened',
        'link_domain' => 'Website domain opened (never the full address)',
        'offer_id' => 'Deal ID',
        'offer_title' => 'Deal title',
        'lead_capture' => 'Deal asks for contact details',

        // Discovery
        'identity' => 'How someone appears in discovery: attendee_list, typed_name, anonymous',

        // Camp Card
        'layout' => 'Camp Card design',
        'method' => 'Share method',
        'content_type' => 'What was shared',
        'item_id' => 'Shared item',
        'action' => 'Camp Card export step that failed',

        // Health
        'description' => 'Error type and place',
        'endpoint' => 'API endpoint that failed',
        'status' => 'HTTP status of a failed API call',
        'metric_name' => 'Web Vitals metric: LCP, CLS, INP',
        'metric_rating' => 'Web Vitals rating: good, needs_improvement, poor',
    ],

    'metrics' => [
        'query_length' => 'Schedule search length (characters)',
        'results_count' => 'Schedule search results',
        'answer_count' => 'Contribute answers chosen',
        'tag_count' => 'Discovery interest tags chosen',
        'metric_value' => 'Web Vitals value (ms, or unitless for CLS)',
    ],

    // Marked as key events (GA4's "conversions"): the outcomes that mean
    // CampBuddy did its job.
    'key_events' => [
        'session_save',
        'discovery_join',
        'generate_lead',
        'install_complete',
        'camp_card_download',
    ],

];
