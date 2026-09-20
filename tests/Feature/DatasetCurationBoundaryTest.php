<?php

namespace Tests\Feature;

use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\Datasets\Pages\CreateDataset;
use App\Models\Dataset;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DatasetCurationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_non_admin_cannot_access_dataset_resource_while_admin_can(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $url = DatasetResource::getUrl('index', panel: 'admin');

        $this->actingAs($regularUser)->get($url)->assertForbidden();
        $this->actingAs($admin)->get($url)->assertOk();
    }

    public function test_verifier_dropdown_only_contains_admin_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $this->actingAs($admin);

        Livewire::test(CreateDataset::class)
            ->assertFormFieldExists('verified_by', function (Select $field) use ($admin, $otherAdmin, $regularUser): bool {
                $options = $field->getOptions();

                return isset($options[$admin->id], $options[$otherAdmin->id])
                    && ! isset($options[$regularUser->id]);
            });
    }

    public function test_admin_or_null_verifier_can_be_persisted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $verified = Dataset::factory()->create(['verified_by' => $admin->id]);
        $unverified = Dataset::factory()->create(['verified_by' => null]);

        $this->assertSame($admin->id, $verified->verified_by);
        $this->assertNull($unverified->verified_by);
    }

    public function test_regular_user_cannot_be_persisted_as_verifier_through_direct_model_save(): void
    {
        $regularUser = User::factory()->create(['is_admin' => false]);

        try {
            Dataset::factory()->create(['verified_by' => $regularUser->id]);
            $this->fail('A regular user was accepted as a dataset verifier.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verified_by', $exception->errors());
        }

        $this->assertDatabaseCount('datasets', 0);
    }

    public function test_forged_filament_form_cannot_assign_regular_user_as_verifier(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $this->actingAs($admin);

        Livewire::test(CreateDataset::class)
            ->fillForm([
                'text' => 'Klaim katalog yang cukup panjang untuk diverifikasi oleh administrator.',
                'label' => 'meragukan',
                'source' => 'Pengujian kurasi',
                'verified_by' => $regularUser->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['verified_by']);

        $this->assertDatabaseCount('datasets', 0);
    }

    public function test_editing_dataset_preserves_admin_verifier_invariant(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);
        $dataset = Dataset::factory()->create(['verified_by' => $admin->id]);

        $dataset->update(['text' => 'Teks katalog yang diperbarui tanpa mengganti verifier admin.']);
        $this->assertSame($admin->id, $dataset->fresh()->verified_by);

        try {
            $dataset->update(['verified_by' => $regularUser->id]);
            $this->fail('Editing accepted a regular user as verifier.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verified_by', $exception->errors());
        }

        $this->assertSame($admin->id, $dataset->fresh()->verified_by);
    }

    public function test_verifier_cannot_be_demoted_while_still_assigned(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $dataset = Dataset::factory()->create(['verified_by' => $admin->id]);

        try {
            $admin->update(['is_admin' => false]);
            $this->fail('An assigned verifier was demoted to a regular user.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_admin', $exception->errors());
        }

        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertSame($admin->id, $dataset->fresh()->verified_by);
    }

    public function test_dataset_crud_does_not_dispatch_training_or_run_subprocesses(): void
    {
        Bus::fake();
        Process::fake();
        $bertConfig = config('services.bert');

        $dataset = Dataset::factory()->create(['verified_by' => null]);
        $dataset->update(['label' => 'hoax']);
        $dataset->delete();

        Bus::assertNothingDispatched();
        Process::assertNothingRan();
        $this->assertSame($bertConfig, config('services.bert'));
    }
}
