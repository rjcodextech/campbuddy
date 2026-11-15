<?php

namespace Tests\Feature\Adversarial;

use App\Models\AppRelease;
use App\Models\Event;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\SvgGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Hostile uploads to the panels: a program dressed as a picture, double
 * extensions, path traversal in the file name, oversized files, SVGs that
 * carry script, and APKs that are not APKs. Files are stored under generated
 * names with the extension read from their content, and an APK never lands
 * on the public disk.
 */
class PanelUploadTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\xff\xff?\x00\x05\xfe\x02\xfe\xa7\x35\x81\x84\x00\x00\x00\x00IEND\xaeB`\x82";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
    }

    /**
     * A real file on disk, so its type is sniffed from the content as in production
     * (UploadedFile::fake() takes the type from the name).
     */
    private function file(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    public function test_a_program_dressed_as_a_picture_is_refused(): void
    {
        $uploads = [
            $this->file('shell.php', '<?php system($_GET["c"]); ?>'),
            $this->file('logo.png', '<?php system($_GET["c"]); ?>'),
            $this->file('logo.png', '<html><body><script>alert(1)</script></body></html>'),
            $this->file('logo.png', "GIF89a/*\x00*/=alert(1);//"),
        ];

        foreach ($uploads as $file) {
            $this->post(route('admin.media.store'), ['file' => $file])->assertSessionHasErrors('file');
        }

        $this->assertSame(0, MediaAsset::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_double_extension_or_a_path_in_the_name_is_stored_under_a_generated_name(): void
    {
        foreach (['logo.php.png', '../../../public/index.php.png', '..\\..\\evil.png'] as $name) {
            $this->post(route('admin.media.store'), ['file' => $this->file($name, self::PNG)])->assertSessionHasNoErrors();
        }

        foreach (MediaAsset::all() as $asset) {
            $this->assertMatchesRegularExpression('#^media-library/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#', $asset->path);
        }
        foreach (Storage::disk('public')->allFiles() as $path) {
            $this->assertStringStartsWith('media-library/', $path);
            $this->assertStringNotContainsString('.php', $path);
        }

        // The name as typed is only ever shown as text.
        $this->get(route('admin.media.index'))->assertOk()->assertSee('index.php.png');
    }

    public function test_an_oversized_picture_is_refused(): void
    {
        $this->post(route('admin.media.store'), ['file' => UploadedFile::fake()->create('big.png', 5121, 'image/png')])->assertSessionHasErrors('file');
        $this->assertSame(0, MediaAsset::count());
    }

    public function test_svgs_that_carry_script_in_less_obvious_ways_are_refused(): void
    {
        $svgs = [
            'namespaced script' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:h="http://www.w3.org/1999/xhtml"><h:script>alert(1)</h:script></svg>',
            'xslt stylesheet' => '<?xml version="1.0"?><?xml-stylesheet type="text/xsl" href="#s"?><svg xmlns="http://www.w3.org/2000/svg"><xsl:stylesheet id="s" version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"/></svg>',
            'handler after a tab' => "<svg xmlns=\"http://www.w3.org/2000/svg\"\tonload=\"alert(1)\"></svg>",
            'entity-hidden link' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="&#x6A;avascript:alert(1)"><text>x</text></a></svg>',
            'external entity' => '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>',
        ];

        foreach ($svgs as $what => $svg) {
            $this->assertFalse(SvgGuard::isSafe($svg), "{$what} passed the guard");
            $this->post(route('admin.media.store'), ['file' => $this->file('logo.svg', $svg)])->assertSessionHasErrors('file');
        }

        $this->assertTrue(SvgGuard::isSafe('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><rect width="1" height="1"/></svg>'));
        $this->assertSame(0, MediaAsset::count());
    }

    public function test_an_event_logo_must_be_a_real_picture(): void
    {
        $event = Event::create(['slug' => 'wc-a', 'display_name' => 'A', 'source_site_url' => 'https://a.wordcamp.org/2026', 'status' => 'draft', 'is_visible' => false]);

        $this->post(route('admin.events.upload-branding', $event), ['logo' => $this->file('logo.png', '<?php echo 1; ?>')])->assertSessionHasErrors('logo');
        $this->post(route('admin.events.upload-branding', $event), ['logo' => $this->file('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:h="http://www.w3.org/1999/xhtml"><h:script>alert(1)</h:script></svg>')])
            ->assertSessionHasErrors('logo');

        $this->assertNull($event->fresh()->logo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function sendApk(string $bytes): TestResponse
    {
        $id = (string) Str::uuid();
        $this->call('POST', route('admin.mobile.releases.chunk'), [], [], [], ['HTTP_X_UPLOAD_ID' => $id, 'HTTP_X_CHUNK_INDEX' => '0', 'HTTP_ACCEPT' => 'application/json'], $bytes)->assertOk();

        return $this->postJson(route('admin.mobile.releases.store'), ['upload' => $id, 'sha256' => hash('sha256', $bytes)]);
    }

    public function test_a_file_that_is_not_an_apk_is_refused_with_a_reason_not_an_error(): void
    {
        $real = (string) file_get_contents(base_path('tests/Fixtures/apk/campbuddy-13.apk'));

        foreach ([self::PNG, substr($real, 0, intdiv(strlen($real), 2)), "PK\x05\x06".str_repeat("\xff", 18), str_repeat('A', 4096)] as $bytes) {
            $this->sendApk($bytes)->assertStatus(422)->assertJsonStructure(['message']);
        }

        $this->assertSame(0, AppRelease::count());
        $this->assertSame([], glob(Storage::disk('local')->path('app-releases/tmp').'/*') ?: [], 'nothing is left behind');
    }

    public function test_an_upload_id_can_not_point_outside_the_upload_folder(): void
    {
        foreach (['../../../../../../../../etc/passwd00', str_repeat('.', 36), 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaa/.'] as $id) {
            $this->call('POST', route('admin.mobile.releases.chunk'), [], [], [], ['HTTP_X_UPLOAD_ID' => $id, 'HTTP_X_CHUNK_INDEX' => '0', 'HTTP_ACCEPT' => 'application/json'], 'x')->assertStatus(422);
            $this->postJson(route('admin.mobile.releases.store'), ['upload' => str_pad(substr($id, 0, 36), 36, '.'), 'sha256' => str_repeat('a', 64)])->assertStatus(422);
        }
    }

    public function test_an_accepted_apk_is_kept_off_the_public_disk(): void
    {
        $this->sendApk((string) file_get_contents(base_path('tests/Fixtures/apk/campbuddy-13.apk')))->assertOk();

        $release = AppRelease::sole();
        Storage::disk('local')->assertExists($release->file);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertMatchesRegularExpression('#^app-releases/[a-z0-9._]+/\d+-[0-9a-f]{12}\.apk$#', $release->file);
    }
}
