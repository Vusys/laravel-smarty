<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Globals;

use PHPUnit\Framework\Attributes\DataProvider;
use Vusys\LaravelSmarty\Exceptions\ReservedTemplateVariable;
use Vusys\LaravelSmarty\Tests\TestCase;

/**
 * Passing one of the five reserved auto-share keys ($auth, $request,
 * $session, $route, $errors) as view data is a programmer error —
 * silently letting user data win would mask typos and produce
 * confusing template output. The engine throws instead.
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
}
