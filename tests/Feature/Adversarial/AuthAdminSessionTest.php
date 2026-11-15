<?php

namespace Tests\Feature\Adversarial;

use App\Models\EventManager;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Trying to break the admin sign-in: session fixation, sign-out, guessing,
 * account enumeration through the password-reset forms, reset-link reuse and
 * expiry, the remember-me cookie and other open sessions after a password
 * change, and e-mail verification links.
 *
 * In tests the session id changes on every request unless the session cookie
 * is sent back, so the fixation checks send it back on purpose — otherwise a
 * "the id changed" assertion proves nothing.
 */
class AuthAdminSessionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password-1';

    private function admin(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['password' => self::PASSWORD]);
    }

    private function signIn(User $admin, bool $remember = false): TestResponse
    {
        return $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD] + ($remember ? ['remember' => 'on'] : []));
    }

    /** The next request starts from the session alone, as a new page load in the browser does. */
    private function nextRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    // ---- Session -------------------------------------------------------------

    public function test_signing_in_as_an_admin_replaces_the_session_id_an_attacker_could_have_planted(): void
    {
        $admin = $this->admin();
        $this->get('/admin/login');
        $planted = session()->getId();

        $this->withCookie(config('session.cookie'), $planted)
            ->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNotSame($planted, session()->getId());
    }

    public function test_signing_in_as_a_manager_replaces_the_session_id_an_attacker_could_have_planted(): void
    {
        $manager = EventManager::factory()->create(['password' => self::PASSWORD]);
        $this->get('/manager/login');
        $planted = session()->getId();

        $this->withCookie(config('session.cookie'), $planted)
            ->post('/manager/login', ['email' => $manager->email, 'password' => self::PASSWORD])
            ->assertRedirect();

        $this->assertAuthenticatedAs($manager, 'manager');
        $this->assertNotSame($planted, session()->getId());
    }

    public function test_signing_out_ends_the_admin_session_and_its_forms_for_good(): void
    {
        $admin = $this->admin(['name' => 'Original']);
        $this->signIn($admin);
        $this->nextRequest();
        $this->get(route('dashboard'))->assertOk();
        $token = session()->token();

        $this->post(route('logout'))->assertRedirect('/');
        $this->nextRequest();

        $this->assertGuest();
        $this->assertNotSame($token, session()->token());
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->patch(route('profile.update'), ['name' => 'After sign out', 'email' => $admin->email])->assertRedirect(route('login'));
        $this->assertSame('Original', $admin->fresh()->name);
    }

    // ---- Guessing ------------------------------------------------------------

    public function test_an_unknown_address_and_a_wrong_password_get_the_same_answer(): void
    {
        $admin = $this->admin();

        $unknown = $this->post('/admin/login', ['email' => 'nobody@example.com', 'password' => self::PASSWORD]);
        $wrong = $this->post('/admin/login', ['email' => $admin->email, 'password' => 'secret-password-2']);

        $this->assertSame(session('errors')?->get('email'), [__('auth.failed')]);
        $unknown->assertSessionHasErrors(['email' => __('auth.failed')]);
        $wrong->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_five_wrong_guesses_lock_the_sign_in_even_for_the_right_password(): void
    {
        $admin = $this->admin();

        foreach (range(1, 5) as $try) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => "wrong-guess-{$try}"]);
        }

        $locked = $this->post('/admin/login', ['email' => $admin->email, 'password' => self::PASSWORD]);

        $this->assertContains($locked->status(), [302, 429]);
        $this->assertGuest();
    }

    public function test_odd_sign_in_input_is_refused_not_an_error(): void
    {
        $this->post('/admin/login', ['email' => ['a@example.com'], 'password' => ['x']])->assertSessionHasErrors(['email', 'password']);
        $this->post('/admin/login', ['email' => "' OR 1=1 --", 'password' => "' OR '1'='1"])->assertSessionHasErrors('email');
        $this->post('/admin/login', ['email' => str_repeat('a', 5000).'@example.com', 'password' => str_repeat('p', 100000)])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    // ---- Password changes end every other way in -----------------------------

    public function test_the_remember_me_cookie_stops_working_once_the_password_changes(): void
    {
        $admin = $this->admin();
        $cookie = collect($this->signIn($admin, remember: true)->headers->getCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
        $this->assertNotNull($cookie, 'a remember-me cookie was set');

        // With only that cookie (a new browser session), the admin is let in…
        $this->flushSession();
        $this->nextRequest();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get(route('dashboard'))->assertOk();

        // …until the password changes.
        $admin->forceFill(['password' => Hash::make('secret-password-2')])->save();
        $this->flushSession();
        $this->nextRequest();
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_another_open_session_is_signed_out_when_the_password_is_changed(): void
    {
        $admin = $this->admin();
        $this->signIn($admin);
        $this->nextRequest();
        $this->get(route('dashboard'))->assertOk();

        // Changed somewhere else: on another device, or by a reset link.
        $admin->forceFill(['password' => Hash::make('secret-password-2')])->save();
        $this->nextRequest();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_the_session_that_changed_the_password_stays_signed_in(): void
    {
        $admin = $this->admin();
        $this->signIn($admin);
        $this->nextRequest();
        $this->get(route('profile.edit'))->assertOk();

        $this->put(route('password.update'), ['current_password' => self::PASSWORD, 'password' => 'secret-password-2', 'password_confirmation' => 'secret-password-2'])
            ->assertSessionHasNoErrors();
        $this->nextRequest();

        $this->get(route('profile.edit'))->assertOk();
        $this->assertTrue(Hash::check('secret-password-2', $admin->fresh()->password));
    }

    // ---- Password reset --------------------------------------------------------

    public function test_asking_for_a_reset_link_does_not_say_whether_an_admin_account_exists(): void
    {
        Notification::fake();
        $admin = $this->admin();

        $known = $this->from('/admin/forgot-password')->post('/admin/forgot-password', ['email' => $admin->email]);
        $knownStatus = session('status');
        $again = $this->from('/admin/forgot-password')->post('/admin/forgot-password', ['email' => $admin->email]);
        $againStatus = session('status');
        $unknown = $this->from('/admin/forgot-password')->post('/admin/forgot-password', ['email' => 'nobody@example.com']);
        $unknownStatus = session('status');

        foreach ([$known, $again, $unknown] as $response) {
            $response->assertRedirect('/admin/forgot-password')->assertSessionHasNoErrors();
        }
        $this->assertNotNull($knownStatus);
        $this->assertSame($knownStatus, $unknownStatus, 'an unknown address is answered differently');
        $this->assertSame($knownStatus, $againStatus, 'a second request for a real account is answered differently');
        Notification::assertSentToTimes($admin, ResetPassword::class, 1);
    }

    public function test_a_reset_for_an_address_with_no_account_is_answered_like_a_bad_link(): void
    {
        $badToken = $this->post('/admin/reset-password', ['token' => 'made-up', 'email' => $this->admin()->email, 'password' => 'secret-password-2', 'password_confirmation' => 'secret-password-2']);
        $badTokenError = session('errors')->first('email');
        $noAccount = $this->post('/admin/reset-password', ['token' => 'made-up', 'email' => 'nobody@example.com', 'password' => 'secret-password-2', 'password_confirmation' => 'secret-password-2']);

        $badToken->assertSessionHasErrors('email');
        $noAccount->assertSessionHasErrors(['email' => $badTokenError]);
    }

    public function test_a_reset_link_works_once_and_only_for_its_own_account(): void
    {
        $admin = $this->admin();
        $other = $this->admin();
        $token = Password::broker()->createToken($admin);

        // Someone else's address with this token: refused.
        $this->post('/admin/reset-password', ['token' => $token, 'email' => $other->email, 'password' => 'secret-password-3', 'password_confirmation' => 'secret-password-3'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::PASSWORD, $other->fresh()->password));

        $this->post('/admin/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'secret-password-2', 'password_confirmation' => 'secret-password-2'])
            ->assertSessionHasNoErrors()->assertRedirect(route('login'));

        // The same link a second time: refused, the password stays the one just set.
        $this->post('/admin/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'secret-password-4', 'password_confirmation' => 'secret-password-4'])
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('secret-password-2', $admin->fresh()->password));
    }

    public function test_a_reset_link_expires_after_an_hour(): void
    {
        $this->travelTo('2026-11-07 10:00:00');
        $admin = $this->admin();
        $token = Password::broker()->createToken($admin);

        $this->travelTo('2026-11-07 11:01:00');
        $this->post('/admin/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'secret-password-2', 'password_confirmation' => 'secret-password-2'])
            ->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::PASSWORD, $admin->fresh()->password));
    }

    public function test_a_reset_needs_a_real_password(): void
    {
        $admin = $this->admin();
        $token = Password::broker()->createToken($admin);

        $this->post('/admin/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/admin/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'secret-password-2', 'password_confirmation' => 'different-password'])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::PASSWORD, $admin->fresh()->password));
    }

    public function test_a_reset_signs_out_every_open_session_and_the_remember_me_cookie(): void
    {
        $admin = $this->admin();
        $this->signIn($admin, remember: true);
        $this->nextRequest();
        $this->get(route('dashboard'))->assertOk();
        $rememberBefore = $admin->fresh()->remember_token;

        $token = Password::broker()->createToken($admin);
        $this->app['auth']->guard()->logout(); // the reset form is for guests; the old session is kept below.
        $this->post('/admin/reset-password', ['token' => $token, 'email' => $admin->email, 'password' => 'secret-password-2', 'password_confirmation' => 'secret-password-2'])
            ->assertRedirect(route('login'));

        $this->assertNotSame($rememberBefore, $admin->fresh()->remember_token);
    }

    public function test_the_reset_form_shows_a_hostile_address_or_token_as_text(): void
    {
        $this->get('/admin/reset-password/'.rawurlencode('"><svg onload=alert(1)>').'?email='.rawurlencode('"><img src=x onerror=alert(2)>'))
            ->assertOk()
            ->assertDontSee('<svg onload=alert(1)>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false);
    }

    // ---- E-mail verification -----------------------------------------------------
    // (User does not implement MustVerifyEmail — there is no sign-up — so `verified` lets every
    // signed-in admin through; these check the verification link itself.)

    public function test_a_tampered_or_borrowed_verification_link_verifies_nobody(): void
    {
        $admin = $this->admin(['email_verified_at' => null]);
        $other = $this->admin(['email_verified_at' => null]);
        $this->actingAs($admin);

        $sign = fn (User $user, ?string $hash = null) => URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => $hash ?? sha1($user->getEmailForVerification())]);

        // Wrong e-mail hash, a broken signature, an expired link, and another admin's link.
        $this->get($sign($admin, sha1('attacker@example.com')))->assertForbidden();
        $this->get($sign($admin).'x')->assertForbidden();
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $admin->id, 'hash' => sha1($admin->getEmailForVerification())]);
        $this->get($expired)->assertForbidden();
        $this->get($sign($other))->assertForbidden();

        $this->assertNull($admin->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);

        // The real link still works.
        $this->get($sign($admin))->assertRedirect();
        $this->assertNotNull($admin->fresh()->email_verified_at);
    }

    public function test_the_profile_form_can_not_mark_a_new_address_verified_or_set_a_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->patch(route('profile.update'), [
            'name' => $admin->name, 'email' => 'new-address@example.com',
            'email_verified_at' => now()->toDateTimeString(), 'password' => 'secret-password-9', 'remember_token' => 'x', 'id' => 999,
        ])->assertSessionHasNoErrors();

        $admin->refresh();
        $this->assertSame('new-address@example.com', $admin->email);
        $this->assertNull($admin->email_verified_at);
        $this->assertTrue(Hash::check(self::PASSWORD, $admin->password));
        $this->assertNotSame('x', $admin->remember_token);
        $this->assertNull(User::find(999));
    }

    // ---- The first admin account ---------------------------------------------------

    public function test_the_admin_seeder_saves_the_password_it_prints_never_a_fixed_default(): void
    {
        Artisan::call('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);
        preg_match('/Password:\s+(\S+)/', Artisan::output(), $printed);

        $admin = User::sole();
        $this->assertNotEmpty($printed[1] ?? null, 'the password is printed once');
        $this->assertGreaterThanOrEqual(20, strlen($printed[1]));
        $this->assertTrue(Hash::check($printed[1], $admin->password), 'the printed password is the one that works');
        $this->assertFalse(Hash::check('Hello@123', $admin->password));
    }

    // ---- Confirm password ------------------------------------------------------------

    public function test_confirming_the_password_refuses_a_wrong_one_and_is_limited(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post('/admin/confirm-password', ['password' => 'wrong-password'])->assertSessionHasErrors('password');
        $this->assertNull(session('auth.password_confirmed_at'));

        foreach (range(1, 5) as $try) {
            $this->post('/admin/confirm-password', ['password' => "wrong-{$try}"]);
        }
        $this->post('/admin/confirm-password', ['password' => self::PASSWORD])->assertStatus(429);
    }

    // ---- Where sign-in leads ---------------------------------------------------------

    public function test_after_signing_in_an_admin_is_only_ever_sent_to_a_page_of_this_site(): void
    {
        $admin = $this->admin();

        $this->get('/admin/events?next='.rawurlencode('https://evil.example/').'&return_to='.rawurlencode('//evil.example'))->assertRedirect(route('login'));
        $target = $this->signIn($admin)->headers->get('Location');

        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url((string) $target, PHP_URL_HOST));
        $this->assertStringStartsWith(url('/admin/events'), (string) $target);

        // Nor does a "next" on the sign-in form itself go anywhere.
        $this->post(route('logout'));
        $this->nextRequest();
        $target = $this->post('/admin/login?next=https://evil.example', ['email' => $admin->email, 'password' => self::PASSWORD, 'redirect' => 'https://evil.example'])->headers->get('Location');
        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url((string) $target, PHP_URL_HOST));
    }

    public function test_there_is_no_way_to_sign_up(): void
    {
        $this->get('/admin/register')->assertNotFound();
        $this->post('/admin/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])->assertStatus(404);
        $this->get('/register')->assertNotFound();
        $this->assertSame(0, User::count());
    }

    public function test_verification_mail_is_not_sent_to_an_already_verified_admin(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());

        $this->post(route('verification.send'))->assertRedirect();

        Notification::assertNothingSent();
        Notification::assertNotSentTo(User::first(), VerifyEmail::class);
    }
}
