<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Http\Request;

/** Saving an event's brand colours (Social media page), for the admin and managers alike. */
class SocialColors
{
    /** @return array<string, string> the colours saved */
    public static function save(Request $request, Event $event): array
    {
        $rule = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $colors = array_map('strtolower', $request->validate([
            'primary' => $rule,
            'secondary' => $rule,
            'ink' => $rule,
            'paper' => $rule,
        ]));

        $event->forceFill(['brand_colors' => $colors])->save();

        return $colors;
    }
}
