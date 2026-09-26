<?php

namespace Tests\Unit;

use App\Domain\PublicCatalog\PublicCatalogService;
use App\Models\RentalPackage;
use ReflectionMethod;
use Tests\TestCase;

class PublicCatalogBilingualContentTest extends TestCase
{
    public function test_id_keeps_original_and_en_uses_operator_translation(): void
    {
        $method = new ReflectionMethod(PublicCatalogService::class, 'localizedContent');
        $service = app(PublicCatalogService::class);
        app()->setLocale('id');
        $this->assertSame('Deskripsi kamera', $method->invoke($service, 'Deskripsi kamera', 'Camera description'));
        app()->setLocale('en');
        $this->assertSame('Camera description', $method->invoke($service, 'Deskripsi kamera', 'Camera description'));
    }

    public function test_package_content_falls_back_without_changing_package_identity(): void
    {
        $package = new RentalPackage([
            'name' => 'Paket Foto',
            'description' => 'Paket asli',
            'description_en' => 'Photo package',
            'seo_title' => 'Paket SEO',
            'seo_title_en' => null,
            'seo_description' => 'SEO asli',
            'seo_description_en' => 'English SEO',
        ]);
        $package->setRelation('items', collect());
        $package->setRelation('rates', collect());
        $service = app(PublicCatalogService::class);
        $mapper = new ReflectionMethod(PublicCatalogService::class, 'mapPackage');
        app()->setLocale('en');
        $mapped = $mapper->invoke($service, $package, [
            'id' => 1, 'name' => 'Cabang', 'whatsapp' => '',
        ], true);
        $this->assertSame('Paket Foto', $mapped['name']);
        $this->assertSame('Photo package', $mapped['description']);
        $this->assertSame('Paket SEO', $mapped['seo_title']);
        $this->assertSame('English SEO', $mapped['seo_description']);
        $package->description_en = null;
        $package->seo_description_en = null;
        $legacy = $mapper->invoke($service, $package, [
            'id' => 1, 'name' => 'Cabang', 'whatsapp' => '',
        ], true);
        $this->assertSame('Paket asli', $legacy['description']);
        $this->assertSame('SEO asli', $legacy['seo_description']);
    }

    public function test_en_falls_back_for_legacy_empty_and_whitespace_translations(): void
    {
        $method = new ReflectionMethod(PublicCatalogService::class, 'localizedContent');
        $service = app(PublicCatalogService::class);
        app()->setLocale('en');
        foreach ([null, '', '   '] as $untranslated) {
            $this->assertSame('Konten lama', $method->invoke($service, 'Konten lama', $untranslated));
        }
        $this->assertNull($method->invoke($service, null, null));
    }
}
