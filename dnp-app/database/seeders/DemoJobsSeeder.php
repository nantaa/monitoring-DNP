<?php

namespace Database\Seeders;

use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoJobsSeeder extends Seeder
{
    public function run(): void
    {
        $mgr = User::where('email', 'terzha@dnp.co.id')->first();
        $now = now();
        $ago = fn(int $d) => $now->copy()->subDays($d)->toDateString();
        $future = fn(int $d) => $now->copy()->addDays($d)->toDateString();

        $jobs = [
            [
                'id' => Str::uuid(), 'kode' => 'DNP/2026/0001',
                'klien' => 'PT Equinix Indonesia (JKT05 Cikarang)', 'lokasi' => 'Cikarang, Bekasi',
                'owner_marketing' => 'Terzha R. Perdanawan', 'pic_klien' => 'Bapak Hendra (HSE)', 'pic_klien_phone' => '0812-3456-7890',
                'pesawat' => 'FIRE', 'units' => 4, 'nilai' => 85000000, 'no_po' => 'PO/EQX/2026/0142', 'tgl_po' => $ago(15),
                'stage' => 5, 'stage_started_at' => $ago(2),
                'inspektur' => 'Rendi Pratama, Terzha R. Perdanawan', 'inspektur_ids' => ['I001','I002'],
                'tgl_pelaksanaan' => $ago(5), 'no_surat_tugas' => 'ST-DNP/2026/0142', 'tgl_surat_tugas' => $ago(8),
                'disnaker_tujuan' => 'Disnaker Bekasi', 'tgl_h5' => $ago(10), 'h5_confirmed' => true,
                'h5_method' => 'teman_k3', 'h5_confirmed_at' => $ago(10), 'h5_confirmed_by' => 'Admin RU',
                'peer_review_status' => 'submitted', 'peer_review_submitted_at' => $ago(1),
                'peer_review_submitted_by' => 'Rendi Pratama', 'laik_status' => 'laik_bersyarat',
                'evaluations' => [
                    ['unit_no'=>1,'unit_label'=>'Sprinkler Zona 1','status'=>'laik','findings'=>'Tekanan & flow normal','recommendation'=>'Maintenance per 6 bulan'],
                    ['unit_no'=>2,'unit_label'=>'Sprinkler Zona 2','status'=>'laik','findings'=>'Sesuai NFPA 25','recommendation'=>'Lanjutkan rutinitas'],
                    ['unit_no'=>3,'unit_label'=>'Fire Alarm Panel','status'=>'laik_bersyarat','findings'=>'Battery backup 1 zone weak (12.4V)','recommendation'=>'Ganti battery dalam 30 hari'],
                    ['unit_no'=>4,'unit_label'=>'Hydrant System','status'=>'laik','findings'=>'Tekanan 5.5 bar OK','recommendation'=>'Flushing per kuartal'],
                ],
                'notes' => 'Sprinkler + Fire Alarm + Hydrant 4 sistem',
                'created_by' => $mgr?->id,
            ],
            [
                'id' => Str::uuid(), 'kode' => 'DNP/2026/0002',
                'klien' => 'Hotel Indonesia Kempinski', 'lokasi' => 'Thamrin, Jakarta Pusat',
                'owner_marketing' => 'Andini Sari', 'pic_klien' => 'Pak Surya (Chief Engineer)', 'pic_klien_phone' => '0813-9876-5432',
                'pesawat' => 'PV', 'units' => 4, 'nilai' => 32000000, 'no_po' => 'PO/HIK/2026/0033', 'tgl_po' => $ago(40),
                'stage' => 6, 'stage_started_at' => $ago(25),
                'inspektur' => 'Terzha R. Perdanawan, Bambang Setiawan', 'inspektur_ids' => ['I001','I003'],
                'tgl_pelaksanaan' => $ago(30), 'no_surat_tugas' => 'ST-DNP/2026/0033', 'disnaker_tujuan' => 'Disnaker DKI Jakarta',
                'h5_confirmed' => true, 'h5_method' => 'teman_k3',
                'peer_review_status' => 'approved', 'peer_review_approved_at' => $ago(27), 'peer_review_approved_by' => 'Terzha R. Perdanawan',
                'laik_status' => 'laik',
                'units_tracking' => [
                    ['unit_no'=>1,'unit_label'=>'Calorifier Tower A (200L)','laik_status'=>'laik','status'=>'issued','tgl_submit'=>$ago(25),'no_registrasi'=>'REG/DKI/PV/2026/045-U1','no_suket'=>'SK.045A/DISNAKER/DKI/2026','tgl_suket'=>$ago(8),'suket_expired_at'=>$future(720),'suket_validity_months'=>24,'notes'=>''],
                    ['unit_no'=>2,'unit_label'=>'Calorifier Tower B (200L)','laik_status'=>'laik','status'=>'issued','tgl_submit'=>$ago(25),'no_registrasi'=>'REG/DKI/PV/2026/045-U2','no_suket'=>'SK.045B/DISNAKER/DKI/2026','tgl_suket'=>$ago(8),'suket_expired_at'=>$future(720),'suket_validity_months'=>24,'notes'=>''],
                    ['unit_no'=>3,'unit_label'=>'Storage Tank 500L (Kitchen)','laik_status'=>'laik','status'=>'submitted','tgl_submit'=>$ago(25),'no_registrasi'=>'REG/DKI/PV/2026/045-U3','no_suket'=>null,'tgl_suket'=>null,'suket_expired_at'=>null,'suket_validity_months'=>null,'notes'=>''],
                    ['unit_no'=>4,'unit_label'=>'Pressure Vessel Spa (Hydrotest)','laik_status'=>'laik_bersyarat','status'=>'rejected','tgl_submit'=>$ago(25),'no_registrasi'=>'REG/DKI/PV/2026/045-U4','no_suket'=>null,'tgl_suket'=>null,'suket_expired_at'=>null,'suket_validity_months'=>null,'notes'=>'Disnaker minta data hydrotest lengkap'],
                ],
                'notes' => 'Calorifier 4 unit — mixed pemeriksaan luar & hydrotest',
                'created_by' => $mgr?->id,
            ],
            [
                'id' => Str::uuid(), 'kode' => 'DNP/2026/0003',
                'klien' => 'PT Toyota Motor Manufacturing Indonesia', 'lokasi' => 'Karawang',
                'owner_marketing' => 'Terzha R. Perdanawan', 'pic_klien' => 'Ibu Linda (EHS Manager)', 'pic_klien_phone' => '0811-2233-4455',
                'pesawat' => 'PAPA', 'units' => 8, 'nilai' => 64000000, 'no_po' => 'PO/TMMIN/2026/0089', 'tgl_po' => $ago(5),
                'stage' => 2, 'stage_started_at' => $ago(3),
                'notes' => 'Overhead crane 5 ton + forklift 3 unit', 'created_by' => $mgr?->id,
            ],
            [
                'id' => Str::uuid(), 'kode' => 'DNP/2026/0004',
                'klien' => 'PT Pupuk Kujang', 'lokasi' => 'Cikampek',
                'owner_marketing' => 'Terzha R. Perdanawan', 'pic_klien' => 'Bapak Arman', 'pic_klien_phone' => '0812-7654-3210',
                'pesawat' => 'BOILER', 'units' => 2, 'nilai' => 48000000, 'no_po' => 'PO/PK/2026/0021', 'tgl_po' => $ago(120),
                'stage' => 7, 'stage_started_at' => $ago(2), 'completed_at' => $ago(1),
                'inspektur' => 'Terzha R. Perdanawan, Rendi Pratama', 'inspektur_ids' => ['I001','I002'],
                'tgl_pelaksanaan' => $ago(95), 'disnaker_tujuan' => 'Disnaker Jabar',
                'peer_review_status' => 'approved', 'laik_status' => 'laik',
                'invoice_no' => 'INV-2026-0021', 'invoice_date' => $ago(2), 'top_days' => 30,
                'payment_due_date' => $future(28), 'payment_status' => 'sent',
                'tanda_terima_kembali' => true,
                'units_tracking' => [
                    ['unit_no'=>1,'unit_label'=>'Boiler Pipa Air #1 (5 ton/jam)','laik_status'=>'laik','status'=>'issued','tgl_submit'=>$ago(60),'no_registrasi'=>'REG/JABAR/BOILER/2026/021-U1','no_suket'=>'SK.123A/DISNAKER/JABAR/2026','tgl_suket'=>$ago(5),'suket_expired_at'=>$future(360),'suket_validity_months'=>12,'notes'=>''],
                    ['unit_no'=>2,'unit_label'=>'Boiler Pipa Air #2 (5 ton/jam)','laik_status'=>'laik','status'=>'issued','tgl_submit'=>$ago(60),'no_registrasi'=>'REG/JABAR/BOILER/2026/021-U2','no_suket'=>'SK.123B/DISNAKER/JABAR/2026','tgl_suket'=>$ago(5),'suket_expired_at'=>$future(360),'suket_validity_months'=>12,'notes'=>''],
                ],
                'notes' => 'Boiler pipa air 2 unit kapasitas 5 ton/jam', 'created_by' => $mgr?->id,
            ],
        ];

        foreach ($jobs as $j) {
            Job::withoutEvents(fn() => Job::updateOrCreate(['kode' => $j['kode']], $j));
        }
    }
}
