<?php

use App\Support\FreeSteals;
use Illuminate\Database\Migrations\Migration;

/**
 * The Free Steals CampBuddy ships with, every field written out here (from
 * mockups/campbuddy-free-steals.csv; links and maker links checked on the web
 * on 29 Sep 2026). Runs with the deploy's `migrate` / `campbuddy:doctor`
 * (FreeSteals::install): never adds one twice (matched on its link), never
 * touches one an admin has since edited.
 *
 * Order: tools made by individuals first (Gaurav Tiwari, Abhishek Deshpande,
 * Nitin Prakash, Hardeep Asrani), then the companies' — small makers have no
 * marketing team. The 12 marked "Initial 12" start switched on; 4 wait.
 *
 * maker_links: the maker's own addresses, one per line. When one of them is
 * on an event's Attendees page, that event says "Made by someone at this
 * WordCamp" (FreeSteal::forEvent). People: WordPress.org, GitHub, website, X.
 * Companies: WordPress.org and GitHub only — their website is on many
 * employees' profiles, which would say "made by someone here" too often.
 *
 * Skipped in the test suite, whose tests start from an empty list (tests read
 * steals() from this file).
 */
return new class extends Migration
{
    private const LUBUS = "https://profiles.wordpress.org/lubus/\nhttps://github.com/lubusIN";

    private const MULTIDOTS = "https://profiles.wordpress.org/multidots/\nhttps://github.com/multidots";

    private const RTCAMP = "https://profiles.wordpress.org/rtcamp/\nhttps://github.com/rtCamp";

    private const AUTOMATTIC = "https://profiles.wordpress.org/automattic/\nhttps://github.com/Automattic";

    /** @return list<array<string, mixed>> */
    public function steals(): array
    {
        return [
            [
                'name' => 'WordPress Skills',
                'description' => 'A large collection of reusable AI-agent skills covering WordPress development, Gutenberg, plugins, themes, WP-CLI, SEO and more.',
                'maker' => 'Gaurav Tiwari / Gatilab',
                'maker_links' => "https://profiles.wordpress.org/gauravtiwari/\nhttps://github.com/wpgaurav\nhttps://gauravtiwari.org\nhttps://x.com/wpgaurav",
                'category' => 'AI / Developer Tools',
                'url' => 'https://github.com/wpgaurav/WordPress-skills',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'The Off Switch',
                'description' => 'Disable optional WordPress features, scripts and background behaviour that a site does not need.',
                'maker' => 'Abhishek Deshpande',
                'maker_links' => "https://profiles.wordpress.org/deshabhishek007/\nhttps://profiles.wordpress.org/fitehal/\nhttps://github.com/deshabhishek007\nhttps://whoisabhi.com\nhttps://x.com/fitehal",
                'category' => 'Performance / Utilities',
                // WordPress.org still keeps the plugin's first slug (it was "WP Avoid Slow").
                'url' => 'https://wordpress.org/plugins/wp-avoid-slow/',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Thank You Page for WooCommerce',
                'description' => 'Redirect customers to a custom Thank You page or URL after a successful WooCommerce order.',
                'maker' => 'Nitin Prakash',
                'maker_links' => "https://profiles.wordpress.org/nitin247/\nhttps://github.com/nitinprakash\nhttps://nitin247.com",
                'category' => 'WooCommerce',
                'url' => 'https://github.com/nitinprakash/wc-thanks-redirect',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Blocks Export Import',
                'description' => 'Export Gutenberg blocks as JSON and import them into another WordPress site for reuse or backup.',
                'maker' => 'Hardeep Asrani / ThemeIsle',
                'maker_links' => "https://profiles.wordpress.org/hardeepasrani/\nhttps://github.com/HardeepAsrani\nhttps://hardeepasrani.com\nhttps://x.com/HardeepAsrani",
                'category' => 'Gutenberg / Utilities',
                // The plugin's own page (the CSV had the whole Otter Blocks repo).
                'url' => 'https://wordpress.org/plugins/blocks-export-import/',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Visual Blueprint Builder',
                'description' => 'Build WordPress Playground blueprint.json files visually using blocks, then preview, copy or download the generated blueprint.',
                'maker' => 'Lubus',
                'maker_links' => self::LUBUS,
                'category' => 'Developer Tools / Playground',
                'url' => 'https://github.com/lubusIN/visual-blueprint-builder',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'Multidots Passkey Login',
                'description' => 'Add passwordless WordPress authentication using Face ID, Touch ID, security keys and passkeys.',
                'maker' => 'Multidots',
                'maker_links' => self::MULTIDOTS,
                'category' => 'Security / Authentication',
                'url' => 'https://github.com/multidots/multidots-passkey-login',
                'is_active' => true,
                'is_featured' => true,
            ],
            [
                'name' => 'WordPress Studio',
                'description' => 'Free desktop app for creating and managing local WordPress sites, testing plugins and themes, and sharing previews.',
                'maker' => 'Automattic',
                'maker_links' => self::AUTOMATTIC,
                'category' => 'Local Development',
                'url' => 'https://github.com/Automattic/studio',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'GoDAM',
                'description' => 'Organize the WordPress Media Library with folders and improved workflows for images, audio, video and other assets.',
                'maker' => 'rtCamp',
                'maker_links' => self::RTCAMP,
                'category' => 'Media / Utilities',
                'url' => 'https://github.com/rtCamp/godam',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'BlaBlaBlocks Tabs Block',
                'description' => 'Add clean, responsive tabbed content to WordPress using the native Block Editor.',
                'maker' => 'Lubus',
                'maker_links' => self::LUBUS,
                'category' => 'Gutenberg / Blocks',
                'url' => 'https://github.com/lubusIN/blablablocks-tabs-block',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'ActivityPub',
                'description' => 'Connect a WordPress site to the Fediverse so people can follow and interact with its content through compatible platforms.',
                'maker' => 'Automattic',
                'maker_links' => self::AUTOMATTIC,
                'category' => 'Publishing / Social',
                'url' => 'https://github.com/Automattic/wordpress-activitypub',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Better By Default',
                'description' => 'Improve a fresh WordPress installation with admin cleanup, security options, performance tweaks and useful defaults.',
                'maker' => 'Multidots',
                'maker_links' => self::MULTIDOTS,
                'category' => 'Utilities / Performance',
                'url' => 'https://github.com/multidots/better-by-default',
                'is_active' => true,
                'is_featured' => false,
            ],
            [
                'name' => 'Co-Authors Plus',
                'description' => 'Assign multiple authors to content and create guest-author bylines without requiring separate WordPress accounts.',
                'maker' => 'Automattic',
                'maker_links' => self::AUTOMATTIC,
                'category' => 'Content / Publishing',
                'url' => 'https://github.com/Automattic/co-authors-plus',
                'is_active' => true,
                'is_featured' => false,
            ],
            // In the collection, switched off for now: an admin can swap them in.
            [
                'name' => 'Login with Google',
                'description' => 'Let users register and sign in to WordPress with their Google account, including domain-based access controls.',
                'maker' => 'rtCamp',
                'maker_links' => self::RTCAMP,
                'category' => 'Security / Authentication',
                'url' => 'https://github.com/rtCamp/login-with-google',
                'is_active' => false,
                'is_featured' => false,
            ],
            [
                'name' => 'Nginx Helper',
                'description' => 'Automatically purge Nginx or Redis-based page caches when WordPress content changes.',
                'maker' => 'rtCamp',
                'maker_links' => self::RTCAMP,
                'category' => 'Performance / Developer Tools',
                'url' => 'https://github.com/rtCamp/nginx-helper',
                'is_active' => false,
                'is_featured' => false,
            ],
            [
                'name' => 'WP Super Cache',
                'description' => 'Improve WordPress performance by generating static HTML pages instead of processing every visit through PHP.',
                'maker' => 'Automattic',
                'maker_links' => self::AUTOMATTIC,
                'category' => 'Performance',
                'url' => 'https://github.com/Automattic/wp-super-cache',
                'is_active' => false,
                'is_featured' => false,
            ],
            [
                'name' => 'WP Job Manager',
                'description' => 'Add searchable job listings, frontend submissions and employer-management functionality to WordPress.',
                'maker' => 'Automattic',
                'maker_links' => self::AUTOMATTIC,
                'category' => 'Utilities / Community',
                'url' => 'https://github.com/Automattic/WP-Job-Manager',
                'is_active' => false,
                'is_featured' => false,
            ],
        ];
    }

    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        FreeSteals::install($this->steals());
    }

    public function down(): void
    {
        // Left in place: by now an admin may have edited them.
    }
};
