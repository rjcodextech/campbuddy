<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaAssetRequest;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The shared Media Library (admin-only) — images uploaded here are
 * pickable as a deal's or a Free Steal's logo. Each image shows what uses
 * it; one nothing uses can be deleted.
 */
class MediaController extends Controller
{
    public const FILTERS = ['all' => 'All', 'used' => 'In use', 'unused' => 'Not used'];

    public function index(Request $request): View
    {
        $show = array_key_exists($request->query('show'), self::FILTERS) ? $request->query('show') : 'all';

        $assets = MediaAsset::query()
            ->when($show === 'used', fn ($q) => $q->used())
            ->when($show === 'unused', fn ($q) => $q->unused())
            ->with(['offers:id,media_asset_id,event_id,brand,title', 'offers.event:id,display_name', 'freeSteals:id,media_asset_id,name'])
            ->latest()->paginate(24)->withQueryString();

        return view('admin.media.index', [
            'assets' => $assets,
            'show' => $show,
            'counts' => ['all' => MediaAsset::count(), 'used' => MediaAsset::used()->count(), 'unused' => MediaAsset::unused()->count()],
        ]);
    }

    public function store(StoreMediaAssetRequest $request): RedirectResponse
    {
        MediaAsset::store($request->file('file'), $request->user()->id);

        return redirect()->route('admin.media.index')->with('status', 'Image uploaded.');
    }

    /** Deletes an image nothing uses: the row, then the file. One in use is refused. */
    public function destroy(MediaAsset $media): RedirectResponse
    {
        $deleted = DB::transaction(function () use ($media) {
            $asset = MediaAsset::lockForUpdate()->find($media->id);
            if (! $asset || $asset->isUsed()) {
                return false;
            }
            $asset->delete();

            return true;
        });

        if (! $deleted) {
            return back()->with('error', "“{$media->filename}” is in use, so it can't be deleted. Pick another logo where it's used first.");
        }

        Storage::disk($media->disk)->delete($media->path);

        return back()->with('status', 'Image deleted.');
    }
}
