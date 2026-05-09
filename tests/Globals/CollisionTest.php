<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Globals;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Vusys\LaravelSmarty\Exceptions\ReservedTemplateVariable;
use Vusys\LaravelSmarty\Tests\TestCase;

/**
 * Passing one of the five reserved auto-share keys ($auth, $request,
 * $session, $route, $errors) as view data is a programmer error —
 * silently letting user data win would mask typos and produce
 * confusing template output. The engine throws instead.
 *
 * The one exception is an `errors` key carrying a `ViewErrorBag`,
 * which Laravel's stock `ShareErrorsFromSession` middleware injects on
 * every request through the `web` group. That share is silently
 * dropped so the package's wrapper (which wraps the same bag) wins —
 * see test_view_error_bag_share_is_suppressed_silently below.
 */
class CollisionTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function reservedKeys(): array
    {
        return [
            'auth' => ['auth'],
            'request' => ['request'],
            'session' => ['session'],
            'route' => ['route'],
            'errors' => ['errors'],
        ];
    }

    #[DataProvider('reservedKeys')]
    public function test_passing_reserved_key_as_view_data_throws(string $key): void
    {
        $this->expectException(ReservedTemplateVariable::class);
        $this->expectExceptionMessage("\${$key}");

        view('globals_session', [$key => 'oops'])->render();
    }

    public function test_view_error_bag_share_is_suppressed_silently(): void
    {
        // Reproduces what Laravel's `ShareErrorsFromSession` middleware does
        // on every request: `View::share('errors', $session->get('errors'))`.
        // Without the suppression, the engine would throw and every web
        // request through the default middleware stack would 500.
        Session::start();
        $bag = (new ViewErrorBag)->put('default', new MessageBag([
            'email' => ['Email is required.'],
        ]));
        Session::put('errors', $bag);

        view()->share('errors', $bag);

        $output = view('globals_errors')->render();

        // The auto-shared wrapper still resolves the same underlying bag,
        // so the template renders the messages it would have rendered if
        // the middleware had never run.
        $this->assertStringContainsString('<li>Email is required.</li>', $output);
        $this->assertStringContainsString('first-email=Email is required.', $output);
    }

    public function test_non_bag_errors_value_still_throws(): void
    {
        // The suppression is narrowly scoped to `ViewErrorBag` instances —
        // anything else under `errors` is still treated as a programmer
        // collision and surfaces loudly.
        $this->expectException(ReservedTemplateVariable::class);
        $this->expectExceptionMessage('$errors');

        view('globals_errors', ['errors' => 'not a bag'])->render();
    }
}
