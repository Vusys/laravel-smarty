<?php

namespace Vusys\LaravelSmarty\Tests\Globals;

use Illuminate\Support\Facades\Session as SessionFacade;
use Vusys\LaravelSmarty\Globals\Session;
use Vusys\LaravelSmarty\Tests\TestCase;

class SessionTest extends TestCase
{
    public function test_get_returns_value_or_default(): void
    {
        SessionFacade::start();
        SessionFacade::put('status', 'saved');

        $session = Session::make();

        $this->assertSame('saved', $session->get('status'));
        $this->assertSame('default', $session->get('missing', 'default'));
        $this->assertNull($session->get('missing'));
    }

    public function test_has_reflects_session_keys(): void
    {
        SessionFacade::start();
        SessionFacade::put('error', 'oops');

        $session = Session::make();

        $this->assertTrue($session->has('error'));
        $this->assertFalse($session->has('absent'));
    }

    public function test_magic_get_is_shorthand_for_get(): void
    {
        SessionFacade::start();
        SessionFacade::put('status', 'flashed!');

        $session = Session::make();

        $this->assertSame('flashed!', $session->status);
        $this->assertNull($session->absent_key);
    }

    public function test_token_returns_string_when_session_started(): void
    {
        SessionFacade::start();

        $session = Session::make();

        $this->assertSame(SessionFacade::token(), $session->token());
        $this->assertIsString($session->token());
    }

    public function test_token_returns_null_when_session_not_started(): void
    {
        // Note: no Session::start() — synthetic console-like state.
        $session = Session::make();

        $this->assertNull($session->token());
    }

    public function test_isset_reflects_session_keys(): void
    {
        SessionFacade::start();
        SessionFacade::put('status', 'flashed!');

        $session = Session::make();

        $this->assertTrue(isset($session->status));
        $this->assertFalse(isset($session->absent));
    }

    public function test_flashed_keys_returns_keys_flashed_to_this_request(): void
    {
        SessionFacade::start();
        // _flash.old is the bag of keys flashed *to* this request.
        SessionFacade::put('_flash.old', ['status', 'error']);

        $session = Session::make();

        $this->assertSame(['status', 'error'], $session->flashedKeys());
    }

    public function test_flashed_keys_is_empty_when_no_flash_payload(): void
    {
        SessionFacade::start();

        $session = Session::make();

        $this->assertSame([], $session->flashedKeys());
    }

    public function test_make_falls_back_when_session_unbound(): void
    {
        // Simulate a stateless app (no session.store binding) — e.g.
        // queue worker or API-only project. The wrapper should still
        // be usable and surface empty/null/false defaults uniformly.
        $this->app->forgetInstance('session.store');
        $this->app->offsetUnset('session.store');

        $session = Session::make();

        $this->assertFalse($session->has('anything'));
        $this->assertNull($session->get('anything'));
        $this->assertSame('default', $session->get('anything', 'default'));
        $this->assertNull($session->token());
        $this->assertSame([], $session->flashedKeys());
        $this->assertNull($session->whatever);
    }
}
