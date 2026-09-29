<?php

namespace App\Support;

use App\Models\FreeSteal;

/**
 * The Free Steals CampBuddy ships with (from mockups/campbuddy-free-steals.csv).
 * Installed once by a migration; after that they are the admin's to edit,
 * switch off or remove (Admin → Free Steals) — installing again never
 * overwrites or re-creates one (it is matched on its link).
 *
 * Tools made by individuals come first (Gaurav Tiwari, Abhishek Deshpande,
 * Nitin Prakash, Hardeep Asrani), then the companies' — small makers have no
 * marketing team, and this is where they get found. The 12 marked
 * "Initial 12" start switched on; the rest wait.
 */
class FreeSteals
{
    /** @return list<array<string, mixed>> */
    public static function catalogue(): array
    {
        return [
            ['WordPress Skills', 'A large collection of reusable AI-agent skills covering WordPress development, Gutenberg, plugins, themes, WP-CLI, SEO and more.', 'Gaurav Tiwari / Gatilab', 'AI / Developer Tools', 'https://github.com/wpgaurav/WordPress-skills', true, true],
            ['The Off Switch', 'Disable optional WordPress features, scripts and background behaviour that a site does not need.', 'Abhishek Deshpande', 'Performance / Utilities', 'https://wordpress.org/plugins/wp-avoid-slow/', true, true],
            ['Thank You Page for WooCommerce', 'Redirect customers to a custom Thank You page or URL after a successful WooCommerce order.', 'Nitin Prakash', 'WooCommerce', 'https://github.com/nitinprakash/wc-thanks-redirect', true, false],
            ['Blocks Export Import', 'Export Gutenberg blocks as JSON and import them into another WordPress site for reuse or backup.', 'Hardeep Asrani / ThemeIsle', 'Gutenberg / Utilities', 'https://github.com/Codeinwp/otter-blocks', true, false],
            ['Visual Blueprint Builder', 'Build WordPress Playground blueprint.json files visually using blocks, then preview, copy or download the generated blueprint.', 'Lubus', 'Developer Tools / Playground', 'https://github.com/lubusIN/visual-blueprint-builder', true, true],
            ['Multidots Passkey Login', 'Add passwordless WordPress authentication using Face ID, Touch ID, security keys and passkeys.', 'Multidots', 'Security / Authentication', 'https://github.com/multidots/multidots-passkey-login', true, true],
            ['WordPress Studio', 'Free desktop app for creating and managing local WordPress sites, testing plugins and themes, and sharing previews.', 'Automattic', 'Local Development', 'https://github.com/Automattic/studio', true, false],
            ['GoDAM', 'Organize the WordPress Media Library with folders and improved workflows for images, audio, video and other assets.', 'rtCamp', 'Media / Utilities', 'https://github.com/rtCamp/godam', true, false],
            ['BlaBlaBlocks Tabs Block', 'Add clean, responsive tabbed content to WordPress using the native Block Editor.', 'Lubus', 'Gutenberg / Blocks', 'https://github.com/lubusIN/blablablocks-tabs-block', true, false],
            ['ActivityPub', 'Connect a WordPress site to the Fediverse so people can follow and interact with its content through compatible platforms.', 'Automattic', 'Publishing / Social', 'https://github.com/Automattic/wordpress-activitypub', true, false],
            ['Better By Default', 'Improve a fresh WordPress installation with admin cleanup, security options, performance tweaks and useful defaults.', 'Multidots', 'Utilities / Performance', 'https://github.com/multidots/better-by-default', true, false],
            ['Co-Authors Plus', 'Assign multiple authors to content and create guest-author bylines without requiring separate WordPress accounts.', 'Automattic', 'Content / Publishing', 'https://github.com/Automattic/co-authors-plus', true, false],
            // In the collection, switched off for now.
            ['Login with Google', 'Let users register and sign in to WordPress with their Google account, including domain-based access controls.', 'rtCamp', 'Security / Authentication', 'https://github.com/rtCamp/login-with-google', false, false],
            ['Nginx Helper', 'Automatically purge Nginx or Redis-based page caches when WordPress content changes.', 'rtCamp', 'Performance / Developer Tools', 'https://github.com/rtCamp/nginx-helper', false, false],
            ['WP Super Cache', 'Improve WordPress performance by generating static HTML pages instead of processing every visit through PHP.', 'Automattic', 'Performance', 'https://github.com/Automattic/wp-super-cache', false, false],
            ['WP Job Manager', 'Add searchable job listings, frontend submissions and employer-management functionality to WordPress.', 'Automattic', 'Utilities / Community', 'https://github.com/Automattic/WP-Job-Manager', false, false],
        ];
    }

    /** Adds the catalogue's steals that aren't there yet. Returns how many were added. */
    public static function install(): int
    {
        $added = 0;
        $order = (int) (FreeSteal::max('sort_order') ?? 0);

        foreach (self::catalogue() as [$name, $description, $maker, $category, $url, $active, $featured]) {
            if (FreeSteal::where('url', $url)->exists()) {
                continue;
            }

            FreeSteal::create([
                'name' => $name,
                'description' => $description,
                'maker' => $maker,
                'category' => $category,
                'url' => $url,
                'is_active' => $active,
                'is_featured' => $featured,
                'sort_order' => $order += 10,
            ]);
            $added++;
        }

        return $added;
    }
}
