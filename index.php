<?php
/**
 * UTS DATA SCIENCE - PHP NATIVE (tanpa composer / library eksternal)
 * Jalankan: php -S localhost:8000  lalu buka http://localhost:8000
 */
const CSV_FILE = __DIR__ . '/dataset_mahasiswa.csv';

/* ---------- Helper umum ---------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmt($n, int $d = 2): string { return number_format((float)$n, $d, '.', ''); }
function tampil($v): string { return $v === null ? 'null' : ($v === '' ? '""' : (string)$v); }

/* =====================================================================
   SOAL 1 - DATA PIPELINE SEDERHANA
   ===================================================================== */
function validasi_baris(array $r): array {
    $err = [];
    foreach (['id', 'tanggal', 'produk', 'qty', 'harga'] as $k) {
        if (!array_key_exists($k, $r) || $r[$k] === null || $r[$k] === '') {
            $err[] = "kolom '$k' kosong/hilang";
        }
    }
    if ($err) return $err;
    if (!is_numeric($r['qty']) || (int)$r['qty'] <= 0)     $err[] = 'qty harus angka > 0';
    if (!is_numeric($r['harga']) || (float)$r['harga'] <= 0) $err[] = 'harga harus angka > 0';
    $d = date_create_from_format('Y-m-d', (string)$r['tanggal']);
    if (!$d || $d->format('Y-m-d') !== $r['tanggal'])       $err[] = 'tanggal harus format YYYY-MM-DD yang valid';
    return $err;
}

function jalankan_pipeline(array $raw): array {
    $valid = [];
    $invalid = [];
    foreach ($raw as $i => $row) {                       // tahap 2: validasi
        $err = validasi_baris($row);
        if ($err) $invalid[] = ['baris' => $i + 1, 'data' => $row, 'error' => $err];
        else      $valid[] = $row;                       // tahap 3: filter
    }
    $diproses = array_map(function ($r) {                // tahap 4: transformasi
        $r['qty']   = (int)$r['qty'];
        $r['harga'] = (float)$r['harga'];
        $r['total'] = $r['qty'] * $r['harga'];
        return $r;
    }, $valid);
    $tahap = [
        ['Ingest data mentah', count($raw),      'selesai'],
        ['Validasi struktur',  count($raw),      'selesai'],
        ['Valid (lolos filter)', count($valid),  'valid'],
        ['Diproses (hitung total)', count($diproses), 'diproses'],
        ['Siap analisis',      count($diproses), 'siap analisis'],
    ];
    return compact('valid', 'invalid', 'diproses', 'tahap');
}

$transaksi_mentah = [
    ['id' => 'TRX001', 'tanggal' => '2026-10-01', 'produk' => 'Kopi Susu',  'qty' => 3,  'harga' => 18000],
    ['id' => 'TRX002', 'tanggal' => '2026-10-01', 'produk' => 'Pancong',    'qty' => 2,  'harga' => 12000],
    ['id' => 'TRX003', 'tanggal' => '2026-10-02', 'produk' => 'Teh Tarik',  'qty' => -2, 'harga' => 10000],   // qty salah
    ['id' => 'TRX004', 'tanggal' => '2026-10-02', 'produk' => 'Roti Bakar', 'qty' => 1,  'harga' => null],    // harga kosong
    ['id' => 'TRX005', 'tanggal' => '2026-02-31', 'produk' => 'Kopi Hitam', 'qty' => 4,  'harga' => 8000],    // tanggal tidak ada
    ['id' => 'TRX006', 'tanggal' => '2026-10-03', 'qty' => 5, 'harga' => 15000],                              // kolom produk hilang
    ['id' => 'TRX007', 'tanggal' => '2026-10-03', 'produk' => 'Mie Rebus',  'qty' => 2,  'harga' => 14000],
    ['id' => 'TRX008', 'tanggal' => '2026-10-04', 'produk' => 'Kopi Susu',  'qty' => 6,  'harga' => 18000],
];
$p1 = jalankan_pipeline($transaksi_mentah);

/* =====================================================================
   SOAL 2 - EDA CSV (fopen + fgetcsv)
   ===================================================================== */
function buat_csv_contoh(string $path): void {
    $nama = ['Andi', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fajar', 'Gita', 'Hana', 'Indra', 'Joko',
             'Kiki', 'Lina', 'Maya', 'Nanda', 'Oka', 'Putri', 'Qori', 'Rina', 'Sari', 'Tono'];
    mt_srand(2026);
    $f = fopen($path, 'w');
    fputcsv($f, ['nim', 'nama', 'nilai_tugas', 'nilai_ujian'], ',', '"', '');
    foreach ($nama as $i => $n) {
        fputcsv($f, ['2026' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT), $n . ' Pratama',
                     mt_rand(60, 100), mt_rand(55, 100)], ',', '"', '');
    }
    fclose($f);
}

function baca_csv(string $path): array {
    $h = fopen($path, 'r');
    if (!$h) die('File CSV tidak bisa dibuka');
    $kolom = fgetcsv($h, 0, ',', '"', '');
    $baris = [];
    while (($r = fgetcsv($h, 0, ',', '"', '')) !== false) {
        if ($r === [null] || count($r) !== count($kolom)) continue;   // lewati baris kosong/rusak
        $baris[] = array_combine($kolom, $r);
    }
    fclose($h);
    return [$kolom, $baris];
}

function deteksi_tipe(array $nilai): string {
    $adaFloat = false;
    foreach ($nilai as $v) {
        if ($v === '' || $v === null) continue;
        if (!is_numeric($v)) return 'string';
        if (!preg_match('/^-?\d+$/', $v)) $adaFloat = true;
    }
    return $adaFloat ? 'float' : 'integer';
}

if (!file_exists(CSV_FILE)) buat_csv_contoh(CSV_FILE);
[$kolom, $dataCsv] = baca_csv(CSV_FILE);

/* =====================================================================
   SOAL 3 - MISSING VALUE + MIN-MAX
   ===================================================================== */
$nilai_ujian = [80, null, 75, "", 90, null, 65, 85, 100];
$kosong = fn($v) => $v === null || $v === '' || !is_numeric($v);
$valid3 = array_values(array_filter($nilai_ujian, fn($v) => !$kosong($v)));
$mean3  = array_sum($valid3) / count($valid3);
$imputasi = array_map(fn($v) => $kosong($v) ? $mean3 : (float)$v, $nilai_ujian);
$min3 = min($imputasi);
$max3 = max($imputasi);
$normal = array_map(fn($v) => $max3 === $min3 ? 0.0 : ($v - $min3) / ($max3 - $min3), $imputasi);

/* =====================================================================
   SOAL 4 - STATISTIK DESKRIPTIF + ASCII BAR CHART
   ===================================================================== */
function hitung_mean(array $d): float { return array_sum($d) / count($d); }

function hitung_median(array $d): float {
    sort($d);
    $n = count($d);
    $m = intdiv($n, 2);
    return $n % 2 ? (float)$d[$m] : ($d[$m - 1] + $d[$m]) / 2;
}

function hitung_modus(array $d): array {
    $f = array_count_values(array_map('strval', $d));
    $max = max($f);
    if ($max === 1) return [];                      // tidak ada modus
    return array_map('floatval', array_keys($f, $max));
}

function hitung_stddev(array $d, bool $sampel = false): float {
    $m = hitung_mean($d);
    $jumlah = 0.0;
    foreach ($d as $x) $jumlah += ($x - $m) ** 2;
    return sqrt($jumlah / (count($d) - ($sampel ? 1 : 0)));
}

function histogram(array $d): array {
    $bin = ['<60' => 0, '60-69' => 0, '70-79' => 0, '80-89' => 0, '90-100' => 0];
    foreach ($d as $x) {
        if ($x < 60)       $bin['<60']++;
        elseif ($x >= 90)  $bin['90-100']++;
        else { $b = (int)floor($x / 10) * 10; $bin["$b-" . ($b + 9)]++; }
    }
    return $bin;
}

$input4 = trim($_GET['data'] ?? '');
$default4 = '78 85 92 67 74 88 95 61 73 85 79 90 82 68 85 77 99 64 71 86 93 58 80 85 76 69 91 84 72 87';
$data4 = array_map('floatval', array_values(array_filter(
    preg_split('/[\s,;]+/', $input4 !== '' ? $input4 : $default4),
    'is_numeric'
)));
if (count($data4) < 2) $data4 = array_map('floatval', explode(' ', $default4));
$modus4 = hitung_modus($data4);
$hist4  = histogram($data4);

/* =====================================================================
   SOAL 5 - TRAIN TEST SPLIT
   ===================================================================== */
function train_test_split(array $data, float $test_size = 0.2, bool $shuffle = true, ?int $seed = null): array {
    $data = array_values($data);
    $n = count($data);
    $idx = range(0, $n - 1);
    $seed ??= random_int(1, 999999);
    if ($shuffle) {                                   // Fisher-Yates shuffle pada INDEKS
        mt_srand($seed);
        for ($i = $n - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$idx[$i], $idx[$j]] = [$idx[$j], $idx[$i]];
        }
    }
    $nTest  = (int)round($n * $test_size);
    $nTrain = $n - $nTest;
    $train = $test = [];
    foreach (array_slice($idx, 0, $nTrain) as $i) $train[] = $data[$i];
    foreach (array_slice($idx, $nTrain) as $i)    $test[]  = $data[$i];
    return ['train' => $train, 'test' => $test, 'seed' => $seed];
}

mt_srand(7);
$dataset5 = [];
for ($i = 1; $i <= 50; $i++) {
    $dataset5[] = ['id' => $i, 'fitur_a' => mt_rand(10, 99), 'fitur_b' => mt_rand(10, 99), 'label' => mt_rand(0, 1)];
}
$seedGet = isset($_GET['seed']) && ctype_digit($_GET['seed']) ? (int)$_GET['seed'] : null;
$split = train_test_split($dataset5, 0.2, true, $seedGet);
$idTrain = array_column($split['train'], 'id');
$idTest  = array_column($split['test'], 'id');
$duplikat = array_intersect($idTrain, $idTest);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>UTS Data Science - PHP Native</title>
<style>
:root{--bg:#0f1226;--card:#181c3a;--line:#2a2f5c;--txt:#e8eaff;--mut:#9aa1d1;--a:#7c5cff;--b:#22d3ee;--ok:#34d399;--bad:#fb7185}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,Segoe UI,Roboto,sans-serif;background:radial-gradient(circle at top,#1b1f4a,var(--bg) 60%);color:var(--txt);line-height:1.55}
.wrap{max-width:1000px;margin:auto;padding:24px 16px 60px}
header{text-align:center;padding:30px 0 10px}
header h1{margin:0;font-size:2rem;background:linear-gradient(90deg,var(--a),var(--b));-webkit-background-clip:text;background-clip:text;color:transparent}
header p{color:var(--mut);margin:6px 0 0}
nav{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin:20px 0;position:sticky;top:0;padding:10px;background:rgba(15,18,38,.85);backdrop-filter:blur(8px);z-index:5}
nav a{color:var(--txt);text-decoration:none;padding:6px 14px;border:1px solid var(--line);border-radius:99px;font-size:.85rem}
nav a:hover{background:var(--a)}
section{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px;margin:22px 0;scroll-margin-top:70px}
section h2{margin:0 0 4px;font-size:1.25rem}
.sub{color:var(--mut);margin:0 0 16px;font-size:.9rem}
h3{font-size:.95rem;color:var(--b);margin:18px 0 8px}
table{width:100%;border-collapse:collapse;font-size:.85rem}
th,td{padding:7px 10px;border-bottom:1px solid var(--line);text-align:left}
th{color:var(--mut);font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em}
.scroll{overflow-x:auto}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
.stat{background:#10142e;border:1px solid var(--line);border-radius:12px;padding:12px;text-align:center}
.stat b{display:block;font-size:1.4rem;color:var(--b)}
.stat span{font-size:.75rem;color:var(--mut)}
.chip{display:inline-block;padding:2px 10px;border-radius:99px;font-size:.75rem;background:#2a2f5c}
.ok{background:rgba(52,211,153,.18);color:var(--ok)}
.bad{background:rgba(251,113,133,.18);color:var(--bad)}
.flow{display:flex;gap:6px;flex-wrap:wrap;align-items:stretch}
.step{flex:1;min-width:140px;background:#10142e;border:1px solid var(--line);border-radius:12px;padding:12px;text-align:center}
.step b{font-size:1.5rem;display:block;color:var(--a)}
.step small{color:var(--mut)}
pre{background:#0a0d22;border:1px solid var(--line);border-radius:12px;padding:14px;overflow-x:auto;font-size:.85rem;color:var(--ok);margin:0}
form{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
input[type=text]{flex:1;min-width:200px;padding:9px 12px;border-radius:10px;border:1px solid var(--line);background:#10142e;color:var(--txt)}
button,.btn{padding:9px 16px;border:0;border-radius:10px;background:linear-gradient(90deg,var(--a),var(--b));color:#fff;font-weight:600;cursor:pointer;text-decoration:none;font-size:.85rem}
.split{display:grid;grid-template-columns:4fr 1fr;gap:10px}
.bar{height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;color:#0f1226}
footer{text-align:center;color:var(--mut);font-size:.8rem;margin-top:30px}
</style>
</head>
<body>
<div class="wrap">
<header>
  <h1>UTS Data Science &middot; PHP Native</h1>
  <p>Politeknik META Industri &middot; TRPL</p>
</header>
<nav>
  <a href="#s1">1 Pipeline</a><a href="#s2">2 EDA CSV</a><a href="#s3">3 Cleaning</a>
  <a href="#s4">4 Statistik</a><a href="#s5">5 Train-Test</a>
</nav>

<!-- ================= SOAL 1 ================= -->
<section id="s1">
  <h2>1. Data Pipeline Sederhana</h2>
  <p class="sub">Ingest &rarr; validasi &rarr; filter &rarr; proses &rarr; siap analisis</p>
  <div class="flow">
    <?php foreach ($p1['tahap'] as [$nm, $jml, $st]): ?>
      <div class="step"><b><?= $jml ?></b><?= e($nm) ?><br><small><?= e($st) ?></small></div>
    <?php endforeach; ?>
  </div>

  <h3>Transaksi valid &amp; sudah diproses</h3>
  <div class="scroll"><table>
    <tr><th>ID</th><th>Tanggal</th><th>Produk</th><th>Qty</th><th>Harga</th><th>Total</th><th>Status</th></tr>
    <?php foreach ($p1['diproses'] as $r): ?>
      <tr><td><?= e($r['id']) ?></td><td><?= e($r['tanggal']) ?></td><td><?= e($r['produk']) ?></td>
      <td><?= $r['qty'] ?></td><td><?= number_format($r['harga'], 0, ',', '.') ?></td>
      <td><?= number_format($r['total'], 0, ',', '.') ?></td><td><span class="chip ok">siap analisis</span></td></tr>
    <?php endforeach; ?>
  </table></div>

  <h3>Transaksi ditolak</h3>
  <div class="scroll"><table>
    <tr><th>Baris</th><th>ID</th><th>Alasan</th><th>Status</th></tr>
    <?php foreach ($p1['invalid'] as $x): ?>
      <tr><td><?= $x['baris'] ?></td><td><?= e($x['data']['id'] ?? '-') ?></td>
      <td><?= e(implode('; ', $x['error'])) ?></td><td><span class="chip bad">tidak valid</span></td></tr>
    <?php endforeach; ?>
  </table></div>
</section>

<!-- ================= SOAL 2 ================= -->
<section id="s2">
  <h2>2. EDA Dataset CSV</h2>
  <p class="sub">Dibaca dengan <code>fopen()</code> + <code>fgetcsv()</code> dari dataset_mahasiswa.csv</p>
  <div class="grid">
    <div class="stat"><b><?= count($dataCsv) ?></b><span>Jumlah baris data</span></div>
    <div class="stat"><b><?= count($kolom) ?></b><span>Jumlah kolom</span></div>
  </div>
  <h3>Nama kolom</h3>
  <?php foreach ($kolom as $k): ?><span class="chip"><?= e($k) ?></span> <?php endforeach; ?>

  <h3>Head (5 baris pertama)</h3>
  <div class="scroll"><table>
    <tr><?php foreach ($kolom as $k): ?><th><?= e($k) ?></th><?php endforeach; ?></tr>
    <?php foreach (array_slice($dataCsv, 0, 5) as $r): ?>
      <tr><?php foreach ($r as $v): ?><td><?= e($v) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
  </table></div>

  <h3>Tipe data per kolom</h3>
  <div class="scroll"><table>
    <tr><th>Kolom</th><th>Tipe</th><th>Missing</th><th>Contoh</th></tr>
    <?php foreach ($kolom as $k):
        $vals = array_column($dataCsv, $k); ?>
      <tr><td><?= e($k) ?></td><td><span class="chip ok"><?= deteksi_tipe($vals) ?></span></td>
      <td><?= count(array_filter($vals, fn($v) => $v === '')) ?></td><td><?= e($vals[0] ?? '-') ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</section>

<!-- ================= SOAL 3 ================= -->
<section id="s3">
  <h2>3. Cleaning, Imputasi &amp; Min-Max</h2>
  <p class="sub">Array awal: [<?= e(implode(', ', array_map('tampil', $nilai_ujian))) ?>]</p>
  <div class="grid">
    <div class="stat"><b><?= fmt($mean3) ?></b><span>Mean nilai valid (<?= count($valid3) ?> data)</span></div>
    <div class="stat"><b><?= $min3 ?></b><span>Min</span></div>
    <div class="stat"><b><?= $max3 ?></b><span>Max</span></div>
  </div>
  <h3>Tahap per elemen</h3>
  <div class="scroll"><table>
    <tr><th>#</th><th>Awal</th><th>Setelah imputasi</th><th>Normalisasi (x-min)/(max-min)</th></tr>
    <?php foreach ($nilai_ujian as $i => $v): ?>
      <tr><td><?= $i ?></td>
      <td><?= $kosong($v) ? '<span class="chip bad">' . e(tampil($v)) . '</span>' : e($v) ?></td>
      <td><?= fmt($imputasi[$i]) ?></td><td><?= fmt($normal[$i], 4) ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</section>

<!-- ================= SOAL 4 ================= -->
<section id="s4">
  <h2>4. Statistik Deskriptif &amp; ASCII Bar Chart</h2>
  <p class="sub">Coba data sendiri (pisahkan dengan spasi atau koma)</p>
  <form method="get" action="#s4">
    <input type="text" name="data" placeholder="<?= e($default4) ?>" value="<?= e($input4) ?>">
    <button>Hitung</button>
  </form>
  <div class="grid">
    <div class="stat"><b><?= count($data4) ?></b><span>Jumlah data</span></div>
    <div class="stat"><b><?= fmt(hitung_mean($data4)) ?></b><span>Mean</span></div>
    <div class="stat"><b><?= fmt(hitung_median($data4)) ?></b><span>Median</span></div>
    <div class="stat"><b><?= $modus4 ? e(implode(', ', $modus4)) : '-' ?></b><span>Modus</span></div>
    <div class="stat"><b><?= fmt(hitung_stddev($data4)) ?></b><span>Std Dev (populasi)</span></div>
    <div class="stat"><b><?= fmt(hitung_stddev($data4, true)) ?></b><span>Std Dev (sampel)</span></div>
  </div>
  <h3>Frekuensi per rentang nilai</h3>
<pre><?php foreach ($hist4 as $rentang => $jml) {
    echo e(str_pad($rentang, 7)) . ' | ' . str_repeat('#', $jml) . ' ' . $jml . "\n";
} ?></pre>
</section>

<!-- ================= SOAL 5 ================= -->
<section id="s5">
  <h2>5. Train-Test Split</h2>
  <p class="sub"><code>train_test_split($data, 0.2, true)</code> &middot; Fisher-Yates pada indeks &middot; seed: <b><?= $split['seed'] ?></b></p>
  <div class="grid">
    <div class="stat"><b><?= count($dataset5) ?></b><span>Total data</span></div>
    <div class="stat"><b><?= count($split['train']) ?></b><span>Data latih (80%)</span></div>
    <div class="stat"><b><?= count($split['test']) ?></b><span>Data uji (20%)</span></div>
    <div class="stat"><b class="<?= $duplikat ? '' : '' ?>"><?= $duplikat ? 'ADA' : 'TIDAK ADA' ?></b><span>Duplikasi antar subset</span></div>
  </div>
  <h3>Proporsi</h3>
  <div class="split">
    <div class="bar" style="background:var(--a)">Train <?= count($split['train']) ?></div>
    <div class="bar" style="background:var(--b)">Test <?= count($split['test']) ?></div>
  </div>
  <h3>ID sampel hasil acak</h3>
  <p style="font-size:.85rem"><b>Train:</b> <?= e(implode(', ', $idTrain)) ?></p>
  <p style="font-size:.85rem"><b>Test:</b> <?= e(implode(', ', $idTest)) ?></p>
  <a class="btn" href="?seed=<?= random_int(1, 999999) ?>#s5">Acak ulang</a>
</section>

<footer>Dibuat dengan PHP Native &middot; jalankan: <code>php -S localhost:8000</code></footer>
</div>
</body>
</html>
