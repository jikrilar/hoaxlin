<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Filament\Resources\DetectionResults\DetectionResultResource;
use App\Filament\Resources\Feedback\FeedbackResource;
use App\Filament\Resources\ModelVersions\ModelVersionResource;
use App\Filament\Resources\SubmissionProcessingEvents\SubmissionProcessingEventResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AdminLog;
use App\Models\Dataset;
use App\Models\DetectionResult;
use App\Models\Feedback;
use App\Models\ModelVersion;
use App\Models\Submission;
use App\Models\SubmissionProcessingEvent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** T1.6: admin navigation grouping, Indonesian labels, unique icons and explicit sort order. */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_seven_navigation_items_are_grouped_into_three_ordered_groups(): void
    {
        $items = $this->navigationItems();
        $visible = $items->filter(fn (array $item): bool => $item['visible'])->values();

        $this->assertSame(7, $visible->count(), 'Only seven resources belong in admin navigation.');

        $this->assertSame([
            ['Operasional', 'Periksa', 10],
            ['Operasional', 'Pemeriksaan', 20],
            ['Operasional', 'Umpan Balik', 30],
            ['Model AI', 'Versi Model', 40],
            ['Model AI', 'Data Kurasi', 50],
            ['Pengguna & Keamanan', 'Pengguna', 60],
            ['Pengguna & Keamanan', 'Log Admin', 70],
        ], $visible->map(fn (array $item): array => [$item['group'], $item['label'], $item['sort']])->all());
    }

    public function test_each_navigation_item_has_a_unique_icon(): void
    {
        $icons = $this->navigationItems()
            ->filter(fn (array $item): bool => $item['visible'])
            ->map(fn (array $item): string => (string) $item['icon'])
            ->values()
            ->all();

        $this->assertNotContains('', $icons, 'Every navigation item needs an icon.');
        $this->assertSame($icons, array_values(array_unique($icons)), 'Navigation icons must be unique per menu.');
    }

    public function test_duplicate_detection_and_processing_event_resources_stay_out_of_navigation(): void
    {
        $items = $this->navigationItems()->keyBy('resource');

        foreach ([DetectionResultResource::class, SubmissionProcessingEventResource::class] as $resource) {
            $this->assertFalse($resource::shouldRegisterNavigation(), $resource.' should stay hidden from navigation until T3.6.');
            $this->assertFalse($items[$resource]['visible']);
            $this->assertSame(0, count(Filament::getNavigationItems()['resources'][$resource] ?? []));
        }

        // Hiding navigation must not remove access or routes.
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->get(DetectionResultResource::getUrl('index'))->assertOk();
        $this->get(SubmissionProcessingEventResource::getUrl('index'))->assertOk();
    }

    public function test_official_labels_replace_english_resource_names(): void
    {
        $items = $this->navigationItems();
        $labels = $items->filter(fn (array $item): bool => $item['visible'])->pluck('label', 'resource')->all();

        $this->assertSame('Pemeriksaan', $labels[SubmissionResource::class]);
        $this->assertSame('Umpan Balik', $labels[FeedbackResource::class]);
        $this->assertSame('Log Admin', $labels[AdminLogResource::class]);
        $this->assertSame('Data Kurasi', $labels[DatasetResource::class]);
        $this->assertSame('Versi Model', $labels[ModelVersionResource::class]);

        foreach (['Submission', 'Feedback', 'Admin Logs', 'Katalog Dataset', 'Versi Model BERT', 'Monitoring'] as $old) {
            $this->assertNotContains($old, $labels, $old.' must not remain as a navigation label or group.');
        }

        $hiddenGroups = $items->filter(fn (array $item): bool => ! $item['visible'])->pluck('group')->filter()->all();
        $this->assertNotContains('Monitoring', $hiddenGroups, 'The stray Monitoring group must not survive.');
    }

    public function test_admin_navigation_renders_three_groups_with_indonesian_labels(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $html = $this->get(Filament::getPanel('admin')->getUrl())->assertOk()->getContent();

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
            $xpath = new \DOMXPath($dom);
            $pageText = $xpath->evaluate('string(//body)');
            $navigationText = $xpath->evaluate('string(//nav)');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        foreach (['Operasional', 'Model AI', 'Pengguna & Keamanan', 'Periksa', 'Pemeriksaan', 'Umpan Balik', 'Versi Model', 'Data Kurasi', 'Pengguna', 'Log Admin'] as $label) {
            $this->assertStringContainsString($label, $pageText, $label.' is missing from the admin navigation.');
        }
        foreach (['Submission', 'Feedback', 'Katalog Dataset', 'Versi Model BERT', 'Admin Logs', 'Processing Events', 'Hasil Deteksi', 'Monitoring'] as $stale) {
            $this->assertStringNotContainsString($stale, $navigationText, $stale.' must not appear in the admin navigation.');
        }
    }

    public function test_dashboard_is_the_first_operational_item(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(Filament::getPanel('admin')->getUrl())->assertOk();

        $groups = collect(Filament::getNavigation());
        $flattened = collect();
        foreach ($groups as $group) {
            foreach ($group->getItems() as $item) {
                $flattened->push([
                    'group' => $group->getLabel(),
                    'label' => $item->getLabel(),
                    'sort' => (int) $item->getSort(),
                ]);
            }
        }
        $operational = $flattened->where('group', 'Operasional')->sortBy('sort')->values();

        $this->assertSame('Periksa', $operational->first()['label'], 'Dashboard must be the first Operasional item.');
        $this->assertSame(
            ['Periksa', 'Pemeriksaan', 'Umpan Balik'],
            $operational->pluck('label')->all(),
            'Operasional items must follow the defined order.',
        );
    }

    /** @return Collection<int, array{resource: class-string, label: string, group: ?string, sort: ?int, icon: string, visible: bool}> */
    private function navigationItems(): Collection
    {
        $resources = [
            AdminLogResource::class => AdminLog::class,
            DatasetResource::class => Dataset::class,
            DetectionResultResource::class => DetectionResult::class,
            FeedbackResource::class => Feedback::class,
            ModelVersionResource::class => ModelVersion::class,
            SubmissionProcessingEventResource::class => SubmissionProcessingEvent::class,
            SubmissionResource::class => Submission::class,
            UserResource::class => User::class,
        ];

        $items = collect($resources)->map(fn (string $model, string $resource): array => [
            'resource' => $resource,
            'label' => $resource::getNavigationLabel(),
            'group' => $resource::getNavigationGroup(),
            'sort' => $resource::getNavigationSort(),
            'icon' => $resource::getNavigationIcon() instanceof \BackedEnum
                ? (string) $resource::getNavigationIcon()->value
                : (string) $resource::getNavigationIcon(),
            'visible' => $resource::shouldRegisterNavigation(),
        ]);

        return $items->push([
            'resource' => Dashboard::class,
            'label' => Dashboard::getNavigationLabel(),
            'group' => Dashboard::getNavigationGroup(),
            'sort' => Dashboard::getNavigationSort(),
            'icon' => Dashboard::getNavigationIcon() instanceof \BackedEnum
                ? (string) Dashboard::getNavigationIcon()->value
                : (string) Dashboard::getNavigationIcon(),
            'visible' => Dashboard::shouldRegisterNavigation(),
        ])->sortBy(fn (array $item): int => (int) ($item['sort'] ?? 0))->values();
    }
}
