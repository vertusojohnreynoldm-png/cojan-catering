<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\MenuItem;

class CategoryAndMenuSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $appetizers = Category::create([
            'name'        => 'Appetizers',
            'description' => 'Start your meal right',
            'is_active'   => true,
        ]);

        $mainCourse = Category::create([
            'name'        => 'Main Course',
            'description' => 'Hearty and delicious main dishes',
            'is_active'   => true,
        ]);

        $desserts = Category::create([
            'name'        => 'Desserts',
            'description' => 'Sweet treats to end your meal',
            'is_active'   => true,
        ]);

        $drinks = Category::create([
            'name'        => 'Drinks',
            'description' => 'Refreshing beverages',
            'is_active'   => true,
        ]);

        // Appetizers
        MenuItem::create([
            'category_id'  => $appetizers->id,
            'name'         => 'Lumpiang Shanghai',
            'description'  => 'Crispy fried spring rolls with sweet chili sauce',
            'price'        => 150.00,
            'is_available' => true,
        ]);

        MenuItem::create([
            'category_id'  => $appetizers->id,
            'name'         => 'Dynamite',
            'description'  => 'Spicy cheese-stuffed finger chili wrapped in lumpia',
            'price'        => 120.00,
            'is_available' => true,
        ]);

        // Main Course
        MenuItem::create([
            'category_id'  => $mainCourse->id,
            'name'         => 'Lechon Kawali',
            'description'  => 'Crispy deep-fried pork belly',
            'price'        => 350.00,
            'is_available' => true,
        ]);

        MenuItem::create([
            'category_id'  => $mainCourse->id,
            'name'         => 'Kare-Kare',
            'description'  => 'Oxtail stew in peanut sauce with bagoong',
            'price'        => 450.00,
            'is_available' => true,
        ]);

        MenuItem::create([
            'category_id'  => $mainCourse->id,
            'name'         => 'Chicken Inasal',
            'description'  => 'Grilled chicken marinated in local spices',
            'price'        => 280.00,
            'is_available' => true,
        ]);

        MenuItem::create([
            'category_id'  => $mainCourse->id,
            'name'         => 'Beef Caldereta',
            'description'  => 'Tender beef in rich tomato sauce',
            'price'        => 380.00,
            'is_available' => true,
        ]);

        // Desserts
        MenuItem::create([
            'category_id'  => $desserts->id,
            'name'         => 'Leche Flan',
            'description'  => 'Classic Filipino caramel custard',
            'price'        => 80.00,
            'is_available' => true,
        ]);

        MenuItem::create([
            'category_id'  => $desserts->id,
            'name'         => 'Biko',
            'description'  => 'Sweet sticky rice cake with coconut',
            'price'        => 100.00,
            'is_available' => true,
        ]);

        // Drinks
        MenuItem::create([
            'category_id'  => $drinks->id,
            'name'         => 'Sago at Gulaman',
            'description'  => 'Refreshing Filipino drink with tapioca pearls',
            'price'        => 60.00,
            'is_available' => true,
        ]);

        MenuItem::create([
            'category_id'  => $drinks->id,
            'name'         => 'Buko Juice',
            'description'  => 'Fresh coconut juice',
            'price'        => 80.00,
            'is_available' => true,
        ]);
    }
}
