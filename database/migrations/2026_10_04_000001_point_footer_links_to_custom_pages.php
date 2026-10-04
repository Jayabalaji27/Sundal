<?php

use App\Models\LandingPageCustomPage;
use App\Models\LandingPageSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * The default footer links "About Us", "Help Center" and "Privacy Policy"
 * pointed at in-page anchors (#about, #help, #privacy) that don't exist on
 * most pages, while the real content lives on the seeded custom pages. Footer
 * links can't be edited in the settings UI, so update the stored defaults.
 * Only links still set to the old anchor are changed, and only when the
 * matching custom page exists.
 */
return new class extends Migration
{
    private const LINKS = [
        '#about' => 'about-us',
        '#help' => 'help-support',
        '#privacy' => 'privacy-policy',
    ];

    public function up(): void
    {
        $existing = LandingPageCustomPage::whereIn('slug', self::LINKS)->pluck('slug')->all();

        $this->rewriteFooterLinks(function (string $href) use ($existing) {
            $slug = self::LINKS[$href] ?? null;

            return $slug && in_array($slug, $existing, true) ? '/page/' . $slug : null;
        });
    }

    public function down(): void
    {
        $anchors = [];
        foreach (self::LINKS as $anchor => $slug) {
            $anchors['/page/' . $slug] = $anchor;
        }

        $this->rewriteFooterLinks(fn (string $href) => $anchors[$href] ?? null);
    }

    /** Replaces each footer link href for which $map returns a new value. */
    private function rewriteFooterLinks(callable $map): void
    {
        foreach (LandingPageSetting::all() as $setting) {
            $config = $setting->config_sections;
            $changed = false;

            foreach ($config['sections'] ?? [] as $i => $section) {
                if (($section['key'] ?? null) !== 'footer') {
                    continue;
                }
                foreach ($section['links'] ?? [] as $group => $links) {
                    foreach ($links as $j => $link) {
                        $new = isset($link['href']) ? $map($link['href']) : null;
                        if ($new !== null) {
                            $config['sections'][$i]['links'][$group][$j]['href'] = $new;
                            $changed = true;
                        }
                    }
                }
            }

            if ($changed) {
                $setting->config_sections = $config;
                $setting->save();
            }
        }
    }
};
