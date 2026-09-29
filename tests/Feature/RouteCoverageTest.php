<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * No route without a test. Every route of the app must be named in some test
 * (route('name') / 'name') or have its address written in one. GET pages under
 * /admin, /manager and /event/{event} also count as covered by
 * EveryPageAndAccessTest, which opens each of them and checks who may.
 * A new route that nothing tests fails this.
 */
class RouteCoverageTest extends TestCase
{
    public function test_every_route_is_exercised_by_a_test(): void
    {
        $tests = collect(File::allFiles(base_path('tests')))
            ->filter(fn ($file) => in_array($file->getExtension(), ['php', 'mjs'], true) && $file->getFilename() !== 'RouteCoverageTest.php')
            ->map(fn ($file) => $file->getContents())
            ->implode("\n");

        $untested = collect(Router::getRoutes()->getRoutes())
            ->reject(fn (Route $route) => str_starts_with($route->uri(), '_') || str_starts_with($route->uri(), 'up')
                || str_starts_with((string) $route->getName(), 'ignition.') || str_starts_with((string) $route->getName(), 'sanctum.'))
            ->reject(fn (Route $route) => $this->pageSweptByEveryPageTest($route))
            ->reject(fn (Route $route) => ($route->getName() && str_contains($tests, "'".$route->getName()."'"))
                || str_contains($tests, '/'.$this->literalPrefix($route)))
            ->map(fn (Route $route) => implode('|', $route->methods()).' '.$route->uri().' ('.($route->getName() ?? 'unnamed').')')
            ->values()
            ->all();

        $this->assertSame([], $untested, 'routes that no test exercises');
    }

    private function pageSweptByEveryPageTest(Route $route): bool
    {
        return in_array('GET', $route->methods(), true)
            && (str_starts_with($route->uri(), 'admin') || str_starts_with($route->uri(), 'manager') || str_starts_with($route->uri(), 'event/{event}'));
    }

    /** "admin/events/{event}/roster" → "admin/events/"; a route with no parameter → its whole address. */
    private function literalPrefix(Route $route): string
    {
        $uri = $route->uri();
        $cut = strpos($uri, '{');

        return $cut === false ? $uri : substr($uri, 0, $cut);
    }
}
