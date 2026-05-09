<?php

declare(strict_types=1);

namespace Vusys\LaravelSmarty\Tests\Globals;

use Illuminate\Support\Facades\Session as SessionFacade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Vusys\LaravelSmarty\Globals\Errors;
use Vusys\LaravelSmarty\Tests\TestCase;

class ErrorsTest extends TestCase
{
    public function test_any_has_count_reflect_default_bag(): void
    {
        $this->seedErrors([
            'email' => ['Email is required.'],
            'name' => ['Name is required.', 'Name is too short.'],
        ]);

        $errors = Errors::make();

        $this->assertTrue($errors->any());
        $this->assertSame(3, $errors->count());
        $this->assertTrue($errors->has('email'));
        $this->assertFalse($errors->has('absent'));
    }

    public function test_first_returns_first_message_for_field(): void
    {
        $this->seedErrors([
            'email' => ['Email is required.', 'Email is invalid.'],
        ]);

        $errors = Errors::make();

        $this->assertSame('Email is required.', $errors->first('email'));
        $this->assertSame('', $errors->first('absent'));
    }

    public function test_first_supports_format(): void
    {
        $this->seedErrors([
            'email' => ['Email is required.'],
        ]);

        $errors = Errors::make();

        $this->assertSame('<p>Email is required.</p>', $errors->first('email', '<p>:message</p>'));
    }

    public function test_get_returns_messages_for_field(): void
    {
        $this->seedErrors([
            'email' => ['First.', 'Second.'],
        ]);

        $errors = Errors::make();

        $this->assertSame(['First.', 'Second.'], $errors->get('email'));
        $this->assertSame([], $errors->get('absent'));
    }

    public function test_all_returns_messages_across_fields(): void
    {
        $this->seedErrors([
            'email' => ['Email error.'],
            'name' => ['Name error.'],
        ]);

        $errors = Errors::make();

        $this->assertEqualsCanonicalizing(
            ['Email error.', 'Name error.'],
            $errors->all(),
        );
    }

    public function test_get_bag_returns_wrapper_for_named_bag(): void
    {
        SessionFacade::start();
        $bag = (new ViewErrorBag)
            ->put('default', new MessageBag(['email' => ['default-msg']]))
            ->put('login', new MessageBag(['password' => ['login-msg']]));
        SessionFacade::put('errors', $bag);

        $errors = Errors::make();

        $this->assertSame('default-msg', $errors->first('email'));
        $this->assertSame('login-msg', $errors->getBag('login')->first('password'));
        $this->assertTrue($errors->getBag('login')->any());
        $this->assertFalse($errors->getBag('login')->has('email'));
    }

    public function test_get_bag_returns_empty_wrapper_for_unknown_bag(): void
    {
        $this->seedErrors(['email' => ['x']]);

        $errors = Errors::make();

        $this->assertFalse($errors->getBag('nonexistent')->any());
        $this->assertSame(0, $errors->getBag('nonexistent')->count());
        $this->assertSame('', $errors->getBag('nonexistent')->first('email'));
    }

    public function test_falls_back_when_session_unbound(): void
    {
        $this->app->forgetInstance('session.store');
        $this->app->offsetUnset('session.store');

        $errors = Errors::make();

        $this->assertFalse($errors->any());
        $this->assertSame(0, $errors->count());
        $this->assertFalse($errors->has('anything'));
        $this->assertSame('', $errors->first('anything'));
        $this->assertSame([], $errors->get('anything'));
        $this->assertSame([], $errors->all());
        $this->assertFalse($errors->getBag('login')->any());
    }

    public function test_falls_back_when_session_has_no_errors_key(): void
    {
        SessionFacade::start();

        $errors = Errors::make();

        $this->assertFalse($errors->any());
        $this->assertSame([], $errors->all());
        $this->assertFalse($errors->has('anything'));
    }

    public function test_falls_back_when_errors_value_is_not_a_view_error_bag(): void
    {
        SessionFacade::start();
        // Defensive: a misconfigured session driver could put something
        // unexpected at the `errors` key. Don't blow up downstream.
        SessionFacade::put('errors', 'not-a-bag');

        $errors = Errors::make();

        $this->assertFalse($errors->any());
        $this->assertSame([], $errors->all());
    }

    /**
     * @param  array<string, array<int, string>>  $messages
     */
    private function seedErrors(array $messages, string $bagName = 'default'): void
    {
        SessionFacade::start();
        $bag = (new ViewErrorBag)->put($bagName, new MessageBag($messages));
        SessionFacade::put('errors', $bag);
    }
}
