<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Bert\BertRuntimeMetadata;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        $this->mock(BertRuntimeMetadata::class)->shouldReceive('fetch')->andReturn([
            'available' => false, 'model_version' => null, 'evaluation' => [],
        ]);
    }

    public function test_guest_uses_native_login_and_protected_pages_still_redirect_to_it(): void
    {
        $panel = Filament::getPanel('admin');
        $response = $this->get($panel->getLoginUrl())->assertOk()
            ->assertSee('hoaxlin.id')->assertSee('wire:submit="authenticate"', false)
            ->assertSee('data.email')->assertSee('data.password')->assertSee('data.remember')
            ->assertDontSee('fonts.bunny.net')->assertDontSee('fonts.googleapis.com');
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $response->assertSee($manifest['resources/css/filament/admin/theme.css']['file'])
            ->assertDontSee($manifest['resources/css/app.css']['file'])
            ->assertDontSee($manifest['resources/js/app.js']['file']);
        $this->get($panel->getUrl())->assertRedirect($panel->getLoginUrl());
        $this->get(UserResource::getUrl('index'))->assertRedirect($panel->getLoginUrl());
        $this->assertGuest();
        Http::assertNothingSent();
    }

    #[DataProvider('verificationStates')]
    public function test_regular_user_is_denied_by_native_login_and_panel_middleware(bool $verified): void
    {
        $user = User::factory()->create([
            'is_admin' => false, 'password' => 'Fixture-password-123',
            'email_verified_at' => $verified ? now() : null,
        ]);
        Livewire::test(Login::class)->fillForm([
            'email' => $user->email, 'password' => 'Fixture-password-123', 'remember' => false,
        ])->call('authenticate')->assertHasFormErrors(['email'])->assertNoRedirect();
        $this->assertGuest();
        $this->actingAs($user)->get(Filament::getPanel('admin')->getUrl())->assertForbidden();
        $this->get(UserResource::getUrl('index'))->assertForbidden();
    }

    #[DataProvider('verificationStates')]
    public function test_admin_native_login_and_existing_pages_do_not_add_a_verified_requirement(bool $verified): void
    {
        $user = User::factory()->create([
            'is_admin' => true, 'password' => 'Fixture-password-123',
            'email_verified_at' => $verified ? now() : null,
        ]);
        Livewire::test(Login::class)->fillForm([
            'email' => $user->email, 'password' => 'Fixture-password-123', 'remember' => true,
        ])->call('authenticate')->assertHasNoFormErrors()->assertRedirect(Filament::getPanel('admin')->getUrl());
        $this->assertAuthenticatedAs($user);
        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();
        $this->get(UserResource::getUrl('index'))->assertOk()->assertSee($user->email);
        $this->get(UserResource::getUrl('edit', ['record' => $user]))->assertOk();
        Http::assertNothingSent();
    }

    public static function verificationStates(): array
    {
        return ['unverified' => [false], 'verified' => [true]];
    }

    public function test_wrong_credentials_and_missing_fields_keep_native_form_validation(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'password' => 'Fixture-password-123']);
        Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'Wrong-password'])
            ->call('authenticate')->assertHasFormErrors(['email'])->assertNoRedirect();
        $this->assertGuest();
        Livewire::test(Login::class)->fillForm(['email' => '', 'password' => ''])
            ->call('authenticate')->assertHasFormErrors(['email' => 'required', 'password' => 'required'])->assertNoRedirect();
        $this->assertGuest();
    }

    public function test_native_login_limiter_still_blocks_the_sixth_attempt(): void
    {
        $component = Livewire::test(Login::class)->fillForm(['email' => '', 'password' => '']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $component->call('authenticate')->assertHasFormErrors(['email', 'password']);
        }
        $component->call('authenticate')->assertNotified()->assertNoRedirect();
        $this->assertGuest();
    }

    public function test_real_update_endpoint_keeps_csrf_protection(): void
    {
        $this->get(Filament::getPanel('admin')->getLoginUrl())->assertOk();
        $this->app->instance('env', 'production');
        $this->postJson(Livewire::getUpdateUri(), ['components' => []])->assertStatus(419);
        $this->assertGuest();
    }
}
