<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserExcelSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['ABDUL SIGIT WIYOTO HADI', 'abdulsigitwiyotohadi@bps.go.id', '91404272', 'SIM-JBG-001', 'pegawai'],
            ['ADI CAHYO SAPUTRO', 'adicahyosaputro@bps.go.id', '43500383', 'SIM-JBG-002', 'pegawai'],
            ['AGUS BUDHI SANTOSA', 'agusbudhisantosa@bps.go.id', '34878519', 'SIM-JBG-003', 'pegawai'],
            ['AGUS PRIHANTO', 'agusprihanto@bps.go.id', '92665614', 'SIM-JBG-004', 'pegawai'],
            ['ARI SUSANTI', 'arisusanti@bps.go.id', '15522845', 'SIM-JBG-005', 'pegawai'],
            ['ARTATIAS SIMANJUNTAK', 'artatiassimanjuntak@bps.go.id', '96405787', 'SIM-JBG-006', 'pegawai'],
            ['BAMBANG SUGIYANTO', 'bambangsugiyanto@bps.go.id', '71376830', 'SIM-JBG-007', 'pegawai'],
            ['BASTARI WIDOJOKO', 'bastariwidojoko@bps.go.id', '35966394', 'SIM-JBG-008', 'pegawai'],
            ['DESSY NARULITASARI', 'dessynarulitasari@bps.go.id', '27241568', 'SIM-JBG-009', 'pegawai'],
            ['DEVI ANDRIANI', 'deviandriani@bps.go.id', '84147161', 'SIM-JBG-010', 'pegawai'],
            ['DIAN CAHYANI WULANDARI', 'diancahyaniwulandari@bps.go.id', '78279789', 'SIM-JBG-011', 'pegawai'],
            ['EKO SUPRIYANTO', 'ekosupriyanto@bps.go.id', '79516690', 'SIM-JBG-012', 'pegawai'],
            ['FITRIANA APEBRUARIN', 'fitrianaapebruarin@bps.go.id', '48964426', 'SIM-JBG-013', 'pegawai'],
            ['IKA FENY LESTARI', 'ikafenylestari@bps.go.id', '99390365', 'SIM-JBG-014', 'pegawai'],
            ['LAMATUR HERIBERTUS SIDABUTAR', 'lamaturheribertussidabutar@bps.go.id', '28107255', 'SIM-JBG-015', 'pegawai'],
            ['Lelia Alful Mizan', 'leliaalfulmizan@bps.go.id', '61069503', 'SIM-JBG-016', 'pegawai'],
            ['MITHA RAMADHANI PRATIWI', 'mitharamadhanipratiwi@bps.go.id', '89039682', 'SIM-JBG-017', 'admin'],
            ['MOCH. HANAFI', 'mochhanafi@bps.go.id', '82113421', 'SIM-JBG-018', 'pegawai'],
            ['MOH. SAFI\'UDIN', 'mohsafiudin@bps.go.id', '78348217', 'SIM-JBG-019', 'pegawai'],
            ['MOHAMAD ALLAMUL WAFA', 'mohamadallamulwafa@bps.go.id', '34415583', 'SIM-JBG-020', 'pegawai'],
            ['MOUNA SRI WAHYUNI', 'mounasriwahyuni@bps.go.id', '96352042', 'SIM-JBG-021', 'pegawai'],
            ['MUHAMMAD BAYU PRAKOSO AJI', 'muhammadbayuprakosoaji@bps.go.id', '94602769', 'SIM-JBG-022', 'pegawai'],
            ['MUHAMMAD SYOLEH', 'muhammadsyoleh@bps.go.id', '78412844', 'SIM-JBG-023', 'pegawai'],
            ['MUSTAKIM', 'mustakim@bps.go.id', '57716085', 'SIM-JBG-024', 'pegawai'],
            ['NANANG HERI PURWANTO', 'nanangheripurwanto@bps.go.id', '48545071', 'SIM-JBG-025', 'pegawai'],
            ['NANANG KHISBULLAH', 'nanangkhisbullah@bps.go.id', '95014589', 'SIM-JBG-026', 'pegawai'],
            ['NURHIDAYAH', 'nurhidayah@bps.go.id', '37746070', 'SIM-JBG-027', 'pegawai'],
            ['RATRI YULIANA', 'ratriyuliana@bps.go.id', '56039635', 'SIM-JBG-028', 'pegawai'],
            ['RENI PUSPITASARI', 'renipuspitasari@bps.go.id', '39826434', 'SIM-JBG-029', 'pegawai'],
            ['RISKA ANDRIANI', 'riskaandriani@bps.go.id', '76028086', 'SIM-JBG-030', 'pegawai'],
            ['ROSYIDA HANUM', 'rosyidahanum@bps.go.id', '51545252', 'SIM-JBG-031', 'pegawai'],
            ['SITI AFIAH', 'sitiafiah@bps.go.id', '40851423', 'SIM-JBG-032', 'pegawai'],
            ['TEGUH PATITIS MUJI RAHAYUNINGTIYAS', 'teguhpatitismujirahayuningtiyas@bps.go.id', '33865579', 'SIM-JBG-033', 'pegawai'],
            ['TITHA APRILLIAWATIE', 'tithaaprilliawatie@bps.go.id', '25766567', 'SIM-JBG-034', 'pegawai'],
            ['ADE SETYO HARIANTO', 'adesetyoharianto@bps.go.id', '57628663', 'SIM-JBG-035', 'pegawai'],
            ['ANDREAS YUDHA PRANATA', 'andreasyudhapranata@bps.go.id', '10155521', 'SIM-JBG-036', 'pegawai'],
            ['ARIF SATRIA WIBOWO', 'arifsatriawibowo@bps.go.id', '78817176', 'SIM-JBG-037', 'pegawai'],
            ['RIFKA AMELIA ISMAWATI', 'rifkaameliaismawati@bps.go.id', '58942962', 'SIM-JBG-038', 'pegawai'],
            ['TEGUH SUTOMPO', 'teguhsutompo@bps.go.id', '78073662', 'SIM-JBG-039', 'pegawai'],
        ];

        foreach ($accounts as $acc) {
            // updateOrCreate akan mengecek email. Jika ada, data di-update. Jika tidak, data dibuat baru.
            $user = User::updateOrCreate(
                ['email' => $acc[1]], 
                [
                    'name' => $acc[0],
                    'password' => Hash::make($acc[2]),
                    'employee_number' => $acc[3],
                ]
            );
            
            // Opsional: $user->assignRole($acc[4]);
        }
    }
}