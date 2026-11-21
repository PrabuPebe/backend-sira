<?php

namespace Database\Seeders;

use App\Models\DamageCategory;
use App\Models\Location;
use App\Models\User;
use App\Models\UserBuildingScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with initial setup according to FR-20 and campus brief.
     */
    public function run(): void
    {
        // 1. Initial Admin Universitas (FR-20)
        $adminUniv = User::firstOrCreate(
            ['email' => 'admin.univ@uii.ac.id'],
            [
                'full_name' => 'Admin Universitas UII',
                'password' => Hash::make('password123'),
                'role' => 'ADMIN_UNIV',
                'must_change_password' => false,
                'is_active' => true,
            ]
        );

        // 2. Master Locations (Gedung Kampus)
        $gedungFti = Location::firstOrCreate(
            ['code' => 'FTI'],
            [
                'name' => 'Gedung KH Mas Mansyur (FTI)',
                'type' => 'BUILDING',
                'is_active' => true,
            ]
        );

        Location::firstOrCreate(
            ['code' => 'REKTORAT'],
            [
                'name' => 'Gedung GBPH Prabuningrat (Rektorat)',
                'type' => 'BUILDING',
                'is_active' => true,
            ]
        );

        Location::firstOrCreate(
            ['code' => 'FBE'],
            [
                'name' => 'Gedung Prof. Dr. Ace Partadiredja (FBE)',
                'type' => 'BUILDING',
                'is_active' => true,
            ]
        );

        // 3. Master Damage Categories
        $categories = [
            'AC / Pendingin Ruangan',
            'Proyektor & Layar',
            'Kelistrikan & Stopkontak',
            'Lampu & Penerangan',
            'Kursi & Meja Kelas',
            'Pintu, Jendela & Kunci',
            'Sanitasi & Toilet',
            'Lift & Eskalator',
        ];

        foreach ($categories as $catName) {
            DamageCategory::firstOrCreate(['name' => $catName], ['is_active' => true]);
        }

        // 4. Initial Kaur / Admin Unit FTI
        $kaurFti = User::firstOrCreate(
            ['email' => 'kaur.fti@uii.ac.id'],
            [
                'full_name' => 'Sukirno (Kaur Perbekalan FTI)',
                'password' => Hash::make('password123'),
                'role' => 'ADMIN_UNIT',
                'must_change_password' => true,
                'is_active' => true,
            ]
        );

        // 5. Initial Teknisi FTI
        $teknisiFti = User::firstOrCreate(
            ['email' => 'teknisi.fti@uii.ac.id'],
            [
                'full_name' => 'Bambang (Teknisi FTI)',
                'password' => Hash::make('password123'),
                'role' => 'TEKNISI',
                'must_change_password' => true,
                'is_active' => true,
            ]
        );

        // 6. Initial Pelapor (Mahasiswa)
        User::firstOrCreate(
            ['email' => 'pelapor.mhs@uii.ac.id'],
            [
                'full_name' => 'Ahmad Fauzi',
                'password' => Hash::make('password123'),
                'role' => 'PELAPOR',
                'reporter_type' => 'MAHASISWA',
                'must_change_password' => false,
                'is_active' => true,
            ]
        );

        // 7. Scopes: Tugaskan Kaur & Teknisi ke Gedung FTI
        \Illuminate\Support\Facades\DB::table('user_building_scopes')->insertOrIgnore([
            [
                'user_id' => $kaurFti->id,
                'building_id' => $gedungFti->id,
                'granted_by' => $adminUniv->id,
                'created_at' => now(),
            ],
            [
                'user_id' => $teknisiFti->id,
                'building_id' => $gedungFti->id,
                'granted_by' => $adminUniv->id,
                'created_at' => now(),
            ],
        ]);

        // 8. Panggil Seeder Data Dummy Lengkap
        $this->call(DummyDataSeeder::class);
    }
}
