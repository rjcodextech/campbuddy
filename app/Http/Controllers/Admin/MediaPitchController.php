<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Events → Media Partner Pitch.
 * Prepares, customizes, and exports (Email / PDF) media partnership pitches
 * for any WordCamp event on behalf of CampBuddy × WPSimplified.
 */
class MediaPitchController extends Controller
{
    public function index(Request $request): View
    {
        $events = rescue(
            fn () => Event::query()
                ->orderByRaw('starts_on IS NULL, starts_on DESC')
                ->get([
                    'id',
                    'display_name',
                    'slug',
                    'city',
                    'country',
                    'starts_on',
                    'ends_on',
                    'venue_name',
                    'source_site_url',
                ]),
            collect()
        );

        $selectedSlug = (string) $request->query('event', '');
        $selectedEvent = $events->firstWhere('slug', $selectedSlug)
            ?: $events->firstWhere('id', (int) $selectedSlug);

        // Pre-formatted default values
        $defaults = [
            'applicant_name' => 'Sunil Kumar Sharma',
            'applicant_role' => 'Lead — WPSimplified & CampBuddy',
            'applicant_email' => 'hello@wpsimplified.in',
            'applicant_phone' => '+91-7877994704',
            'event_name' => $selectedEvent?->display_name ?? 'WordCamp Delhi 2026',
            'event_dates' => $selectedEvent && $selectedEvent->starts_on
                ? ($selectedEvent->starts_on->format('j M Y').($selectedEvent->ends_on && ! $selectedEvent->ends_on->isSameDay($selectedEvent->starts_on) ? ' – '.$selectedEvent->ends_on->format('j M Y') : ''))
                : '12–13 December 2026',
            'event_venue' => $selectedEvent?->venue_name ?? 'Dr. Ambedkar International Centre, New Delhi',
            'event_email' => $selectedEvent?->slug ? $selectedEvent->slug.'@wordcamp.org' : 'delhi@wordcamp.org',
            'campbuddy' => [
                'website' => 'https://campbuddy.club/',
                'youtube' => 'https://www.youtube.com/@CampBuddyClub',
                'instagram' => 'https://www.instagram.com/campbuddyclub/',
                'x' => 'https://x.com/CampBuddyClub',
            ],
            'wpsimplified' => [
                'website' => 'https://wpsimplified.in/',
                'youtube' => 'https://www.youtube.com/@WPSimplifiedBySunil',
                'instagram' => 'https://www.instagram.com/wpsimplified/',
                'x' => 'https://x.com/iwpsimplified',
                'linkedin' => 'https://www.linkedin.com/company/wpsimplified/',
            ],
            'campbuddy_bio' => 'CampBuddy (by WPSimplified) is an intelligent, mobile companion app engineered to transform how attendees experience WordCamps and developer conferences. Built from the ground up with a "guidance over information" philosophy, CampBuddy answers the one question every first-timer and seasoned attendee asks standing in a crowded venue: "What should I do right now?" Rather than serving static timetables, CampBuddy acts as a live on-ground co-pilot. Key features include real-time multi-track schedule tracking with bookmarking and timely session alerts, digital networking Camp Cards equipped with dynamic LinkedIn QR codes for instant connections, and interest-based attendee matching to help community members find like-minded collaborators effortlessly. To elevate event participation, CampBuddy integrates gamified exploration quests that guide attendees through the venue and directly toward sponsor booths. For Contributor Day, it features a skill-based table navigator that demystifies open collaboration, helping newcomers confidently find suitable teams to contribute. Sponsors benefit from interactive, ticket-style deal showcases featuring frictionless in-app lead capture forms that deliver verified attendee leads with genuine return on investment. Designed with a strict privacy-first architecture, CampBuddy requires no account creation, no logins, and collects no personal data—storing attendee preferences locally on the device while operating reliably offline even across congested conference Wi-Fi networks. CampBuddy bridges the gap between feeling lost and feeling connected, empowering every attendee to make the most of their event experience.',
            'wpsimplified_bio' => 'WPSimplified is a premier WordPress education platform and community led by senior technical architect and community leader Sunil Kumar Sharma. Operating with an uncompromising "zero gatekeeping" ethos, the platform is on a mission to democratize modern web engineering and empower the next generation of developers, freelancers, and digital creators. WPSimplified breaks down complex enterprise web topics—custom plugin architecture, theme development, scalable systems design, API integrations, and performance optimization—into simple, practical, step-by-step learning paths that anyone can follow with complete confidence. Recognizing that language barriers often hold back exceptional talent, WPSimplified actively bridges the regional divide in tech by delivering high-impact bilingual education in Hindi and English. Through comprehensive video tutorials, hands-on architectural workshops, live developer masterclasses, and practical podcasts, the platform demystifies coding fundamentals and real-world engineering standards. Beyond technical training, WPSimplified offers actionable career coaching and freelancing guidance, helping learners transition from feeling stuck to building scalable digital solutions, launching successful agencies, and mastering client communication. Supported by a vibrant, rapidly growing community of passionate developers and creators across South Asia and worldwide, WPSimplified provides the continuous mentorship, real-world insights, and community support needed to thrive. At WPSimplified, WordPress development is made simple, practical, and profitable—ensuring top-tier technical education is accessible to everyone with zero barriers.',
            'sunil_bio' => 'Sunil Kumar Sharma is a Senior WordPress Developer and Technical Architect with over a decade of hands-on experience designing and engineering scalable web platforms, high-traffic digital ecosystems, and custom plugins. Deeply engaged in the WordPress community, Sunil has contributed extensively across South Asia as a speaker, organizer, volunteer, and mentor at numerous regional conferences, including WordCamp Kolhapur, WordCamp Nepal, WordCamp Bhopal, WordCamp Rajasthan, WordCamp Bengaluru, WordCamp Delhi, and WordCamp Asia. As the Lead at WPSimplified and CampBuddy, Sunil combines enterprise-level engineering acumen with an authentic commitment to grassroots impact. He is passionate about bridging the language divide in technology, dedicating his work to empowering non-metro developers and creators through regional language masterclasses, actionable content creation, and developer training. Sunil operates under a straightforward guiding philosophy: "zero gatekeeping—tech sabko sikhana is the vibe." His mission is ensuring that modern web development is never locked behind complex jargon or inaccessible barriers. Whether architecting scalable backend solutions, speaking on stage at tech conferences, or mentoring young developers taking their first steps in tech, Sunil brings a genuine community-first mindset to everything he builds. His focus remains steadfast on creating intelligent tools that enhance event experiences and producing practical educational resources that enable developers to build sustainable, scalable, and profitable digital careers.',
            'joint_bio' => 'CampBuddy | WPSimplified is a collaborative initiative led by WordPress architect Sunil Kumar Sharma, created to empower the global WordPress community through intelligent event tools and accessible developer education. By bringing together on-ground utility and high-value media reach, this partnership provides comprehensive support to conference organizers, attendees, and sponsors alike. CampBuddy functions as an intelligent mobile companion app for WordCamp attendees, turning busy schedules into real-time guidance. Operating with a strict "guidance over information" philosophy, it features live multi-track session navigation, digital networking Camp Cards with instant LinkedIn QR codes, gamified exploration quests, and guided Contributor Day table onboarding. Sponsors receive high-impact visibility through interactive deal showcases and zero-friction lead capture forms, all delivered within a 100% account-free, privacy-first, offline-ready architecture. Working hand in hand, WPSimplified serves as the educational powerhouse and media amplifier. With an active following of ambitious developers, freelancers, and creators, WPSimplified provides pre-event hype campaigns, on-site attendee reaction video coverage, and post-event retrospective articles published on official channels. Operating with a "zero gatekeeping" philosophy, WPSimplified bridges regional language divides, ensuring event takeaways and developer insights reach a wider, highly engaged South Asian audience. Together, CampBuddy and WPSimplified deliver unmatched attendee utility, memorable event energy, and measurable sponsor engagement.',
        ];

        return view('admin.media-pitch.index', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'defaults' => $defaults,
        ]);
    }

    public function print(Request $request): View
    {
        $data = [
            'applicant_name' => (string) $request->query('applicant_name', 'Sunil Kumar Sharma'),
            'applicant_role' => (string) $request->query('applicant_role', 'Lead — WPSimplified & CampBuddy'),
            'applicant_email' => (string) $request->query('applicant_email', 'hello@wpsimplified.in'),
            'applicant_phone' => (string) $request->query('applicant_phone', '+91-7877994704'),
            'show_phone' => filter_var($request->query('show_phone', true), FILTER_VALIDATE_BOOLEAN),
            'event_name' => (string) $request->query('event_name', 'WordCamp Delhi 2026'),
            'event_dates' => (string) $request->query('event_dates', '12–13 December 2026'),
            'event_venue' => (string) $request->query('event_venue', 'Dr. Ambedkar International Centre, New Delhi'),
            'event_email' => (string) $request->query('event_email', 'delhi@wordcamp.org'),
            'subject' => (string) $request->query('subject', 'Media Partnership Proposal: WordCamp Delhi 2026 × CampBuddy & WPSimplified'),
            'body' => (string) $request->query('body', ''),
            'bio' => (string) $request->query('bio', ''),
            'campbuddy_bio' => (string) $request->query('campbuddy_bio', ''),
            'wpsimplified_bio' => (string) $request->query('wpsimplified_bio', ''),
            'sunil_bio' => (string) $request->query('sunil_bio', ''),
        ];

        return view('admin.media-pitch.print', $data);
    }
}
