<?php
/**
 * Sistem Klasifikasi Kedisiplinan Santri: Algoritma Rule-Based
 * SIMPONTREN - Pondok Pesantren Nurul Athfal
 * 
 * Aturan (Knowledge Base Dinamis):
 * - Rule 1: IF Poin < Ambang Ringan        THEN Kategori = "Aman / Pembinaan Biasa"
 * - Rule 2: IF Ambang Ringan <= Poin < Ambang Sedang THEN Kategori = "Hukuman Ringan"
 * - Rule 3: IF Ambang Sedang <= Poin < Ambang Berat  THEN Kategori = "Hukuman Sedang"
 * - Rule 4: IF Poin >= Ambang Berat       THEN Kategori = "Hukuman Berat"
 */

/**
 * Mengambil nilai ambang batas poin dari database (tabel pengaturan)
 * Default jika belum ada: Ringan = 15, Sedang = 25, Berat = 50
 *
 * @param PDO|null $pdoInstance
 * @return array ['ringan' => int, 'sedang' => int, 'berat' => int]
 */
function get_rule_thresholds($pdoInstance = null) {
    static $cached = null;
    if ($cached !== null && $pdoInstance === null) {
        return $cached;
    }

    $thresholds = [
        'ringan' => 15,
        'sedang' => 25,
        'berat'  => 50
    ];

    $db = $pdoInstance;
    if (!$db && isset($GLOBALS['pdo'])) {
        $db = $GLOBALS['pdo'];
    }

    if ($db instanceof PDO) {
        try {
            $stmt = $db->query("SELECT kunci, nilai FROM pengaturan WHERE kunci IN ('poin_ringan', 'poin_sedang', 'poin_berat')");
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            if (isset($rows['poin_ringan'])) {
                $thresholds['ringan'] = max(1, intval($rows['poin_ringan']));
            }
            if (isset($rows['poin_sedang'])) {
                $thresholds['sedang'] = max($thresholds['ringan'] + 1, intval($rows['poin_sedang']));
            }
            if (isset($rows['poin_berat'])) {
                $thresholds['berat'] = max($thresholds['sedang'] + 1, intval($rows['poin_berat']));
            }
        } catch (Exception $e) {
            // Gunakan nilai default jika tabel belum siap
        }
    }

    $cached = $thresholds;
    return $thresholds;
}

/**
 * Menyimpan pembaruan nilai ambang batas poin ke database
 *
 * @param PDO $pdoInstance
 * @param int $ringan
 * @param int $sedang
 * @param int $berat
 * @return array
 */
function update_rule_thresholds($pdoInstance, $ringan, $sedang, $berat) {
    $ringan = max(1, intval($ringan));
    $sedang = max($ringan + 1, intval($sedang));
    $berat  = max($sedang + 1, intval($berat));

    // Pastikan tabel pengaturan ada
    $pdoInstance->exec("CREATE TABLE IF NOT EXISTS `pengaturan` (
      `kunci` VARCHAR(50) PRIMARY KEY,
      `nilai` VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $stmt = $pdoInstance->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (:k, :v1) ON DUPLICATE KEY UPDATE nilai = :v2");
    $stmt->execute(['k' => 'poin_ringan', 'v1' => $ringan, 'v2' => $ringan]);
    $stmt->execute(['k' => 'poin_sedang', 'v1' => $sedang, 'v2' => $sedang]);
    $stmt->execute(['k' => 'poin_berat',  'v1' => $berat,  'v2' => $berat]);

    // Reset static cache by passing new values
    return [
        'ringan' => $ringan,
        'sedang' => $sedang,
        'berat'  => $berat
    ];
}

/**
 * Mengambil definisi aturan berdasarkan ambang batas aktif
 *
 * @param PDO|null $pdoInstance
 * @return array
 */
function get_rule_definitions($pdoInstance = null) {
    $th = get_rule_thresholds($pdoInstance);
    $tR = $th['ringan'];
    $tS = $th['sedang'];
    $tB = $th['berat'];

    return [
        'R1' => [
            'id'             => 'R1',
            'code'           => 'RULE-01',
            'nama'           => 'Aman / Pembinaan',
            'kondisi_text'   => 'Poin < ' . $tR,
            'min'            => 0,
            'max'            => $tR - 1,
            'kategori'       => 'Aman / Pembinaan',
            'tingkat'        => 'Aman',
            'badge_bg'       => 'bg-success text-white',
            'badge_soft'     => 'badge-soft-success',
            'border_class'   => 'border-success',
            'text_color'     => 'text-success',
            'hex_color'      => '#198754',
            'icon'           => 'fa-check-circle',
            'sp'             => 'Tanpa SP',
            'sp_badge'       => 'badge-soft-success',
            'sanksi_singkat' => 'Teguran lisan & pembinaan akhlak',
            'sanksi_detail'  => 'Nasehat kedisiplinan dan teguran lisan dari wali asrama/kamar.',
            'tindakan'       => 'Monitoring kedisiplinan berkala oleh pengurus kamar santri.',
            'prioritas'      => 1
        ],
        'R2' => [
            'id'             => 'R2',
            'code'           => 'RULE-02',
            'nama'           => 'Hukuman Ringan',
            'kondisi_text'   => $tR . ' ≤ Poin < ' . $tS,
            'min'            => $tR,
            'max'            => $tS - 1,
            'kategori'       => 'Hukuman Ringan',
            'tingkat'        => 'Ringan',
            'badge_bg'       => 'bg-info text-white',
            'badge_soft'     => 'badge-soft-info',
            'border_class'   => 'border-info',
            'text_color'     => 'text-info',
            'hex_color'      => '#0dcaf0',
            'icon'           => 'fa-info-circle',
            'sp'             => 'SP 1 (Peringatan I)',
            'sp_badge'       => 'badge-soft-info',
            'sanksi_singkat' => 'Takzir kebersihan & hafalan surat',
            'sanksi_detail'  => 'Surat Peringatan I (SP 1), takzir membersihkan area pondok & setoran hafalan surat pilihan.',
            'tindakan'       => 'Konseling bimbingan oleh wali kamar santri.',
            'prioritas'      => 2
        ],
        'R3' => [
            'id'             => 'R3',
            'code'           => 'RULE-03',
            'nama'           => 'Hukuman Sedang',
            'kondisi_text'   => $tS . ' ≤ Poin < ' . $tB,
            'min'            => $tS,
            'max'            => $tB - 1,
            'kategori'       => 'Hukuman Sedang',
            'tingkat'        => 'Sedang',
            'badge_bg'       => 'bg-warning text-dark',
            'badge_soft'     => 'badge-soft-warning',
            'border_class'   => 'border-warning',
            'text_color'     => 'text-warning',
            'hex_color'      => '#ffc107',
            'icon'           => 'fa-exclamation-triangle',
            'sp'             => 'SP 2 (Panggilan Wali)',
            'sp_badge'       => 'badge-soft-warning',
            'sanksi_singkat' => 'Panggilan orang tua & larangan pesiar',
            'sanksi_detail'  => 'Surat Peringatan II (SP 2), pemanggilan wali santri ke pondok & pembekuan izin pesiar keluar.',
            'tindakan'       => 'Pertemuan tatap muka keamanan pondok, santri, dan wali santri.',
            'prioritas'      => 3
        ],
        'R4' => [
            'id'             => 'R4',
            'code'           => 'RULE-04',
            'nama'           => 'Hukuman Berat',
            'kondisi_text'   => 'Poin ≥ ' . $tB,
            'min'            => $tB,
            'max'            => 9999,
            'kategori'       => 'Hukuman Berat',
            'tingkat'        => 'Berat',
            'badge_bg'       => 'bg-danger text-white',
            'badge_soft'     => 'badge-soft-danger',
            'border_class'   => 'border-danger',
            'text_color'     => 'text-danger',
            'hex_color'      => '#dc3545',
            'icon'           => 'fa-radiation',
            'sp'             => 'SP 3 (Sidang / D.O)',
            'sp_badge'       => 'badge-soft-danger',
            'sanksi_singkat' => 'Sidang dewan pengasuh & skorsing / DO',
            'sanksi_detail'  => 'Surat Peringatan III (SP 3), sidang disiplin pengasuh, skorsing dirumahkan hingga pengembalian ke wali.',
            'tindakan'       => 'Sidang pleno majelis pimpinan pondok pesantren.',
            'prioritas'      => 4
        ]
    ];
}

/**
 * Evaluasi Algoritma Rule-Based untuk nilai poin tertentu
 *
 * @param int $poin
 * @param PDO|null $pdoInstance
 * @return array
 */
function evaluate_rule_based($poin, $pdoInstance = null) {
    $poin = max(0, intval($poin));
    $th = get_rule_thresholds($pdoInstance);
    $rules = get_rule_definitions($pdoInstance);
    
    $tR = $th['ringan'];
    $tS = $th['sedang'];
    $tB = $th['berat'];

    if ($poin >= $tB) {
        $matched = $rules['R4'];
    } elseif ($poin >= $tS) {
        $matched = $rules['R3'];
    } elseif ($poin >= $tR) {
        $matched = $rules['R2'];
    } else {
        $matched = $rules['R1'];
    }

    return array_merge($matched, [
        'input_poin' => $poin,
        'thresholds' => $th
    ]);
}
