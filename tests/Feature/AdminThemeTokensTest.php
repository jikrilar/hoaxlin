<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** T3.1: the admin panel keeps the shared design tokens and palette in sync. */
class AdminThemeTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_colours_are_drawn_from_the_design_tokens(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $colors = Filament::getPanel('admin')->getColors();
        $tokens = file_get_contents(resource_path('css/design-tokens.css'));
        preg_match_all('/--p-[a-z0-9-]+:\s*(#[0-9a-f]{6})/i', $tokens, $matches);
        $palette = array_map('strtolower', $matches[1]);

        $this->assertArrayHasKey('primary', $colors);
        $this->assertArrayHasKey('gray', $colors);
        foreach (['primary', 'gray'] as $scale) {
            // Filament normalises integer shades to their string keys.
            $this->assertSame(
                array_map('strval', ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950']),
                array_map('strval', array_keys($colors[$scale])),
            );
            foreach ($colors[$scale] as $shade => $hex) {
                $this->assertContains(strtolower($hex), $palette, "colors.{$scale}.{$shade} ({$hex}) must exist in design-tokens.css");
            }
        }
        $this->assertSame('#235c50', $colors['primary'][600]);
        $this->assertSame('#1f2b29', $colors['gray'][900]);
    }

    public function test_panel_uses_the_local_inter_font_and_light_mode_only(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('Inter', $panel->getFontFamily());
        $this->assertFalse($panel->hasDarkMode());
        $this->assertSame('hoaxlin.id', $panel->getBrandName());
    }

    public function test_admin_theme_stylesheet_is_the_vite_entry_and_keeps_tokens(): void
    {
        $panel = Filament::getPanel('admin');
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertSame('resources/css/filament/admin/theme.css', $panel->getViteTheme());
        $this->assertStringContainsString("@import '../../design-tokens.css'", $theme);
        $this->assertStringContainsString('var(--ds-type-h2-size)', $theme);
        $this->assertStringContainsString('var(--ds-motion-fast)', $theme);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $theme);
        // Vendor assets are imported, never edited in place.
        $this->assertFileExists(base_path('vendor/filament/filament/resources/css/theme.css'));
        $this->assertStringNotContainsString('resources/css/app.css', $theme);
    }
}
