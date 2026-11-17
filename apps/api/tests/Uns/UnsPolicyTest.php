<?php

declare(strict_types=1);

namespace App\Tests\Uns;

use App\Uns\UnsPolicy;
use PHPUnit\Framework\TestCase;

final class UnsPolicyTest extends TestCase
{
    public function testProductionWithoutAllowListIsDisabled(): void
    {
        $policy = UnsPolicy::fromEnv(['APP_ENV' => 'prod']);

        self::assertFalse($policy->isConfigured());
        self::assertFalse($policy->allows('acme/troyes'));
    }

    public function testDevelopmentWithoutAllowListAcceptsAnyValidPrefix(): void
    {
        $policy = UnsPolicy::fromEnv(['APP_ENV' => 'dev']);

        self::assertTrue($policy->allows('acme/troyes'));
        self::assertFalse($policy->allows('scada/atelier'), 'le contrat historique reste réservé');
        self::assertFalse($policy->allows('acme/#'));
        self::assertFalse($policy->allows('acme/+/line'));
        self::assertFalse($policy->allows(''));
    }

    public function testAllowListCoversRootsAndTheirDescendantsOnly(): void
    {
        $policy = UnsPolicy::fromEnv(['APP_ENV' => 'prod', 'UNS_ALLOWED_PREFIXES' => 'acme/troyes, other/ ,bad/#']);

        self::assertTrue($policy->isConfigured());
        self::assertTrue($policy->allows('acme/troyes'));
        self::assertTrue($policy->allows('acme/troyes/packaging/line1'));
        self::assertTrue($policy->allows('other/site'));
        self::assertFalse($policy->allows('acme/troyes-bis'), 'un préfixe commun de caractères n\'est pas un descendant');
        self::assertFalse($policy->allows('acme'));
        self::assertFalse($policy->allows('bad/x'), 'une racine invalide est ignorée');
    }
}
