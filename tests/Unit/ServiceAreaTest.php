<?php

namespace Tests\Unit;

use App\Services\ServiceArea;
use PHPUnit\Framework\TestCase;

class ServiceAreaTest extends TestCase
{
    public function test_menu_order_inside_san_jose_mainland_is_deliverable(): void
    {
        $this->assertNull(ServiceArea::menuDeliveryError('Occidental Mindoro', 'San Jose', 'Caminawit'));
    }

    public function test_menu_order_rejects_ilin_island_barangay(): void
    {
        $error = ServiceArea::menuDeliveryError('Occidental Mindoro', 'San Jose', 'Ansiray');
        $this->assertNotNull($error);
        $this->assertStringContainsString('boat', $error);
    }

    public function test_menu_order_rejects_ambulong(): void
    {
        $error = ServiceArea::menuDeliveryError('Occidental Mindoro', 'San Jose', 'Ambulong');
        $this->assertNotNull($error);
        $this->assertStringContainsString('boat', $error);
    }

    public function test_menu_order_rejects_a_different_municipality(): void
    {
        $error = ServiceArea::menuDeliveryError('Occidental Mindoro', 'Sablayan', 'Poblacion');
        $this->assertNotNull($error);
        $this->assertStringContainsString('San Jose', $error);
    }

    public function test_menu_order_rejects_oriental_mindoro(): void
    {
        $error = ServiceArea::menuDeliveryError('Oriental Mindoro', 'Calapan City', 'Balingayan');
        $this->assertNotNull($error);
    }

    public function test_menu_order_rejects_a_barangay_that_does_not_exist(): void
    {
        $error = ServiceArea::menuDeliveryError('Occidental Mindoro', 'San Jose', 'Not A Real Barangay');
        $this->assertSame('Please choose a valid barangay.', $error);
    }

    public function test_catering_accepts_occidental_mindoro_outside_san_jose(): void
    {
        $this->assertNull(ServiceArea::cateringDeliveryError('Occidental Mindoro', 'Mamburao', 'Poblacion 1'));
    }

    public function test_catering_accepts_oriental_mindoro(): void
    {
        $this->assertNull(ServiceArea::cateringDeliveryError('Oriental Mindoro', 'Calapan City', 'Balingayan'));
    }

    public function test_catering_rejects_lubang_municipality_entirely(): void
    {
        $error = ServiceArea::cateringDeliveryError('Occidental Mindoro', 'Lubang', 'Tilik');
        $this->assertNotNull($error);
        $this->assertStringContainsString('boat', $error);
    }

    public function test_catering_rejects_looc_municipality_entirely(): void
    {
        $error = ServiceArea::cateringDeliveryError('Occidental Mindoro', 'Looc', 'Agkawayan');
        $this->assertNotNull($error);
        $this->assertStringContainsString('boat', $error);
    }

    public function test_catering_rejects_ilin_island_even_though_san_jose_is_otherwise_included(): void
    {
        $error = ServiceArea::cateringDeliveryError('Occidental Mindoro', 'San Jose', 'Catayungan');
        $this->assertNotNull($error);
        $this->assertStringContainsString('boat', $error);
    }

    public function test_catering_rejects_a_province_outside_mindoro(): void
    {
        $error = ServiceArea::cateringDeliveryError('Batangas', 'Batangas City', 'Poblacion');
        $this->assertNotNull($error);
    }

    public function test_catering_rejects_a_municipality_that_does_not_exist_in_the_given_province(): void
    {
        $error = ServiceArea::cateringDeliveryError('Occidental Mindoro', 'Calapan City', 'Balingayan');
        $this->assertSame('Please choose a valid municipality.', $error);
    }

    public function test_menu_barangays_excludes_all_eleven_boat_only_entries(): void
    {
        $barangays = ServiceArea::menuBarangays();
        $this->assertNotContains('Ansiray', $barangays);
        $this->assertNotContains('Ambulong', $barangays);
        $this->assertContains('Caminawit', $barangays);
        // 39 total in San Jose, minus 10 Ilin Island barangays, minus Ambulong.
        $this->assertCount(28, $barangays);
    }

    public function test_catering_municipalities_excludes_lubang_and_looc(): void
    {
        $municipalities = ServiceArea::cateringMunicipalities('Occidental Mindoro');
        $this->assertNotContains('Lubang', $municipalities);
        $this->assertNotContains('Looc', $municipalities);
        $this->assertContains('San Jose', $municipalities);
    }
}
