<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\FloorArea;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Data contoh untuk pengembangan. Tidak menimpa data bila brand sudah ada. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Brand::query()->exists()) {
            return;
        }

        Brand::create(['id' => 1, 'name' => 'Kopi Contoh']);
        $branch = Branch::create(['name' => 'Cabang Utama', 'address' => 'Jl. Contoh No. 1']);
        $this->seedFloor($branch);

        $this->seedMenu();
        $this->seedOwner();
    }

    private function seedFloor(Branch $branch): void
    {
        $floor = FloorArea::create(['branch_id' => $branch->id, 'name' => 'Denah']);

        foreach (range(1, 11) as $n) {
            $column = ($n - 1) % 4;
            $row = intdiv($n - 1, 4);
            DiningTable::create([
                'branch_id' => $branch->id, 'floor_area_id' => $floor->id, 'label' => 'A' . $n, 'seats' => $n > 8 ? 2 : 4,
                'shape' => $n > 8 || ($row === 1 && $column % 2 === 1) ? 'round' : 'square',
                'pos_x' => 90 + $column * 190, 'pos_y' => 120 + $row * 150, 'token' => DiningTable::newToken(),
            ]);
        }

        $floor->elements()->createMany([
            ['type' => 'bar', 'label' => 'Bar', 'pos_x' => 80, 'pos_y' => 30, 'width' => 300, 'height' => 44],
            ['type' => 'cashier', 'label' => 'Kasir', 'pos_x' => 660, 'pos_y' => 30, 'width' => 120, 'height' => 44],
            ['type' => 'door', 'label' => 'Pintu', 'pos_x' => 400, 'pos_y' => 580, 'width' => 100, 'height' => 16],
        ]);
    }

    /** Ilustrasi menu contoh (biru gelap) disalin ke disk publik supaya tampil di halaman pelanggan. */
    private function copyMenuImages(): void
    {
        foreach (glob(__DIR__ . '/menu-images/*.svg') as $file) {
            Storage::disk('public')->put('menu/' . basename($file), file_get_contents($file));
        }
    }

    private function seedMenu(): void
    {
        $this->copyMenuImages();
        $coffee = Category::create(['name' => 'Kopi', 'sort_order' => 1]);
        $food = Category::create(['name' => 'Makanan', 'sort_order' => 2]);

        $latte = Item::create(['category_id' => $coffee->id, 'name' => 'Es Kopi Susu', 'photo' => 'menu/es-kopi-susu.svg', 'description' => 'Espresso, susu segar, es.', 'price' => 22000, 'cost' => 8000, 'station' => 'bar', 'is_best_seller' => true]);
        Item::create(['category_id' => $coffee->id, 'name' => 'Americano', 'photo' => 'menu/americano.svg', 'description' => 'Espresso dan air panas.', 'price' => 18000, 'cost' => 5000, 'station' => 'bar', 'sort_order' => 1]);
        Item::create(['category_id' => $food->id, 'name' => 'Nasi Goreng', 'photo' => 'menu/nasi-goreng.svg', 'description' => 'Telur, ayam suwir, kerupuk.', 'price' => 32000, 'cost' => 12000, 'is_best_seller' => true]);
        Item::create(['category_id' => $food->id, 'name' => 'Kentang Goreng', 'photo' => 'menu/kentang-goreng.svg', 'description' => 'Dengan saus sambal.', 'price' => 20000, 'cost' => 6000, 'sort_order' => 1]);

        $size = $latte->modifierGroups()->create(['name' => 'Ukuran', 'required' => true]);
        $size->modifiers()->createMany([['name' => 'Reguler', 'price_delta' => 0], ['name' => 'Besar', 'price_delta' => 5000]]);
    }

    private function seedOwner(): void
    {
        $password = Str::random(10);
        User::create(['name' => 'Owner', 'email' => 'owner@contoh.test', 'password' => $password, 'role' => Role::Owner]);

        $this->command?->info("Login staf: owner@contoh.test / $password (catat, tidak ditampilkan lagi)");
        foreach (DiningTable::query()->orderBy('id')->get() as $table) {
            $this->command?->line("Meja {$table->label}: " . route('menu.show', $table->token));
        }
    }
}
