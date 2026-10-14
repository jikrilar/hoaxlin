<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Dataset;
use App\Models\Submission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminUsersResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Http::preventStrayRequests();
        Notification::fake();
    }

    #[DataProvider('pages')]
    public function test_native_pages_require_admin_and_allow_unverified_admin(string $page): void
    {
        $record = User::factory()->create(['name' => 'Fixture Target', 'is_admin' => false]);
        $url = UserResource::getUrl($page, ['record' => $record]);
        $this->get($url)->assertRedirect(Filament::getPanel('admin')->getLoginUrl());
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->admin();
        $response = $this->get($url)->assertOk()->assertDontSee($record->password)->assertDontSee($record->remember_token);
        if (in_array($page, ['create', 'edit'], true)) {
            $response->assertSee('data-users-runtime-required');
        } else {
            $response->assertDontSee('data-users-runtime-required');
        }
        Http::assertNothingSent();
    }

    public static function pages(): array
    {
        return ['list' => ['index'], 'create' => ['create'], 'detail' => ['view'], 'edit' => ['edit']];
    }

    public function test_detail_and_table_escape_names_and_show_explicit_role_and_verification(): void
    {
        $this->admin();
        $user = User::factory()->unverified()->create(['name' => '<script id="attack">Fixture</script>'.str_repeat('long', 40).' UniqueTailFixture', 'is_admin' => false]);
        $this->get(UserResource::getUrl('view', ['record' => $user]))->assertOk()
            ->assertSee($user->name)->assertDontSee('<script id="attack">', false)
            ->assertSee('Identitas akun')->assertSee('Pengguna')->assertSee('Belum terverifikasi')
            ->assertDontSee($user->password)->assertDontSee($user->remember_token);
        Livewire::test(ListUsers::class)->assertSee('Pengguna')->assertSee('Belum terverifikasi')->assertDontSee('<script id="attack">', false)
            ->assertDontSee($user->name)->searchTable('UniqueTailFixture')->assertCanSeeTableRecords([$user]);
    }

    public function test_native_search_sort_filters_and_pagination_keep_their_populations(): void
    {
        $admin = $this->admin();
        $verified = User::factory()->create(['name' => 'Alpha Fixture', 'is_admin' => false]);
        $unverified = User::factory()->unverified()->create(['name' => 'Beta Fixture', 'is_admin' => false]);
        Livewire::test(ListUsers::class)->searchTable($verified->email)->assertCanSeeTableRecords([$verified])->assertCanNotSeeTableRecords([$admin, $unverified]);
        Livewire::test(ListUsers::class)->filterTable('is_admin', true)->assertCanSeeTableRecords([$admin])->assertCanNotSeeTableRecords([$verified, $unverified]);
        Livewire::test(ListUsers::class)->filterTable('email_verified_at', true)->assertCanSeeTableRecords([$verified])->assertCanNotSeeTableRecords([$admin, $unverified]);
        Livewire::test(ListUsers::class)->filterTable('email_verified_at', false)->assertCanSeeTableRecords([$admin, $unverified])->assertCanNotSeeTableRecords([$verified]);
        Livewire::test(ListUsers::class)->sortTable('name')->assertCanSeeTableRecords(User::orderBy('name')->get(), inOrder: true);
        User::factory()->count(12)->create();
        $page = Livewire::test(ListUsers::class)->set('tableRecordsPerPage', 5)->sortTable('email');
        $this->assertCount(5, $page->instance()->getTableRecords());
        $page->assertCanSeeTableRecords(User::orderBy('email')->limit(5)->get(), inOrder: true)
            ->call('gotoPage', 2)->assertCanSeeTableRecords(User::orderBy('email')->skip(5)->limit(5)->get(), inOrder: true);
    }

    public function test_create_preserves_rules_and_hashes_password_without_verifying_email(): void
    {
        $admin = $this->admin();
        Livewire::test(CreateUser::class)->fillForm(['name' => '', 'email' => 'invalid', 'password' => 'short'])->call('create')
            ->assertHasFormErrors(['name' => 'required', 'email' => 'email', 'password' => 'min'])
            ->assertSee('Masukkan nama pengguna.')->assertSee('Masukkan alamat email yang valid.')->assertSee('Kata sandi minimal 8 karakter.');
        Livewire::test(CreateUser::class)->fillForm(['name' => str_repeat('a', 256), 'email' => $admin->email, 'password' => ''])->call('create')
            ->assertHasFormErrors(['name' => 'max', 'email' => 'unique', 'password' => 'required']);
        Livewire::test(CreateUser::class)->fillForm(['name' => 'New Fixture', 'email' => 'new@example.test', 'password' => 'Fixture-password-123', 'is_admin' => false])
            ->set('data.email_verified_at', now()->toDateTimeString())
            ->call('create')->assertHasNoFormErrors();
        $user = User::where('email', 'new@example.test')->sole();
        $this->assertTrue(Hash::check('Fixture-password-123', $user->password));
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_edit_empty_password_preserves_hash_and_verification_even_when_email_changes(): void
    {
        $this->admin();
        $user = User::factory()->create(['is_admin' => false]);
        $hash = $user->password;
        $verified = $user->email_verified_at;
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->assertSet('data.password', null)->assertDontSee($hash)
            ->fillForm(['name' => 'Updated Fixture', 'email' => 'updated@example.test', 'password' => ''])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue($verified->equalTo($user->fresh()->email_verified_at));
        $this->assertSame('updated@example.test', $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_explicit_password_change_is_hashed_and_cleared_after_save(): void
    {
        $this->admin();
        $user = User::factory()->unverified()->create(['is_admin' => false]);
        $page = Livewire::test(EditUser::class, ['record' => $user->getRouteKey()]);
        $page->fillForm(['password' => 'short'])->call('save')->assertHasFormErrors(['password' => 'min']);
        $page->fillForm(['password' => 'New-password-123'])->call('save')->assertHasNoFormErrors()->assertSet('data.password', null);
        $hash = $user->fresh()->password;
        $this->assertTrue(Hash::check('New-password-123', $hash));
        $this->assertNull($user->fresh()->email_verified_at);
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_verifier_demotion_error_is_connected_to_native_field_without_persisting_changes(): void
    {
        $this->admin();
        $verifier = User::factory()->create(['is_admin' => true]);
        Dataset::factory()->create(['verified_by' => $verifier->id]);
        Livewire::test(EditUser::class, ['record' => $verifier->getRouteKey()])->fillForm(['is_admin' => false])
            ->call('save')->assertHasFormErrors(['is_admin'])
            ->assertSee('Administrator masih tercatat sebagai verifier katalog dataset.');
        $this->assertTrue($verifier->fresh()->is_admin);
        $this->assertDatabaseHas('datasets', ['verified_by' => $verifier->id]);
    }

    public function test_role_changes_without_verifier_assignments_preserve_verification(): void
    {
        $this->admin();
        $user = User::factory()->unverified()->create(['is_admin' => false]);
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->fillForm(['is_admin' => true])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertNull($user->fresh()->email_verified_at);
        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->fillForm(['is_admin' => false])->call('save')->assertHasNoFormErrors();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_bulk_confirmation_counts_selected_records_and_cancellation_does_not_delete(): void
    {
        $this->admin();
        $targets = User::factory()->count(2)->create(['is_admin' => false]);
        $submission = Submission::factory()->for($targets[0])->create();
        $page = Livewire::test(ListUsers::class)->mountTableBulkAction('delete', $targets)->assertTableBulkActionMounted('delete');
        $action = $page->instance()->getMountedAction();
        $this->assertTrue($action->isConfirmationRequired());
        $this->assertStringStartsWith('Anda memilih 2 pengguna.', $action->getModalDescription());
        $page->call('unmountAction');
        foreach ($targets as $target) {
            $this->assertModelExists($target);
        }
        $page->callTableBulkAction('delete', $targets);
        foreach ($targets as $target) {
            $this->assertModelMissing($target);
        }
        $this->assertModelMissing($submission);
    }

    public function test_single_delete_keeps_existing_cascade_and_other_accounts(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['is_admin' => false]);
        $submission = Submission::factory()->for($user)->create();
        $page = Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])->mountAction('delete');
        $this->assertModelExists($user);
        $page->callMountedAction();
        $this->assertModelMissing($user);
        $this->assertModelMissing($submission);
        $this->assertModelExists($admin);
    }

    private function admin(): User
    {
        $admin = User::factory()->unverified()->create(['name' => 'Z Admin Fixture', 'is_admin' => true]);
        $this->actingAs($admin);

        return $admin;
    }
}
