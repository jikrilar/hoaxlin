<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_verification_email(): void
    {
        Notification::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $user = User::where('email', 'budi@example.com')->firstOrFail();

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_user_cannot_register_with_an_existing_email(): void
    {
        $user = User::factory()->create(['email' => 'budi@example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Budi Lain',
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $response->assertSessionHasErrors([
            'email' => 'Email sudah terdaftar. Silakan masuk atau gunakan alamat email lain.',
        ]);
        $this->assertNotSame('validation.unique', session('errors')->first('email'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_native_remember_value_preserves_intended_redirect_and_regenerates_session(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->get(route('login'))->assertOk()
            ->assertSee('name="remember" value="1"', false)
            ->assertDontSee('30 hari');
        $this->withSession(['url.intended' => route('riwayat')]);
        $previousSessionId = session()->getId();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
            ->assertRedirect(route('riwayat'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousSessionId, session()->getId());
    }

    public function test_invalid_credentials_restore_email_and_remember_without_restoring_password(): void
    {
        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'invalid-credentials@example.test', 'password' => 'secret-marker', 'remember' => '1',
        ])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');

        $this->get(route('login'))->assertOk()
            ->assertSee('Email atau kata sandi yang kamu masukkan salah.')
            ->assertSee('invalid-credentials@example.test')
            ->assertSee('id="auth-errors"', false)
            ->assertDontSee('secret-marker');
        $this->assertGuest();
    }

    public function test_login_rate_limit_feedback_remains_a_server_message(): void
    {
        $credentials = ['email' => 'rate-limited@example.test', 'password' => 'wrong-password'];
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), $credentials)->assertSessionHasErrors('email');
        }
        $this->post(route('login.store'), $credentials)->assertSessionHasErrors('email');
        $this->get(route('login'))->assertOk()
            ->assertSee('Terlalu banyak percobaan masuk. Coba lagi dalam')
            ->assertSee('href="#email"', false);
        $this->assertGuest();
    }

    public function test_registration_preserves_terms_confirmation_and_error_recovery(): void
    {
        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Nama <script id="attack">literal</script>',
            'email' => 'registration-recovery@example.test',
            'password' => 'secret-marker', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['password', 'terms'])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->get(route('register'))->assertOk()
            ->assertSee('registration-recovery@example.test')
            ->assertSee('href="#terms"', false)
            ->assertSee('Nama &lt;script id=&quot;attack&quot;&gt;literal&lt;/script&gt;', false)
            ->assertDontSee('<script id="attack">', false)
            ->assertDontSee('secret-marker');
        $this->assertDatabaseEmpty('users');
    }

    public function test_user_can_verify_email_using_signed_url(): void
    {
        Event::fake([Verified::class]);
        $user = User::factory()->unverified()->create();
        $this->assertNull($user->email_verified_at);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->get($url)->assertRedirect(route('home'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->get($url)->assertRedirect(route('home'));
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_invalid_or_expired_verification_link_cannot_verify_email(): void
    {
        $user = User::factory()->unverified()->create();
        $parameters = ['id' => $user->id, 'hash' => sha1($user->email)];
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), $parameters);
        $expiredUrl = URL::temporarySignedRoute('verification.verify', now()->subMinute(), $parameters);

        $this->actingAs($user)->get($url.'&tampered=1')->assertForbidden();
        $this->get($expiredUrl)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_user_can_request_and_complete_password_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->post(route('password.store'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertRedirect(route('login'))->assertSessionHas('status', __(Password::PASSWORD_RESET));
            $this->assertGuest();

            return true;
        });

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_invalid_reset_token_get_is_a_form_and_post_has_recovery_without_mutating_password(): void
    {
        $user = User::factory()->create(['password' => 'original-password']);
        $resetUrl = route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]);
        $this->get($resetUrl)->assertOk()->assertSee('name="token" value="invalid-token"', false);

        $this->from($resetUrl)->post(route('password.store'), [
            'email' => $user->email, 'token' => 'invalid-token', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->get($resetUrl)->assertOk()
            ->assertSee(__(Password::INVALID_TOKEN))
            ->assertSee('Minta tautan reset baru')
            ->assertSee(route('password.request'), false);
        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_expired_reset_token_cannot_change_password_or_log_in_the_user(): void
    {
        $user = User::factory()->create(['password' => 'original-password']);
        $token = Password::createToken($user);
        $brokerName = config('auth.defaults.passwords');
        $expiry = (int) config('auth.passwords.'.$brokerName.'.expire');
        $this->travelTo(now()->addMinutes($expiry + 1), function () use ($user, $token): void {
            $this->post(route('password.store'), [
                'email' => $user->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password',
            ])->assertSessionHasErrors('email');
            $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
            $this->assertGuest();
        });
    }

    public function test_unverified_user_can_resend_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk()
            ->assertSee('folder spam')
            ->assertSee('Kirim Ulang Email Verifikasi');

        $this->actingAs($user)->post(route('verification.send'))
            ->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_does_not_receive_another_verification_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect(route('home'));

        Notification::assertNothingSent();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
