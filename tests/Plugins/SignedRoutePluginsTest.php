<?php

namespace Vusys\LaravelSmarty\Tests\Plugins;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Vusys\LaravelSmarty\Tests\TestCase;

class SignedRoutePluginsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // hasValidSignature() needs a stable APP_KEY to verify against.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The fixture renders both helpers, so both names must resolve
        // for any test that touches the view.
        Route::get('/unsubscribe/{user}', fn (Request $request) => $request->hasValidSignature() ? 'valid' : 'invalid')->name('unsubscribe');

        Route::get('/download/{file}', fn (Request $request) => $request->hasValidSignature() ? 'ok' : 'bad')->name('download');
    }

    public function test_signed_route_emits_signed_url(): void
    {
        $output = view('signed_routes', [
            'userId' => 42,
            'fileId' => 7,
            'expiresAt' => Date::createFromTimestamp(2_000_000_000),
        ])->render();

        $expectedSigned = URL::signedRoute('unsubscribe', ['user' => 42]);
        $this->assertStringContainsString('signed='.$expectedSigned, $output);
        $this->assertMatchesRegularExpression('#signed=http://localhost/unsubscribe/42\?signature=[a-f0-9]+#', $output);
    }

    public function test_temporary_signed_route_with_int_expiration(): void
    {
        Date::setTestNow(Date::createFromTimestamp(1_000_000_000));

        try {
            $output = view('signed_routes', [
                'userId' => 1,
                'fileId' => 7,
                'expiresAt' => Date::createFromTimestamp(1_000_003_600),
            ])->render();
        } finally {
            Date::setTestNow();
        }

        $this->assertMatchesRegularExpression('#temp_int=http://localhost/download/7\?expires=\d+&signature=[a-f0-9]+#', $output);
    }

    public function test_temporary_signed_route_with_datetime_expiration(): void
    {
        $expiresAt = Date::createFromTimestamp(2_000_000_000);

        $output = view('signed_routes', [
            'userId' => 1,
            'fileId' => 7,
            'expiresAt' => $expiresAt,
        ])->render();

        $expected = URL::temporarySignedRoute('download', $expiresAt, ['file' => 7]);
        $this->assertStringContainsString('temp_dt='.$expected, $output);
    }

    public function test_signed_url_round_trips_validation(): void
    {
        $output = view('signed_routes', [
            'userId' => 42,
            'fileId' => 7,
            'expiresAt' => Date::createFromTimestamp(2_000_000_000),
        ])->render();

        preg_match('#signed=(\S+)#', $output, $matches);
        $this->assertNotEmpty($matches, 'expected to extract signed URL from rendered output');

        $response = $this->get($matches[1]);
        $response->assertOk();
        $this->assertSame('valid', $response->getContent());
    }
}
