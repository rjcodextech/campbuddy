<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaAssetRequest;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The shared Media Library (admin-only) — images uploaded here are
 * pickable from any form that references a MediaAsset (currently Offers,
 * §3.7/§9).
 */
class MediaController extends Controller
{
    public function index(): View
    {
        $assets = MediaAsset::latest()->paginate(24);

        return view('admin.media.index', compact('assets'));
    }

    public function store(StoreMediaAssetRequest $request): RedirectResponse
    {
        MediaAsset::store($request->file('file'), $request->user()->id);

        return redirect()->route('admin.media.index')->with('status', 'Image uploaded.');
    }
}
