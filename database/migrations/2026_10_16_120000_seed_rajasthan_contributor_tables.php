<?php

use App\Models\AttendeeRoster;
use App\Models\ContributorTable;
use App\Models\Event;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds Contributor Day tables and table leads for WordCamp Rajasthan 2026
 * scraped from https://rajasthan.wordcamp.org/2026/contributor-day-table-leads/.
 */
return new class extends Migration
{
    public function up(): void
    {
        $event = Event::where('slug', 'wordcamp-rajasthan-2026')->first();
        if (! $event) {
            return;
        }

        $tables = [
            [
                'team' => 'core',
                'title' => null,
                'track' => 'RIC Auditorium',
                'floor' => 'Ground Floor',
                'table_no' => '1',
                'lead_name' => 'Nimesh Agrawal',
                'search' => 'Nimesh Agrawal',
                'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/2026/09/Nimesh-Agarwal-150x150.jpg',
                'designation' => 'Senior Software Engineer at miniOrange',
                'bio' => 'Senior Software Engineer at miniOrange with 7+ years of experience building backend systems for enterprise solutions, specializing in WordPress, SSO, and security.',
                'url' => 'https://rajasthan.wordcamp.org/2026/speaker/nimesh-agrawal/',
                'note' => 'WordPress Core: bug fixes, patches, unit tests, and performance improvements.',
                'sort_order' => 1,
            ],
            [
                'team' => 'core',
                'title' => 'Core AI',
                'track' => 'RIC Auditorium',
                'floor' => 'Ground Floor',
                'table_no' => '2',
                'lead_name' => 'Gajendra Singh',
                'search' => 'Gajendra Singh',
                'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/2026/09/gajendra-150x150.png',
                'designation' => 'WordPress Plugin Developer at WPVibes',
                'bio' => 'WordPress Plugin Developer at WPVibes, working primarily with WordPress. He actively contributes to the WordPress Core and AI experiments.',
                'url' => 'https://rajasthan.wordcamp.org/2026/speaker/gajendra-singh/',
                'note' => 'Core AI: exploring AI capabilities, LLM integration, and experiments in WordPress Core.',
                'sort_order' => 2,
            ],
            [
                'team' => 'polyglots',
                'title' => null,
                'track' => 'RIC Auditorium',
                'floor' => 'Ground Floor',
                'table_no' => '3',
                'lead_name' => 'Nidhi Jain',
                'search' => 'Nidhi Jain',
                'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/2026/07/Nidhi-Jain-150x150.png',
                'designation' => 'WordPress Developer & Technical Support Specialist',
                'bio' => 'Seasoned WordPress Developer and Technical Support Specialist with a passion for innovation, regional language localization, and community building.',
                'url' => 'https://rajasthan.wordcamp.org/2026/speaker/nidhi-jain/',
                'note' => 'Polyglots: translating WordPress core, themes, and plugins into Hindi, Rajasthani, and regional languages.',
                'sort_order' => 3,
            ],
            [
                'team' => 'test',
                'title' => null,
                'track' => 'RIC Auditorium',
                'floor' => 'Ground Floor',
                'table_no' => '4',
                'lead_name' => 'Krupa Nanda',
                'search' => 'Krupa Nanda',
                'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/2026/09/Krupa-Nanda-scaled-e1789803916108-150x150.jpeg',
                'designation' => 'QA Engineer',
                'bio' => 'QA Engineer with 8+ years of experience testing and improving WordPress projects, verifying bug tickets, and quality assurance.',
                'url' => 'https://rajasthan.wordcamp.org/2026/speaker/krupa-nanda/',
                'note' => 'Testing: testing upcoming releases, verifying bug tickets, and quality assurance.',
                'sort_order' => 4,
            ],
            [
                'team' => 'design',
                'title' => 'Patterns',
                'track' => 'RIC Auditorium',
                'floor' => 'Ground Floor',
                'table_no' => '5',
                'lead_name' => 'Chetan Prajapati',
                'search' => 'Chetan Prajapati',
                'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/2026/09/Chetan-Prajapati-scaled-e1789726287876-150x150.png',
                'designation' => 'Developer and WordPress Contributor',
                'bio' => 'Dedicated WordPress enthusiast, versatile freelancer, and experienced WordPress developer with over eleven years of experience in custom themes and block patterns.',
                'url' => 'https://rajasthan.wordcamp.org/2026/speaker/chetan-prajapati/',
                'note' => 'Patterns & Design: building and submitting block patterns for the WordPress Pattern Directory.',
                'sort_order' => 5,
            ],
            [
                'team' => 'support',
                'title' => null,
                'track' => 'RIC Auditorium',
                'floor' => 'Ground Floor',
                'table_no' => '6',
                'lead_name' => 'Prathamesh Palve',
                'search' => 'Prathamesh Palve',
                'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/2026/08/pratfinalone-150x150.jpg',
                'designation' => 'Technical Lead, CampusPress',
                'bio' => 'Technical Lead at CampusPress, where he works with large-scale WordPress Multisite platforms, core troubleshooting, and supporting the community.',
                'url' => 'https://rajasthan.wordcamp.org/2026/speaker/prathamesh-palve/',
                'note' => 'Support: answering questions and troubleshooting on the official WordPress.org support forums.',
                'sort_order' => 6,
            ],
            [
                'team' => 'community',
                'title' => 'Community & Education',
                'track' => 'RIC Auditorium',
                'floor' => 'Ground Floor',
                'table_no' => '7',
                'lead_name' => 'Makarand G. Mane',
                'search' => 'Makarand',
                'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/2026/09/Makarand-mane-1-150x150.png',
                'designation' => 'Founder, WPGenius Solutions LLP',
                'bio' => 'Building with WordPress since 2011. Starting as a freelance developer, he now leads WPGenius Solutions and actively organizes meetups and campus initiatives.',
                'url' => 'https://rajasthan.wordcamp.org/2026/speaker/makarand-g-mane/',
                'note' => 'Community & Education: organizing meetups, campus connect, workshops, and educational programs.',
                'sort_order' => 7,
            ],
            [
                'team' => 'photography',
                'title' => 'Photos',
                'track' => 'RIC Auditorium',
                'floor' => 'Ground Floor',
                'table_no' => '8',
                'lead_name' => 'Mayank Kumar',
                'search' => 'Mayank Kumar',
                'avatar_url' => 'https://rajasthan.wordcamp.org/2026/files/2026/09/10-150x150.webp',
                'designation' => 'Founder of Montaki Technologies and Web Wallah',
                'bio' => 'Founder of Montaki Technologies and Web Wallah. He got into WordPress at 17, contributing CC0 photography and organizing meetups.',
                'url' => 'https://rajasthan.wordcamp.org/2026/speaker/mayank-kumar/',
                'note' => 'Photos: contributing free-to-use CC0 photos to the WordPress Photo Directory.',
                'sort_order' => 8,
            ],
        ];

        foreach ($tables as $data) {
            $roster = AttendeeRoster::where('event_id', $event->id)
                ->where('name', 'like', '%'.$data['search'].'%')
                ->first();

            ContributorTable::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'table_no' => $data['table_no'],
                ],
                [
                    'team' => $data['team'],
                    'title' => $data['title'],
                    'track' => $data['track'],
                    'floor' => $data['floor'],
                    'leads' => [
                        [
                            'name' => $data['lead_name'],
                            'roster_id' => $roster?->id,
                            'avatar_url' => $data['avatar_url'],
                            'designation' => $data['designation'],
                            'bio' => $data['bio'],
                            'url' => $data['url'],
                        ],
                    ],
                    'note' => $data['note'],
                    'sort_order' => $data['sort_order'],
                ]
            );
        }
    }

    public function down(): void
    {
        $event = Event::where('slug', 'wordcamp-rajasthan-2026')->first();
        if ($event) {
            ContributorTable::where('event_id', $event->id)->delete();
        }
    }
};
