<?php

namespace Database\Seeders;

use App\Models\AlatInventory;
use App\Models\InspekturList;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = ['manager', 'admin', 'marketing', 'inspektur', 'finance'];
        foreach ($roles as $role) Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        // Create users
        $users = [
            ['name' => 'Terzha R. Perdanawan', 'email' => 'terzha@dnp.co.id',   'role' => 'manager',   'inspektur_id' => 'I001'],
            ['name' => 'Admin DNP',             'email' => 'admin@dnp.co.id',    'role' => 'admin',     'inspektur_id' => null],
            ['name' => 'Andini Sari',           'email' => 'andini@dnp.co.id',   'role' => 'marketing', 'inspektur_id' => null],
            ['name' => 'Rendi Pratama',         'email' => 'rendi@dnp.co.id',    'role' => 'inspektur', 'inspektur_id' => 'I002'],
            ['name' => 'Bambang Setiawan',      'email' => 'bambang@dnp.co.id',  'role' => 'inspektur', 'inspektur_id' => 'I003'],
            ['name' => 'Andi Wijaya',           'email' => 'andi@dnp.co.id',     'role' => 'inspektur', 'inspektur_id' => 'I004'],
            ['name' => 'Sri Mulyani',           'email' => 'sri@dnp.co.id',      'role' => 'inspektur', 'inspektur_id' => 'I005'],
            ['name' => 'Dewi Anggraini',        'email' => 'dewi@dnp.co.id',     'role' => 'inspektur', 'inspektur_id' => 'I006'],
            ['name' => 'Keuangan DNP',          'email' => 'finance@dnp.co.id',  'role' => 'finance',   'inspektur_id' => null],
        ];

        $createdUsers = [];
        foreach ($users as $u) {
            $user = User::firstOrCreate(['email' => $u['email']], ['name' => $u['name'], 'password' => Hash::make('password')]);
            $user->syncRoles([$u['role']]);
            $createdUsers[$u['email']] = ['user' => $user, 'inspektur_id' => $u['inspektur_id']];
        }

        // Inspektur master data
        $inspekturs = [
            ['id' => 'I001', 'nama' => 'Terzha R. Perdanawan', 'skp' => 'SKP/AK3-UMUM/IV/2018/0123',    'skp_expired' => '2027-04-15', 'spesialisasi' => ['Umum','Kebakaran'], 'phone' => '0812-1111-0001', 'domisili' => 'Bekasi',     'senior_level' => 5, 'joined_year' => 2015],
            ['id' => 'I002', 'nama' => 'Rendi Pratama',        'skp' => 'SKP/AK3-UMUM/IX/2019/0456',    'skp_expired' => '2027-09-20', 'spesialisasi' => ['Umum','Listrik'],   'phone' => '0812-1111-0002', 'domisili' => 'Bekasi',     'senior_level' => 3, 'joined_year' => 2018],
            ['id' => 'I003', 'nama' => 'Bambang Setiawan',     'skp' => 'SKP/AK3-PUBT/III/2020/0789',   'skp_expired' => '2026-08-10', 'spesialisasi' => ['PUBT','Umum'],      'phone' => '0812-1111-0003', 'domisili' => 'Jakarta',    'senior_level' => 4, 'joined_year' => 2017],
            ['id' => 'I004', 'nama' => 'Andi Wijaya',          'skp' => 'SKP/AK3-PAA/VII/2017/0321',    'skp_expired' => '2026-07-30', 'spesialisasi' => ['PAA','Umum'],       'phone' => '0812-1111-0004', 'domisili' => 'Bekasi',     'senior_level' => 5, 'joined_year' => 2014],
            ['id' => 'I005', 'nama' => 'Sri Mulyani',          'skp' => 'SKP/AK3-LISTRIK/V/2021/0654',  'skp_expired' => '2028-05-12', 'spesialisasi' => ['Listrik','Umum'],   'phone' => '0812-1111-0005', 'domisili' => 'Bekasi',     'senior_level' => 2, 'joined_year' => 2020],
            ['id' => 'I006', 'nama' => 'Dewi Anggraini',       'skp' => 'SKP/AK3-KEBAKARAN/II/2022/0987','skp_expired' => '2027-02-18', 'spesialisasi' => ['Kebakaran','Umum'],'phone' => '0812-1111-0006', 'domisili' => 'Tangerang',  'senior_level' => 1, 'joined_year' => 2022],
        ];

        foreach ($inspekturs as $ins) {
            $linked = collect($createdUsers)->first(fn($u) => $u['inspektur_id'] === $ins['id']);
            InspekturList::updateOrCreate(['id' => $ins['id']], array_merge($ins, [
                'active'  => true,
                'user_id' => $linked ? $linked['user']->id : null,
            ]));
        }

        // Alat inventory
        $alat = [
            ['id'=>'A001','nama'=>'Pressure Gauge Bourdon 0-25 bar','merk'=>'Wika','serial'=>'WK-2401-001','kategori'=>['PUBT','PV'],'kalibrasi_terakhir'=>'2025-08-15','kalibrasi_expired'=>'2026-08-15','lab'=>'KAN-LK-145-IDN','status'=>'tersedia'],
            ['id'=>'A002','nama'=>'Digital Multimeter Fluke 87V','merk'=>'Fluke','serial'=>'FLK-87V-2245','kategori'=>['Listrik'],'kalibrasi_terakhir'=>'2025-06-20','kalibrasi_expired'=>'2026-06-20','lab'=>'KAN-LK-089-IDN','status'=>'tersedia'],
            ['id'=>'A003','nama'=>'Tang Ampere Hioki 3280-10','merk'=>'Hioki','serial'=>'HIO-3280-105','kategori'=>['Listrik'],'kalibrasi_terakhir'=>'2025-09-05','kalibrasi_expired'=>'2026-09-05','lab'=>'KAN-LK-089-IDN','status'=>'tersedia'],
            ['id'=>'A004','nama'=>'Earth Tester Kyoritsu 4105A','merk'=>'Kyoritsu','serial'=>'KYO-4105-088','kategori'=>['Listrik'],'kalibrasi_terakhir'=>'2025-07-12','kalibrasi_expired'=>'2026-07-12','lab'=>'KAN-LK-089-IDN','status'=>'tersedia'],
            ['id'=>'A005','nama'=>'Insulation Tester Megger MIT515','merk'=>'Megger','serial'=>'MGR-515-201','kategori'=>['Listrik'],'kalibrasi_terakhir'=>'2025-05-30','kalibrasi_expired'=>'2026-05-30','lab'=>'KAN-LK-089-IDN','status'=>'sedang dipakai'],
            ['id'=>'A006','nama'=>'Ultrasonic Thickness Gauge GE DM5E','merk'=>'GE','serial'=>'GE-DM5E-077','kategori'=>['PUBT','PV','BOILER'],'kalibrasi_terakhir'=>'2025-04-22','kalibrasi_expired'=>'2026-04-22','lab'=>'KAN-LK-145-IDN','status'=>'tersedia'],
            ['id'=>'A007','nama'=>'Pitot Tube + Manometer (Hydrant)','merk'=>'Dwyer','serial'=>'DWY-477-1011','kategori'=>['Kebakaran'],'kalibrasi_terakhir'=>'2025-10-08','kalibrasi_expired'=>'2026-10-08','lab'=>'KAN-LK-145-IDN','status'=>'tersedia'],
            ['id'=>'A008','nama'=>'Smoke Detector Tester Solo A10','merk'=>'Solo','serial'=>'SLO-A10-453','kategori'=>['Kebakaran'],'kalibrasi_terakhir'=>'2024-12-15','kalibrasi_expired'=>'2025-12-15','lab'=>'KAN-LK-145-IDN','status'=>'tersedia'],
            ['id'=>'A009','nama'=>'Anemometer Kestrel 5500','merk'=>'Kestrel','serial'=>'KES-5500-302','kategori'=>['Kebakaran','Umum'],'kalibrasi_terakhir'=>'2025-03-18','kalibrasi_expired'=>'2026-03-18','lab'=>'KAN-LK-145-IDN','status'=>'tersedia'],
            ['id'=>'A010','nama'=>'Lux Meter Lutron LX-1108','merk'=>'Lutron','serial'=>'LUT-1108-066','kategori'=>['Umum'],'kalibrasi_terakhir'=>'2025-09-22','kalibrasi_expired'=>'2026-09-22','lab'=>'KAN-LK-145-IDN','status'=>'tersedia'],
            ['id'=>'A011','nama'=>'Vibration Meter SKF Microlog','merk'=>'SKF','serial'=>'SKF-CMVL-077','kategori'=>['PAA','PTP'],'kalibrasi_terakhir'=>'2025-08-30','kalibrasi_expired'=>'2026-08-30','lab'=>'KAN-LK-145-IDN','status'=>'tersedia'],
            ['id'=>'A012','nama'=>'Load Cell 10 Ton (Crane Test)','merk'=>'Dillon','serial'=>'DIL-10T-422','kategori'=>['PAA'],'kalibrasi_terakhir'=>'2025-07-05','kalibrasi_expired'=>'2026-07-05','lab'=>'KAN-LK-145-IDN','status'=>'tersedia'],
        ];
        foreach ($alat as $a) AlatInventory::updateOrCreate(['id' => $a['id']], $a);

        // Seed demo jobs from prototype
        $this->call(DemoJobsSeeder::class);
    }
}
