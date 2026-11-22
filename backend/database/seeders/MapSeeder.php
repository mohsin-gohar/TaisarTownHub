<?php

namespace Database\Seeders;

use App\Models\Map;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MapSeeder extends Seeder
{
    /**
     * Import the 11 extracted Taiser Town map images into the maps table.
     *
     * Titles/categories are neutral placeholders — the Super Admin can edit
     * them later from the Map Management dashboard.
     */
    public function run(): void
    {
        $maps = [
            [
                'title' => 'Overall Taiser Town Map',
                'sector' => null,
                'phase' => null,
                'category' => 'overall_map',
                'description' => 'Complete overview map of Taiser Town Scheme 45, showing all phases, sectors, and major landmarks.',
                'image' => 'taiser-town-map-page-01.jpg',
                'order' => 1,
                'featured' => true,
            ],
            [
                'title' => 'Sector Map — Page 2',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-02.jpg',
                'order' => 2,
                'featured' => false,
            ],
            [
                'title' => 'Sector Map — Page 3',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-03.jpg',
                'order' => 3,
                'featured' => false,
            ],
            [
                'title' => 'Sector Map — Page 4',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-04.jpg',
                'order' => 4,
                'featured' => false,
            ],
            [
                'title' => 'Sector Map — Page 5',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-05.jpg',
                'order' => 5,
                'featured' => false,
            ],
            [
                'title' => 'Sector Map — Page 6',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-06.jpg',
                'order' => 6,
                'featured' => false,
            ],
            [
                'title' => 'Sector Map — Page 7',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-07.jpg',
                'order' => 7,
                'featured' => false,
            ],
            [
                'title' => 'Sector Map — Page 8',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-08.jpg',
                'order' => 8,
                'featured' => false,
            ],
            [
                'title' => 'Sector Map — Page 9',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-09.jpg',
                'order' => 9,
                'featured' => false,
            ],
            [
                'title' => 'Sector Map — Page 10',
                'sector' => null,
                'phase' => null,
                'category' => 'sector_map',
                'description' => 'Detailed sector-level map. Edit the sector name and description from the Map Management dashboard.',
                'image' => 'taiser-town-map-page-10.jpg',
                'order' => 10,
                'featured' => false,
            ],
            [
                'title' => 'Taiser Town Guide Map',
                'sector' => null,
                'phase' => null,
                'category' => 'guide_map',
                'description' => 'Guide map showing overall layout, key landmarks, and navigation reference for Taiser Town.',
                'image' => 'taiser-town-map-page-11.jpg',
                'order' => 11,
                'featured' => false,
            ],
        ];

        $admin = User::where('role', 'super_admin')->first();

        foreach ($maps as $data) {
            $imagePath = 'maps/' . $data['image'];
            if (!Storage::disk('public')->exists($imagePath)) {
                $this->command->warn("Image not found: {$imagePath}, skipping.");
                continue;
            }

            $fullPath = Storage::disk('public')->path($imagePath);
            $dims = @getimagesize($fullPath);
            $width = $dims ? $dims[0] : null;
            $height = $dims ? $dims[1] : null;
            $size = filesize($fullPath);
            $mime = mime_content_type($fullPath) ?: 'image/jpeg';
            $checksum = hash_file('sha256', $fullPath);

            Map::firstOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'id' => 'map_' . bin2hex(random_bytes(8)),
                    'title' => $data['title'],
                    'slug' => Str::slug($data['title']),
                    'sector' => $data['sector'],
                    'phase' => $data['phase'],
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'image_path' => $imagePath,
                    'image_disk' => 'public',
                    'image_mime' => $mime,
                    'image_size_bytes' => $size,
                    'image_width' => $width,
                    'image_height' => $height,
                    'image_checksum' => $checksum,
                    'display_order' => $data['order'],
                    'is_active' => true,
                    'is_featured' => $data['featured'],
                    'created_by' => $admin?->id,
                ]
            );
        }

        $this->command->info('Maps seeded: ' . Map::count() . ' records.');
    }
};
