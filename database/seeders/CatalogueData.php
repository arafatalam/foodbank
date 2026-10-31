<?php

namespace Database\Seeders;

final class CatalogueData
{
    /** @return array<string, array<string, list<string>>> */
    public static function departments(): array
    {
        // Generic foodbank products: brands and packaging will belong to stock choices.
        return [
            'Food' => [
                'Grains' => ['Rice', 'Oats', 'Couscous', 'Quinoa', 'Barley', 'Flour', 'Cornmeal'],
                'Pasta and Noodles' => ['Pasta', 'Noodles', 'Macaroni and Cheese'],
                'Beans and Pulses' => ['Lentils', 'Chickpeas', 'Kidney Beans', 'Black Beans', 'Split Peas', 'Baked Beans'],
                'Canned Foods' => ['Canned Tomatoes', 'Canned Corn', 'Canned Peas', 'Canned Tuna', 'Canned Salmon', 'Canned Peaches', 'Canned Pineapple'],
                'Soups and Sauces' => ['Soup', 'Broth', 'Pasta Sauce', 'Tomato Paste', 'Curry Sauce'],
                'Breakfast and Spreads' => ['Breakfast Cereal', 'Peanut Butter', 'Jam', 'Honey', 'Pancake Mix'],
                'Dairy and Eggs' => ['Milk', 'Eggs', 'Cheese', 'Yogurt', 'Butter', 'Margarine'],
                'Meat and Alternatives' => ['Chicken', 'Ground Beef', 'Turkey', 'Fish', 'Tofu'],
                'Fruit' => ['Apples', 'Bananas', 'Oranges', 'Pears', 'Grapes', 'Berries'],
                'Vegetables' => ['Potatoes', 'Carrots', 'Onions', 'Cabbage', 'Broccoli', 'Spinach', 'Tomatoes'],
                'Bakery' => ['Bread', 'Rolls', 'Tortillas', 'Bagels', 'Crackers'],
                'Frozen Foods' => ['Frozen Vegetables', 'Frozen Fruit', 'Frozen Meals'],
                'Beverages' => ['Water', 'Fruit Juice', 'Tea', 'Coffee', 'Plant-Based Drink'],
                'Cooking Essentials' => ['Cooking Oil', 'Sugar', 'Salt', 'Black Pepper', 'Vinegar', 'Baking Powder'],
            ],
            'Baby Care' => [
                'Infant Feeding' => ['Infant Formula', 'Baby Cereal', 'Baby Food'],
                'Changing Supplies' => ['Diapers', 'Baby Wipes', 'Diaper Cream'],
                'Baby Hygiene' => ['Baby Shampoo', 'Baby Wash', 'Baby Lotion'],
            ],
            'Personal Care' => [
                'Oral Care' => ['Toothpaste', 'Toothbrush', 'Dental Floss'],
                'Hair Care' => ['Shampoo', 'Conditioner', 'Comb'],
                'Body Care' => ['Bar Soap', 'Body Wash', 'Deodorant', 'Body Lotion'],
                'Menstrual Care' => ['Sanitary Pads', 'Tampons', 'Panty Liners'],
                'Incontinence Care' => ['Adult Briefs', 'Incontinence Pads'],
            ],
            'Household Supplies' => [
                'Cleaning Supplies' => ['Dish Soap', 'Laundry Detergent', 'All-Purpose Cleaner', 'Cleaning Sponges'],
                'Paper Products' => ['Toilet Paper', 'Paper Towels', 'Facial Tissues'],
                'Storage Supplies' => ['Garbage Bags', 'Food Storage Bags', 'Aluminium Foil'],
            ],
        ];
    }
}
