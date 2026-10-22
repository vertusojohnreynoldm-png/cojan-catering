<?php

namespace App\Services;

/**
 * Single source of truth for "can we deliver here" — wraps the static PSGC
 * list (database/data/psgc.php) and the boat-only exclusion list
 * (database/data/excluded_barangays.php). Both the dropdown options shown to
 * the browser AND the server-side re-validation of the submitted address
 * read through this class, so they can never drift apart.
 */
class ServiceArea
{
    public const MENU_PROVINCE = 'Occidental Mindoro';
    public const MENU_MUNICIPALITY = 'San Jose';

    public const CATERING_PROVINCES = ['Occidental Mindoro', 'Oriental Mindoro'];

    private static ?array $psgc = null;
    private static ?array $exclusions = null;

    public static function provinces(): array
    {
        return array_keys(self::psgc());
    }

    public static function municipalities(string $province): array
    {
        return array_keys(self::psgc()[$province] ?? []);
    }

    public static function barangays(string $province, string $municipality): array
    {
        return self::psgc()[$province][$municipality] ?? [];
    }

    // Barangays a customer can actually pick for a regular menu order —
    // San Jose's list minus the boat-only ones.
    public static function menuBarangays(): array
    {
        return array_values(array_filter(
            self::barangays(self::MENU_PROVINCE, self::MENU_MUNICIPALITY),
            fn ($barangay) => !self::isExcludedBarangay(self::MENU_PROVINCE, self::MENU_MUNICIPALITY, $barangay)
        ));
    }

    // Municipalities a customer can pick for a catering booking, within one
    // province — excludes wholly boat-only municipalities (Lubang, Looc).
    public static function cateringMunicipalities(string $province): array
    {
        return array_values(array_filter(
            self::municipalities($province),
            fn ($municipality) => !self::isExcludedMunicipality($province, $municipality)
        ));
    }

    // Barangays a customer can pick for a catering booking, within one
    // province + municipality — excludes boat-only barangays (Ilin,
    // Ambulong) within an otherwise-included municipality.
    public static function cateringBarangays(string $province, string $municipality): array
    {
        return array_values(array_filter(
            self::barangays($province, $municipality),
            fn ($barangay) => !self::isExcludedBarangay($province, $municipality, $barangay)
        ));
    }

    /**
     * Validates a regular menu order's delivery area server-side. Returns
     * null when deliverable, or a friendly message to show the customer.
     * Never trusts what the browser sent, even if the dropdown it rendered
     * should have made an invalid combination impossible.
     */
    public static function menuDeliveryError(?string $province, ?string $municipality, ?string $barangay): ?string
    {
        if ($province !== self::MENU_PROVINCE || $municipality !== self::MENU_MUNICIPALITY) {
            return 'Menu orders are delivered only within San Jose, Occidental Mindoro. For other areas, you can book a catering package.';
        }

        if (!in_array($barangay, self::barangays($province, $municipality), true)) {
            return 'Please choose a valid barangay.';
        }

        if (self::isExcludedBarangay($province, $municipality, $barangay)) {
            return "Sorry, we can't deliver to this area yet because it can only be reached by boat.";
        }

        return null;
    }

    /**
     * Validates a catering booking's delivery area server-side. Returns null
     * when deliverable, or a friendly message to show the customer.
     */
    public static function cateringDeliveryError(?string $province, ?string $municipality, ?string $barangay): ?string
    {
        if (!in_array($province, self::CATERING_PROVINCES, true)) {
            return 'We deliver catering only within Occidental Mindoro or Oriental Mindoro.';
        }

        if (!isset(self::psgc()[$province][$municipality])) {
            return 'Please choose a valid municipality.';
        }

        if (self::isExcludedMunicipality($province, $municipality)) {
            return "Sorry, we can't deliver to this area yet because it can only be reached by boat.";
        }

        if (!in_array($barangay, self::barangays($province, $municipality), true)) {
            return 'Please choose a valid barangay.';
        }

        if (self::isExcludedBarangay($province, $municipality, $barangay)) {
            return "Sorry, we can't deliver to this area yet because it can only be reached by boat.";
        }

        return null;
    }

    // The full PSGC tree, minus excluded municipalities and barangays —
    // exactly what the catering Avail form's cascading dropdowns are built
    // from (embedded as JSON, no runtime API call).
    public static function cateringPsgcForDropdowns(): array
    {
        $tree = [];
        foreach (self::CATERING_PROVINCES as $province) {
            foreach (self::cateringMunicipalities($province) as $municipality) {
                $tree[$province][$municipality] = self::cateringBarangays($province, $municipality);
            }
        }

        return $tree;
    }

    // Builds the one readable address string every existing page/email
    // already displays (Order::delivery_address / User::address) from the
    // structured fields — written at the same time as the structured
    // columns so nothing downstream needs to know the address is now
    // structured at all.
    public static function formatAddress(
        string $street,
        string $barangay,
        string $municipality,
        string $province,
        ?string $landmark = null
    ): string {
        $address = "{$street}, Barangay {$barangay}, {$municipality}, {$province}";

        if ($landmark !== null && trim($landmark) !== '') {
            $address .= " (near {$landmark})";
        }

        return $address;
    }

    private static function isExcludedMunicipality(string $province, string $municipality): bool
    {
        foreach (self::exclusions()['municipalities'] as $excluded) {
            if ($excluded['province'] === $province && $excluded['municipality'] === $municipality) {
                return true;
            }
        }

        return false;
    }

    private static function isExcludedBarangay(string $province, string $municipality, string $barangay): bool
    {
        foreach (self::exclusions()['barangays'] as $excluded) {
            if ($excluded['province'] === $province
                && $excluded['municipality'] === $municipality
                && $excluded['barangay'] === $barangay) {
                return true;
            }
        }

        return false;
    }

    // Resolved relative to this file rather than via database_path(), so
    // this class stays pure PHP — testable in a plain PHPUnit TestCase
    // without booting the framework, since it touches no DB/Eloquent.
    private static function psgc(): array
    {
        return self::$psgc ??= require dirname(__DIR__, 2) . '/database/data/psgc.php';
    }

    private static function exclusions(): array
    {
        return self::$exclusions ??= require dirname(__DIR__, 2) . '/database/data/excluded_barangays.php';
    }
}
