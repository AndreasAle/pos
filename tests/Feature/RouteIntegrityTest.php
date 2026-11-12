<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteIntegrityTest extends TestCase
{
    /** Rute yang menunjuk method controller yang tidak ada akan 500 saat dibuka; harus tertangkap di tes. */
    public function test_every_controller_route_points_to_an_existing_method(): void
    {
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action);
            if (class_exists($class) && ! method_exists($class, $method)) {
                $missing[] = $route->uri().' -> '.class_basename($class).'::'.$method;
            }
        }

        $this->assertSame([], $missing, "Rute ke method yang tidak ada:\n".implode("\n", $missing));
    }
}
