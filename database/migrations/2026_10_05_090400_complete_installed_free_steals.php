<?php

use App\Models\FreeSteal;
use Illuminate\Database\Migrations\Migration;

/**
 * For a database where 2026_10_05_090100_install_free_steals ran before its
 * data was finished (29 Sep 2026): "Blocks Export Import" pointed at the whole
 * Otter Blocks repo, and no steal had maker links. This points it at the
 * plugin's own page and fills in maker links from that migration's steals() —
 * only where they are still empty, so an admin's edit is never overwritten.
 * On a fresh database it finds nothing to do.
 */
return new class extends Migration
{
    private const OLD_URL = 'https://github.com/Codeinwp/otter-blocks';

    private const NEW_URL = 'https://wordpress.org/plugins/blocks-export-import/';

    public function up(): void
    {
        if (! FreeSteal::where('url', self::NEW_URL)->exists()) {
            FreeSteal::where('url', self::OLD_URL)->where('name', 'Blocks Export Import')->update(['url' => self::NEW_URL]);
        }

        $steals = (require __DIR__.'/2026_10_05_090100_install_free_steals.php')->steals();

        foreach ($steals as $steal) {
            FreeSteal::where('url', $steal['url'])
                ->where(fn ($q) => $q->whereNull('maker_links')->orWhere('maker_links', ''))
                ->update(['maker_links' => $steal['maker_links']]);
        }
    }

    public function down(): void
    {
        // Left as it is: the corrected link and maker links are right.
    }
};
