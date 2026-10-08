<?php

namespace App\Native;

use Symfony\UX\Native\Attribute\AsNativeConfiguration;
use Symfony\UX\Native\Attribute\AsNativeConfigurationProvider;
use Symfony\UX\Native\Configuration\Configuration;
use Symfony\UX\Native\Configuration\Rule;

/**
 * How the Hotwire Native Android app (android/) presents each page.
 *
 * Served on the fly in dev; written to public/ for production by `ux:native:build-configs`.
 * Rules are cumulative: a later matching rule overrides the properties of earlier ones.
 */
#[AsNativeConfigurationProvider]
final class NativeConfiguration
{
    public const ANDROID_PATH = '/native/android_v1.json';

    #[AsNativeConfiguration(self::ANDROID_PATH)]
    public function android(): Configuration
    {
        return new Configuration(rules: [
            new Rule(patterns: ['.*'], properties: [
                'context' => 'default',
                'uri' => 'hotwire://fragment/web',
                'pull_to_refresh_enabled' => true,
            ]),
            // The bottom navigation and the login switch screens instead of piling them up
            // (including the review's other weeks, and its ± buttons that redirect to a dated week).
            new Rule(patterns: ['^/?$', '^/taches/?(\?.*)?$', '^/plan$', '^/bilan', '^/profil$', '^/connexion', '^/deconnexion'], properties: [
                'presentation' => 'replace_root',
            ]),
            // Task (and completion) forms slide up as modals; their close button recedes to the screen underneath.
            new Rule(patterns: ['^/taches/nouvelle', '^/taches/\d+/modifier', '^/realisations/\d+/modifier'], properties: [
                'context' => 'modal',
                'pull_to_refresh_enabled' => false,
            ]),
        ]);
    }
}
