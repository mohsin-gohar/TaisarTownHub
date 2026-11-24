<?php

namespace Tests\Feature;

use App\Models\BusinessRecord;

/**
 * The public `/api/catalog` endpoint is unauthenticated. Every collection it
 * exposes must be projected through an explicit public allowlist so internal
 * PII, financial data and tenancy columns can never leak.
 */
class PublicCatalogPrivacyTest extends EnterpriseTestCase
{
    private function item(array $items, string $id): array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        $this->fail("Catalog item {$id} was not returned.");
    }

    private function assertKeysAbsent(array $item, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertArrayNotHasKey($key, $item, "Public catalog leaked '{$key}'.");
        }
    }

    public function test_dealer_catalog_uses_a_public_allowlist(): void
    {
        BusinessRecord::put('dealers', 'dealer-public', [
            'id' => 'dealer-public',
            'fullName' => 'Public Dealer',
            'avatar' => 'https://example.test/avatar.jpg',
            'role' => 'Senior Consultant',
            'agencyOrBranch' => 'Public Agency',
            'dealsClosed' => 12,
            'status' => 'Active',
            'cnic' => '35202-1234567-8',
            'cnicMasked' => '35202-*******-8',
            'phone' => '03001234567',
            'whatsApp' => '03001234567',
            'email' => 'dealer@example.test',
            'commissionType' => 'Percentage',
            'commissionRate' => 2,
            'commissionValue' => 2,
            'totalEarnedPKR' => 900000,
            'pendingCommissionPKR' => 100000,
            'accountNumber' => '1234567890',
            'iban' => 'PK00TEST0000000000000000',
            'internalNotes' => 'private note',
            'notes' => 'private note',
            'agency_id' => 'agency-secret',
            'created_by' => 99,
        ]);

        $dealer = $this->item($this->getJson('/api/catalog')->assertOk()->json('dealers'), 'dealer-public');

        $this->assertSame('Public Dealer', $dealer['fullName']);
        $this->assertSame('Public Agency', $dealer['agencyOrBranch']);
        $this->assertSame(12, $dealer['dealsClosed']);
        $this->assertKeysAbsent($dealer, [
            'cnic', 'cnicMasked', 'phone', 'whatsApp', 'email',
            'commissionType', 'commissionRate', 'commissionValue',
            'totalEarnedPKR', 'pendingCommissionPKR', 'accountNumber', 'iban',
            'internalNotes', 'notes', 'agency_id', 'created_by',
        ]);
    }

    public function test_video_catalog_strips_internal_fields_and_comment_pii(): void
    {
        BusinessRecord::put('videos', 'video-public', [
            'id' => 'video-public',
            'title' => 'Public walkthrough',
            'description' => 'Safe description',
            'videoUrl' => 'https://example.test/video.mp4',
            'thumbnailUrl' => 'https://example.test/thumb.jpg',
            'agencyId' => 'agency-1',
            'agencyName' => 'Public Agency',
            'dealerName' => 'Public Dealer',
            'dealerAvatar' => 'https://example.test/dealer.jpg',
            'phase' => 'Phase 1',
            'sector' => 'Sector 70-D',
            'duration' => '3:45',
            'uploadedAt' => '2 days ago',
            'likes' => 10,
            'views' => 100,
            'tags' => ['possession'],
            'comments' => [[
                'id' => 'comment-1',
                'userName' => 'Visitor',
                'comment' => 'Great video',
                'createdAt' => '2026-01-01',
                'userAvatar' => 'https://example.test/visitor.jpg',
                'isDealer' => false,
                'email' => 'visitor@example.test',
                'userId' => 'user-secret',
                'phone' => '03001234567',
            ]],
            'likedBy' => ['user-secret'],
            'authorId' => 'user-secret',
            'internalNotes' => 'private note',
            'notes' => 'private note',
            'agency_id' => 'agency-secret',
            'created_by' => 99,
        ]);

        $video = $this->item($this->getJson('/api/catalog')->assertOk()->json('videos'), 'video-public');

        $this->assertSame('Public walkthrough', $video['title']);
        $this->assertSame('Great video', $video['comments'][0]['comment']);
        $this->assertKeysAbsent($video, [
            'likedBy', 'authorId', 'internalNotes', 'notes', 'agency_id', 'created_by',
        ]);
        $this->assertKeysAbsent($video['comments'][0], ['email', 'userId', 'phone']);
    }

    public function test_plot_catalog_strips_owner_and_internal_fields(): void
    {
        BusinessRecord::put('plots', 'plot-public', [
            'id' => 'plot-public',
            'plotNumber' => 'R-66',
            'sector' => 'Sector 63',
            'sizeSqYards' => 120,
            'status' => 'Available',
            'pricePKR' => 5000000,
            'roadWidth' => '100ft Road',
            'propertyId' => 'property-1',
            'ownerName' => 'Private Owner',
            'owner_name' => 'Private Owner',
            'ownerPhone' => '03001234567',
            'markerLabel' => 'Private Owner',
            'buyerName' => 'Private Buyer',
            'internalNotes' => 'private note',
            'notes' => 'private note',
            'agency_id' => 'agency-secret',
            'created_by' => 99,
        ]);

        $plot = $this->item($this->getJson('/api/catalog')->assertOk()->json('plots'), 'plot-public');

        $this->assertSame('R-66', $plot['plotNumber']);
        $this->assertSame(5000000, $plot['pricePKR']);
        $this->assertKeysAbsent($plot, [
            'ownerName', 'owner_name', 'ownerPhone', 'markerLabel', 'buyerName',
            'internalNotes', 'notes', 'agency_id', 'created_by',
        ]);
    }

    public function test_testimonial_catalog_uses_a_public_allowlist_and_hides_private_rows(): void
    {
        BusinessRecord::put('testimonials', 'testimonial-public', [
            'id' => 'testimonial-public',
            'clientName' => 'Happy Client',
            'designation' => 'Investor',
            'quote' => 'Excellent service',
            'avatar' => 'https://example.test/client.jpg',
            'propertyPurchased' => 'Sector 70-D',
            'rating' => 5,
            'isVerifiedDemo' => true,
            'email' => 'client@example.test',
            'phone' => '03001234567',
            'internalNotes' => 'private note',
            'adminNotes' => 'private note',
            'userId' => 'user-secret',
            'agency_id' => 'agency-secret',
            'created_by' => 99,
        ]);
        BusinessRecord::put('testimonials', 'testimonial-private', [
            'id' => 'testimonial-private',
            'clientName' => 'Private Client',
            'quote' => 'Private quote',
            'visibility' => 'private',
        ]);
        BusinessRecord::put('testimonials', 'testimonial-draft', [
            'id' => 'testimonial-draft',
            'clientName' => 'Draft Client',
            'quote' => 'Draft quote',
            'approval_status' => 'pending',
        ]);

        $testimonials = $this->getJson('/api/catalog')->assertOk()->json('testimonials');
        $ids = array_column($testimonials, 'id');

        $this->assertContains('testimonial-public', $ids);
        $this->assertNotContains('testimonial-private', $ids);
        $this->assertNotContains('testimonial-draft', $ids);

        $testimonial = $this->item($testimonials, 'testimonial-public');
        $this->assertSame('Happy Client', $testimonial['clientName']);
        $this->assertKeysAbsent($testimonial, [
            'email', 'phone', 'internalNotes', 'adminNotes', 'userId',
            'agency_id', 'created_by',
        ]);
    }

    public function test_cms_catalog_uses_a_public_allowlist(): void
    {
        BusinessRecord::put('cms', 'default', [
            'agencyName' => 'Public Agency',
            'contactEmail' => 'hello@example.test',
            'contactPhone' => '03001234567',
            'heroHeadline' => 'Public headline',
            'internalNotes' => 'private note',
            'adminEmail' => 'admin@example.test',
            'secretKey' => 'super-secret',
        ]);

        $cms = $this->getJson('/api/catalog')->assertOk()->json('cms');

        $this->assertSame('Public Agency', $cms['agencyName']);
        $this->assertSame('Public headline', $cms['heroHeadline']);
        $this->assertKeysAbsent($cms, ['internalNotes', 'adminEmail', 'secretKey']);
    }

    public function test_property_catalog_never_exposes_private_seller_or_financial_fields(): void
    {
        $agency = $this->makeAgency('Catalog Property Agency');

        BusinessRecord::put('properties', 'property-public', [
            'id' => 'property-public',
            'title' => 'Public property',
            'pricePKR' => 10000000,
            'approval_status' => 'approved',
            'visibility' => 'public',
            'listing_purpose' => 'sale',
            'sellerId' => 'seller-secret',
            'sellerPhone' => '03001234567',
            'sellerCnic' => '35202-1234567-8',
            'internalNotes' => 'private note',
            'commissionRate' => 2,
            'agency_id' => 'agency-secret',
            'created_by' => 99,
        ], $agency->id);

        $property = $this->item($this->getJson('/api/catalog')->assertOk()->json('properties'), 'property-public');

        $this->assertSame('Public property', $property['title']);
        $this->assertKeysAbsent($property, [
            'sellerId', 'sellerPhone', 'sellerCnic', 'internalNotes',
            'commissionRate', 'agency_id', 'created_by',
        ]);
    }

    public function test_media_catalog_strips_sale_attribution_and_internal_fields(): void
    {
        BusinessRecord::put('media', 'media-public', [
            'id' => 'media-public',
            'title' => 'Public photo',
            'url' => 'https://example.test/photo.jpg',
            'type' => 'image',
            'folder' => 'General Photos',
            'visibility' => 'public',
            'soldPricePKR' => 500000,
            'basePricePKR' => 400000,
            'profitPKR' => 100000,
            'buyerName' => 'Private Buyer',
            'buyerPhone' => '03001234567',
            'buyerNationalIdMasked' => '35202-*******-8',
            'soldBy' => 'staff-secret',
            'internalNotes' => 'private note',
            'agency_id' => 'agency-secret',
        ]);

        $media = $this->item($this->getJson('/api/catalog')->assertOk()->json('media'), 'media-public');

        $this->assertSame('Public photo', $media['title']);
        $this->assertSame('image', $media['type']);
        $this->assertKeysAbsent($media, [
            'soldPricePKR', 'basePricePKR', 'profitPKR', 'buyerName', 'buyerPhone',
            'buyerNationalIdMasked', 'soldBy', 'internalNotes', 'agency_id',
        ]);
    }

    public function test_catalog_excludes_inactive_or_private_non_property_rows(): void
    {
        BusinessRecord::put('dealers', 'dealer-inactive', [
            'id' => 'dealer-inactive',
            'fullName' => 'Inactive Dealer',
            'status' => 'Inactive',
        ]);
        BusinessRecord::put('dealers', 'dealer-private', [
            'id' => 'dealer-private',
            'fullName' => 'Private Dealer',
            'visibility' => 'private',
        ]);
        BusinessRecord::put('videos', 'video-private', [
            'id' => 'video-private',
            'title' => 'Private video',
            'visibility' => 'private',
        ]);

        $catalog = $this->getJson('/api/catalog')->assertOk()->json();

        $this->assertNotContains('dealer-inactive', array_column($catalog['dealers'], 'id'));
        $this->assertNotContains('dealer-private', array_column($catalog['dealers'], 'id'));
        $this->assertNotContains('video-private', array_column($catalog['videos'], 'id'));
    }
}
