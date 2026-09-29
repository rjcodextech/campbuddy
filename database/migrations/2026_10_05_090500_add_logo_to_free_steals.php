<?php

use App\Support\FreeSteals;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Free Steal can show its own logo, picked from the Media Library like a
 * deal's. Without one the card keeps its category icon (FreeSteal::icon).
 *
 * The shipped steals get their logos here (files in public/media/free-steals/,
 * 256×256: the plugin's WordPress.org icon, else the maker's GitHub picture or
 * the app's own icon), plus AcrossAI Pro, which an admin added on the live
 * site (its AcrossAI WordPress.org icon). Only a steal with no logo yet gets one, so a logo an
 * admin picked stays. WordPress Skills has none on purpose: the only picture
 * is its maker's face, so it keeps its icon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('free_steals', function (Blueprint $table) {
            $table->foreignId('media_asset_id')->nullable()->after('category')->constrained('media_assets')->nullOnDelete();
        });

        if (app()->runningUnitTests()) {
            return;
        }

        FreeSteals::logos($this->logos());
    }

    public function down(): void
    {
        // The copied logos stay in the Media Library, which never deletes.
        Schema::table('free_steals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_asset_id');
        });
    }

    /** @return array<string, string> Each shipped steal's link => its logo file. */
    public function logos(): array
    {
        return [
            'https://wordpress.org/plugins/wp-avoid-slow/' => 'the-off-switch.png',
            'https://github.com/nitinprakash/wc-thanks-redirect' => 'thank-you-page-for-woocommerce.png',
            'https://wordpress.org/plugins/blocks-export-import/' => 'blocks-export-import.png',
            'https://github.com/lubusIN/visual-blueprint-builder' => 'visual-blueprint-builder.png',
            'https://github.com/multidots/multidots-passkey-login' => 'multidots-passkey-login.png',
            'https://github.com/Automattic/studio' => 'wordpress-studio.png',
            'https://github.com/rtCamp/godam' => 'godam.png',
            'https://github.com/lubusIN/blablablocks-tabs-block' => 'blablablocks-tabs-block.png',
            'https://github.com/Automattic/wordpress-activitypub' => 'activitypub.png',
            'https://github.com/multidots/better-by-default' => 'better-by-default.png',
            'https://github.com/Automattic/co-authors-plus' => 'co-authors-plus.png',
            'https://github.com/rtCamp/login-with-google' => 'login-with-google.png',
            'https://github.com/rtCamp/nginx-helper' => 'nginx-helper.png',
            'https://github.com/Automattic/wp-super-cache' => 'wp-super-cache.png',
            'https://github.com/Automattic/WP-Job-Manager' => 'wp-job-manager.png',
            // Added by an admin on campbuddy.club, not installed by 090100.
            'https://r.freemius.com/34763/10087717/' => 'acrossai-pro.png',
        ];
    }
};
