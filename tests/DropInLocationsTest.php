<?php

use PHPUnit\Framework\TestCase;
use TotaraInstaller\installers\DropInLocations;

/**
 * Test harness exposing the trait's protected static helper.
 */
class DropInLocationsHarness {
    use DropInLocations;

    public static function call(string $package_type): ?string {
        return self::getLocationFromPackageType($package_type);
    }
}

final class DropInLocationsTest extends TestCase {

    public function testReturnsTheMappedLocationForAKnownType(): void {
        $this->assertSame('server/blocks/{$name}', DropInLocationsHarness::call('totara-block'));
        $this->assertSame('client/component/{$name}', DropInLocationsHarness::call('totara-client'));
        $this->assertSame('server/mailer/{$name}', DropInLocationsHarness::call('totara-mailer'));
    }

    public function testReturnsNullForAnUnknownTotaraType(): void {
        $this->assertNull(DropInLocationsHarness::call('totara-nonexistent'));
    }

    public function testReturnsNullWhenPrefixIsNotTotara(): void {
        $this->assertNull(DropInLocationsHarness::call('library'));
        $this->assertNull(DropInLocationsHarness::call('composer-plugin'));
    }

    public function testDoesNotMatchWhenTypeHasUnexpectedCharacters(): void {
        // The pattern only allows lower-case letters after the "totara-" prefix.
        $this->assertNull(DropInLocationsHarness::call('totara-Block'));
        $this->assertNull(DropInLocationsHarness::call('totara-block-extra'));
        $this->assertNull(DropInLocationsHarness::call('totara-'));
    }
}
