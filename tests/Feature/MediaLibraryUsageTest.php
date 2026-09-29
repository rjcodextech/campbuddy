<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FreeSteal;
use App\Models\MediaAsset;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin → Media Library shows what uses each image (deals, Free Steals),
 * filters on it, and deletes only an image nothing uses.
 */
class MediaLibraryUsageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->admin = User::factory()->create();
    }

    private function asset(string $file): MediaAsset
    {
        Storage::disk('public')->put('media-library/2026/09/'.$file, 'png');

        return MediaAsset::create(['disk' => 'public', 'path' => 'media-library/2026/09/'.$file, 'filename' => $file, 'mime_type' => 'image/png', 'size' => 3]);
    }

    /** @return array{MediaAsset, MediaAsset, MediaAsset} a deal logo, a Free Steal logo, an unused one */
    private function library(): array
    {
        $event = Event::create(['slug' => 'jaipur', 'display_name' => 'WordCamp Jaipur', 'source_site_url' => 'https://jaipur.wordcamp.org/2026', 'status' => 'active', 'is_visible' => true]);
        $deal = $this->asset('hostinger.png');
        $steal = $this->asset('godam.png');
        $unused = $this->asset('old.png');

        Offer::create(['event_id' => $event->id, 'brand' => 'Hostinger', 'title' => '20% off', 'description' => 'd', 'url' => 'https://a.example', 'is_active' => true, 'media_asset_id' => $deal->id]);
        Offer::create(['event_id' => null, 'countries' => ['IN'], 'brand' => 'Hostinger', 'title' => 'Default', 'description' => 'd', 'url' => 'https://b.example', 'is_active' => true, 'media_asset_id' => $deal->id]);
        FreeSteal::create(['name' => 'GoDAM', 'description' => 'Folders.', 'maker' => 'rtCamp', 'category' => 'Media', 'url' => 'https://github.com/rtCamp/godam', 'media_asset_id' => $steal->id]);

        return [$deal, $steal, $unused];
    }

    public function test_each_image_shows_where_it_is_used_and_only_unused_ones_can_be_deleted(): void
    {
        [$deal, $steal, $unused] = $this->library();

        $html = $this->actingAs($this->admin)->get(route('admin.media.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Hostinger (deal, WordCamp Jaipur), Hostinger (deal, default)', $html);
        $this->assertStringContainsString('GoDAM (Free Steal)', $html);
        $this->assertSame(2, substr_count($html, 'In use ·'));
        $this->assertStringContainsString('Not used', $html);
        $this->assertStringContainsString(route('admin.media.destroy', $unused), $html);
        $this->assertStringNotContainsString(route('admin.media.destroy', $deal), $html);
        $this->assertStringNotContainsString(route('admin.media.destroy', $steal), $html);
    }

    public function test_the_filter_shows_in_use_or_not_used(): void
    {
        $this->library();

        $this->actingAs($this->admin)->get(route('admin.media.index', ['show' => 'used']))->assertOk()
            ->assertSee('hostinger.png')->assertSee('godam.png')->assertDontSee('old.png')->assertSee('Not used (1)');
        $this->actingAs($this->admin)->get(route('admin.media.index', ['show' => 'unused']))->assertOk()
            ->assertSee('old.png')->assertDontSee('hostinger.png')->assertDontSee('godam.png');
        $this->actingAs($this->admin)->get(route('admin.media.index', ['show' => 'nonsense']))->assertOk()
            ->assertSee('old.png')->assertSee('godam.png');
    }

    public function test_an_unused_image_is_deleted_with_its_file(): void
    {
        [, , $unused] = $this->library();

        $this->actingAs($this->admin)->delete(route('admin.media.destroy', $unused))->assertRedirect()->assertSessionHas('status', 'Image deleted.');

        $this->assertModelMissing($unused);
        Storage::disk('public')->assertMissing($unused->path);
    }

    public function test_an_image_in_use_is_never_deleted(): void
    {
        [$deal, $steal] = $this->library();

        foreach ([$deal, $steal] as $asset) {
            $this->actingAs($this->admin)->delete(route('admin.media.destroy', $asset))->assertRedirect()->assertSessionHas('error');

            $this->assertModelExists($asset);
            Storage::disk('public')->assertExists($asset->path);
        }
        $this->assertSame($steal->id, FreeSteal::sole()->media_asset_id);
    }

    public function test_guests_cannot_delete(): void
    {
        [, , $unused] = $this->library();

        $this->delete(route('admin.media.destroy', $unused))->assertRedirect('/admin/login');
        $this->assertModelExists($unused);
    }
}
