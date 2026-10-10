<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

const STORAGE_FILE = __DIR__ . '/registrations.json.php';
const CS_WHATSAPP  = '6281234567890'; // Ganti dengan nomor CS resmi

// Helper: Sanitasi Output
function e(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Helper: Normalisasi nomor WhatsApp Indonesia
function normalize_phone(string $phone): string {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '08')) {
        return '628' . substr($clean, 2);
    }
    if (str_starts_with($clean, '8')) {
        return '628' . substr($clean, 1);
    }
    return $clean;
}

// Helper: Muat data dari file penyimpan
function load_registrations(): array {
    if (!file_exists(STORAGE_FILE)) {
        return [];
    }
    $raw = file_get_contents(STORAGE_FILE);
    $json = preg_replace('/^<\?php.*?\?>\s*/s', '', $raw);
    return json_decode($json, true) ?: [];
}

// Helper: Simpan data secara aman dengan file locking
function save_registrations(array $data): bool {
    $content = "<?php http_response_code(403); exit; ?>" . PHP_EOL . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents(STORAGE_FILE, $content, LOCK_EX) !== false;
}

// Inisialisasi Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Logika Pemrosesan Form (POST)
$feedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $feedback = ['status' => 'danger', 'msg' => 'Validasi keamanan (CSRF) gagal. Silakan muat ulang halaman.'];
    } else {
        $fullName   = trim($_POST['full_name'] ?? '');
        $rawWa      = trim($_POST['whatsapp'] ?? '');
        $city       = trim($_POST['city'] ?? '');
        $agentType  = trim($_POST['agent_type'] ?? 'perorangan');
        $storeName  = trim($_POST['store_name'] ?? '');
        $agreeTerms = isset($_POST['agree_terms']);

        $wa = normalize_phone($rawWa);

        if (mb_strlen($fullName) < 3) {
            $feedback = ['status' => 'danger', 'msg' => 'Nama lengkap wajib diisi minimal 3 karakter.'];
        } elseif (!preg_match('/^628[0-9]{8,12}$/', $wa)) {
            $feedback = ['status' => 'danger', 'msg' => 'Format nomor WhatsApp tidak valid. Gunakan nomor Indonesia (contoh: 08123456789).'];
        } elseif (empty($city)) {
            $feedback = ['status' => 'danger', 'msg' => 'Kabupaten/Kota wajib diisi.'];
        } elseif (!$agreeTerms) {
            $feedback = ['status' => 'danger', 'msg' => 'Anda harus menyetujui syarat & ketentuan kemitraan.'];
        } else {
            $allData = load_registrations();

            // Cek duplikasi nomor WhatsApp
            $isDuplicate = false;
            foreach ($allData as $row) {
                if (($row['whatsapp'] ?? '') === $wa) {
                    $isDuplicate = true;
                    break;
                }
            }

            if ($isDuplicate) {
                $feedback = ['status' => 'warning', 'msg' => 'Nomor WhatsApp ini sudah terdaftar dalam antrean verifikasi.'];
            } else {
                $regNumber = 'REG-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
                $newEntry = [
                    'reg_number' => $regNumber,
                    'full_name'  => $fullName,
                    'whatsapp'   => $wa,
                    'city'       => $city,
                    'agent_type' => $agentType,
                    'store_name' => $storeName ?: '-',
                    'created_at' => date('Y-m-d H:i:s'),
                    'status'     => 'menunggu_verifikasi'
                ];

                $allData[] = $newEntry;
                if (save_registrations($allData)) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Rotate token
                    $feedback = [
                        'status' => 'success',
                        'reg_no' => $regNumber,
                        'name'   => $fullName,
                        'msg'    => 'Pendaftaran berhasil dikirim!'
                    ];
                } else {
                    $feedback = ['status' => 'danger', 'msg' => 'Gagal menyimpan data ke sistem berkas. Pastikan izin akses file memadai.'];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Agen Pulsa, Paket Data & PPOB</title>
    <meta name="description" content="Peluang usaha agen pulsa, paket data, token PLN, voucher game dan PPOB resmi dengan modal terjangkau.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --brand-blue: #0b2545;
            --brand-green: #139a8c;
            --brand-orange: #ea580c;
        }
        body { font-family: system-ui, -apple-system, sans-serif; color: #1e293b; }
        .hero { background: linear-gradient(135deg, var(--brand-blue), #134074); color: white; padding: 70px 0 50px; }
        .card-custom { border: 1px solid #e2e8f0; border-radius: 12px; transition: transform .2s ease; }
        .card-custom:hover { transform: translateY(-3px); }
        .icon-circle { width: 50px; height: 50px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; background: rgba(19, 154, 140, 0.1); color: var(--brand-green); font-size: 1.5rem; }
        .step-badge { width: 38px; height: 38px; border-radius: 50%; background: var(--brand-green); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: bold; margin: 0 auto 12px; }
        .btn-brand { background: var(--brand-orange); color: white; font-weight: 600; border: none; }
        .btn-brand:hover { background: #c2410c; color: white; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background-color: var(--brand-blue);">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#"><i class="bi bi-wallet2 text-success me-2"></i>PulsaNet Digital</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link" href="#keunggulan">Keunggulan</a></li>
                <li class="nav-item"><a class="nav-link" href="#produk">Layanan</a></li>
                <li class="nav-item"><a class="nav-link" href="#simulasi">Simulasi</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                <li class="nav-item ms-lg-2"><a class="btn btn-brand btn-sm px-3" href="#daftar">Daftar Sekarang</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<header class="hero text-center text-lg-start">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge bg-success mb-2 px-3 py-2">Registrasi Kemitraan Terbuka</span>
                <h1 class="display-5 fw-bold mb-3">Mulai Usaha Konter Pulsa & Tagihan Digital Dari Rumah</h1>
                <p class="lead opacity-75 mb-4">Satu saldo untuk transaksi pulsa semua operator, paket kuota, token PLN, voucher game, top-up e-wallet, dan BPJS 24 jam.</p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="#daftar" class="btn btn-brand btn-lg">Daftar Jadi Agen</a>
                    <a href="#simulasi" class="btn btn-outline-light btn-lg">Hitung Keuntungan</a>
                </div>
            </div>
            <div class="col-lg-5 mt-4 mt-lg-0">
                <div class="bg-white text-dark p-4 rounded-4 shadow">
                    <h5 class="fw-bold mb-3 text-primary"><i class="bi bi-shield-check text-success me-2"></i>Keuntungan Utama</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Pendaftaran agen bebas biaya</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Deposit saldo awal mulai Rp20.000</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Transaksi cepat & serial number jelas</li>
                        <li class="mb-0"><i class="bi bi-check-circle-fill text-success me-2"></i>Bisa lewat Android & WhatsApp</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Keunggulan -->
<section id="keunggulan" class="py-5 bg-light">
    <div class="container text-center py-2">
        <h2 class="fw-bold mb-2">Mengapa Bermitra Bersama Kami?</h2>
        <p class="text-muted mb-5">Dukungan infrastruktur stabil untuk menjaga kepuasan pelanggan Anda</p>
        <div class="row g-4 text-start">
            <div class="col-md-3">
                <div class="card card-custom p-4 h-100 bg-white">
                    <div class="icon-circle mb-3"><i class="bi bi-cash-coin"></i></div>
                    <h6 class="fw-bold">Harga Bersaing</h6>
                    <p class="text-muted small mb-0">Harga modal dasar kompetitif memberi ruang margin keuntungan lebih besar.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-4 h-100 bg-white">
                    <div class="icon-circle mb-3"><i class="bi bi-lightning-charge"></i></div>
                    <h6 class="fw-bold">Server Responsif</h6>
                    <p class="text-muted small mb-0">Sistem multi-jalur meminimalisir transaksi pending pada jam-jam sibuk.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-4 h-100 bg-white">
                    <div class="icon-circle mb-3"><i class="bi bi-phone"></i></div>
                    <h6 class="fw-bold">Produk Komprehensif</h6>
                    <p class="text-muted small mb-0">Ratusan produk digital nasional siap dijual hanya dengan satu genggaman ponsel.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-custom p-4 h-100 bg-white">
                    <div class="icon-circle mb-3"><i class="bi bi-headset"></i></div>
                    <h6 class="fw-bold">Layanan Kendala</h6>
                    <p class="text-muted small mb-0">Bantuan responsif via WhatsApp setiap hari jika terjadi kendala pengisian.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Produk & Layanan -->
<section id="produk" class="py-5">
    <div class="container text-center py-2">
        <h2 class="fw-bold mb-2">Produk Transaksi Digital</h2>
        <p class="text-muted mb-5">Layanan harian yang terus dicari pelanggan di lingkungan Anda</p>
        <div class="row g-3 text-start">
            <?php
            $kategori = [
                ['icon' => 'bi-phone', 'title' => 'Pulsa Reguler & Transfer', 'desc' => 'Telkomsel, Indosat, XL, AXIS, Tri, Smartfren nominal 5rb-100rb.'],
                ['icon' => 'bi-wifi', 'title' => 'Paket Kuota Data', 'desc' => 'Paket harian, mingguan, bulanan, kuota belajar, dan voucher data fisik.'],
                ['icon' => 'bi-plug', 'title' => 'Token & Tagihan Listrik PLN', 'desc' => 'Token prabayar instan dan pelunasan tagihan listrik pascabayar.'],
                ['icon' => 'bi-wallet', 'title' => 'Top Up Saldo E-Wallet', 'desc' => 'Isi saldo DANA, GoPay, OVO, ShopeePay, Maxim, dan LinkAja.'],
                ['icon' => 'bi-controller', 'title' => 'Voucher Game Online', 'desc' => 'Mobile Legends, Free Fire, Roblox, PUBG, Genshin Impact.'],
                ['icon' => 'bi-receipt', 'title' => 'Pembayaran Tagihan PPOB', 'desc' => 'BPJS Kesehatan, PDAM se-Indonesia, Telkom IndiHome, dan FIF/WOM.']
            ];
            foreach ($kategori as $kat):
            ?>
            <div class="col-md-4 col-sm-6">
                <div class="card card-custom p-3 h-100">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle me-3"><i class="bi <?= $kat['icon']; ?>"></i></div>
                        <div>
                            <h6 class="fw-bold mb-1"><?= $kat['title']; ?></h6>
                            <p class="text-muted small mb-0"><?= $kat['desc']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 3 Langkah Pendaftaran -->
<section class="py-5 bg-light">
    <div class="container text-center py-2">
        <h2 class="fw-bold mb-2">3 Langkah Menjadi Mitra</h2>
        <p class="text-muted mb-5">Cepat, praktis, dan dapat diselesaikan dalam hitungan menit</p>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="step-badge">1</div>
                <h6 class="fw-bold">Lengkapi Data</h6>
                <p class="text-muted small">Isi formulir pendaftaran di halaman ini dengan nomor WhatsApp aktif.</p>
            </div>
            <div class="col-md-4">
                <div class="step-badge">2</div>
                <h6 class="fw-bold">Simpan No. Registrasi</h6>
                <p class="text-muted small">Dapatkan ID pendaftaran unik dan lakukan konfirmasi ke CS.</p>
            </div>
            <div class="col-md-4">
                <div class="step-badge">3</div>
                <h6 class="fw-bold">Isi Saldo & Jualan</h6>
                <p class="text-muted small">Lakukan pengisian saldo awal dan mulai bertransaksi melayani pembeli.</p>
            </div>
        </div>
    </div>
</section>

<!-- Simulasi Keuntungan -->
<section id="simulasi" class="py-5">
    <div class="container py-2">
        <div class="card border-0 shadow-sm p-4 p-md-5 rounded-4 bg-light">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h3 class="fw-bold text-success mb-2">Simulasi Potensi Margin</h3>
                    <p class="text-muted small mb-4">Gunakan slider berikut untuk memperkirakan keuntungan kotor bulanan berdasarkan target penjualan Anda.</p>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Rata-rata Transaksi per Hari: <span id="txVal" class="text-primary">40</span> trx</label>
                        <input type="range" class="form-range" id="txRange" min="10" max="150" step="5" value="40">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Margin Rata-rata per Transaksi: <span id="marginVal" class="text-primary">Rp 1.500</span></label>
                        <input type="range" class="form-range" id="marginRange" min="500" max="3000" step="100" value="1500">
                    </div>
                </div>
                <div class="col-lg-5 offset-lg-1 mt-4 mt-lg-0 text-center">
                    <div class="bg-white p-4 rounded-3 border shadow-sm">
                        <span class="text-muted small text-uppercase fw-semibold">Estimasi Laba Kotor Bulanan</span>
                        <h2 class="display-6 fw-bold text-success my-2" id="profitResult">Rp 1.800.000</h2>
                        <p class="text-muted small mb-0 fst-italic">*Angka ini murni ilustrasi matematis (transaksi x margin x 30 hari), bukan jaminan pendapatan mutlak.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Formulir Pendaftaran -->
<section id="daftar" class="py-5 bg-light">
    <div class="container py-2">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 bg-white">
                    <h3 class="fw-bold text-center mb-1">Formulir Pendaftaran Calon Agen</h3>
                    <p class="text-muted text-center small mb-4">Pastikan nomor WhatsApp aktif untuk penerimaan informasi kemitraan</p>

                    <?php if ($feedback && $feedback['status'] === 'success'): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-check-circle-fill text-success display-4"></i>
                            <h4 class="fw-bold mt-2 text-success">Pendaftaran Berhasil!</h4>
                            <p class="text-muted small mb-2">Simpan Nomor Registrasi Anda:</p>
                            <div class="alert alert-light border fw-bold fs-5 text-primary py-2 d-inline-block px-4 mb-3">
                                <?= e($feedback['reg_no']); ?>
                            </div>
                            <p class="small text-muted mb-4">Langkah selanjutnya: Kirimkan konfirmasi pendaftaran kepada CS resmi kami melalui tombol berikut:</p>
                            <?php
                            $waText = urlencode("Halo CS, saya mendaftar agen pulsa dengan No. Registrasi {$feedback['reg_no']} atas nama {$feedback['name']}. Mohon bantuan verifikasi.");
                            ?>
                            <a href="https://wa.me/<?= CS_WHATSAPP; ?>?text=<?= $waText; ?>" target="_blank" class="btn btn-success px-4 py-2">
                                <i class="bi bi-whatsapp me-2"></i>Konfirmasi ke CS WhatsApp
                            </a>
                            <div class="mt-4">
                                <a href="index.php#daftar" class="btn btn-link btn-sm text-secondary">Daftar agen baru lainnya</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if ($feedback): ?>
                            <div class="alert alert-<?= $feedback['status']; ?> py-2 small mb-4">
                                <?= e($feedback['msg']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="index.php#daftar">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" required placeholder="Sesuai KTP" value="<?= isset($_POST['full_name']) ? e($_POST['full_name']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Nomor WhatsApp Aktif <span class="text-danger">*</span></label>
                                    <input type="tel" name="whatsapp" class="form-control" required placeholder="08123456789" value="<?= isset($_POST['whatsapp']) ? e($_POST['whatsapp']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Kabupaten / Kota <span class="text-danger">*</span></label>
                                    <input type="text" name="city" class="form-control" required placeholder="Contoh: Tasikmalaya" value="<?= isset($_POST['city']) ? e($_POST['city']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Tipe Kemitraan <span class="text-danger">*</span></label>
                                    <select name="agent_type" class="form-select">
                                        <option value="perorangan">Perorangan (Dipakai Sendiri / Sampingan)</option>
                                        <option value="konter">Konter Pulsa / Servis Ponsel</option>
                                        <option value="warung">Warung / Toko Kelontong</option>
                                        <option value="online">Reseller Online / Mahasiswa</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Nama Usaha / Toko (Opsional)</label>
                                    <input type="text" name="store_name" class="form-control" placeholder="Nama konter / warung jika ada" value="<?= isset($_POST['store_name']) ? e($_POST['store_name']) : ''; ?>">
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="agree_terms" id="agreeCheck" required>
                                        <label class="form-check-label small text-muted" for="agreeCheck">
                                            Saya setuju nomor kontak di atas digunakan untuk verifikasi pendaftaran kemitraan dan menyatakan data yang dimasukkan benar.
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 mt-4 text-center">
                                    <button type="submit" class="btn btn-brand btn-lg w-100">Kirim Pendaftaran Agen</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq" class="py-5">
    <div class="container py-2">
        <h2 class="fw-bold text-center mb-2">Pertanyaan Populer</h2>
        <p class="text-muted text-center mb-5">Hal-hal yang sering ditanyakan oleh calon mitra baru</p>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAcc">
                    <div class="accordion-item mb-2 border rounded-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                Apakah ada biaya pendaftaran awal?
                            </button>
                        </h2>
                        <div id="q1" class="accordion-collapse collapse show" data-bs-parent="#faqAcc">
                            <div class="accordion-body small text-muted">
                                Tidak ada. Pendaftaran agen sepenuhnya gratis. Saldo yang Anda transfer 100% masuk menjadi saldo modal transaksi Anda tanpa potongan pendaftaran.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item mb-2 border rounded-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                Berapa minimal saldo deposit yang harus diisi?
                            </button>
                        </h2>
                        <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
                            <div class="accordion-body small text-muted">
                                Anda dapat memulai pengisian deposit saldo awal mulai dari Rp20.000 via transfer bank maupun QRIS.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item mb-2 border rounded-3 overflow-hidden">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                                Bagaimana jika transaksi pulsa gagal atau pulsa tidak masuk?
                            </button>
                        </h2>
                        <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqAcc">
                            <div class="accordion-body small text-muted">
                                Jika terjadi gangguan dari sisi operator seluler dan status gagal terkonfirmasi, saldo Anda otomatis kembali (refund) secara instan tanpa terpotong.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="py-4 bg-dark text-white text-center text-md-start" style="background-color: var(--brand-blue) !important;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="fw-bold mb-1">PulsaNet Digital Network</h6>
                <p class="small text-white-50 mb-0">Platform distribusi pulsa seluler dan payment point online bank tepercaya.</p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <span class="small text-white-50">Layanan CS WhatsApp: +<?= CS_WHATSAPP; ?></span>
            </div>
        </div>
        <hr class="border-secondary my-3">
        <div class="text-center small text-white-50">
            &copy; 2026 PulsaNet Digital. Hak Cipta Dilindungi.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Logika Penghitungan Interaktif Simulasi Margin
    const txRange = document.getElementById('txRange');
    const marginRange = document.getElementById('marginRange');
    const txVal = document.getElementById('txVal');
    const marginVal = document.getElementById('marginVal');
    const profitResult = document.getElementById('profitResult');

    function updateSim() {
        const tx = parseInt(txRange.value, 10);
        const margin = parseInt(marginRange.value, 10);
        txVal.textContent = tx;
        marginVal.textContent = 'Rp ' + margin.toLocaleString('id-ID');
        const total = tx * margin * 30;
        profitResult.textContent = 'Rp ' + total.toLocaleString('id-ID');
    }

    txRange.addEventListener('input', updateSim);
    marginRange.addEventListener('input', updateSim);
</script>
</body>
</html>