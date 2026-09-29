<?php

namespace App\Support;

use App\Models\FreeSteal;

/**
 * Installs the Free Steals CampBuddy ships with (Admin → Free Steals). The
 * steals themselves — every field, maker links included — are written out
 * in the migration that installs them (2026_10_05_090100_install_free_steals).
 * After that they are the admin's to edit, switch off or remove: installing
 * again never overwrites or re-creates one (it is matched on its link).
 */
class FreeSteals
{
    /**
     * Adds the given steals that aren't there yet, in the order given (after
     * any already there). Returns how many were added.
     *
     * @param  list<array<string, mixed>>  $steals  free_steals rows
     */
    public static function install(array $steals): int
    {
        $added = 0;
        $order = (int) (FreeSteal::max('sort_order') ?? 0);

        foreach ($steals as $steal) {
            if (FreeSteal::where('url', $steal['url'])->exists()) {
                continue;
            }

            FreeSteal::create($steal + ['is_active' => true, 'is_featured' => false, 'sort_order' => $order += 10]);
            $added++;
        }

        return $added;
    }
}
