<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'firma_admin') {
    header("Location: /login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];

$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

function validate_date($date) {
    return preg_match('/^(0[1-9]|[12][0-9]|3[01])\/(0[1-9]|1[0-2])\/\d{4}$/', $date);
}
function validate_time($time) {
    return preg_match('/^(?:[01]\d|2[0-3])\.[0-5]\d$/', $time);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_trip'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Geçersiz istek (CSRF).");
    }
    $stmt = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company_id = $stmt->fetchColumn();
    if (!$company_id) {
        $_SESSION['error'] = "Firma bilgisi bulunamadı!";
        header("Location: /sefer/sefer_ekle.php");
        exit;
    }
    $id = uniqid('trip_');
    $departure_city = htmlspecialchars(trim($_POST['departure_city']));
    $destination_city = htmlspecialchars(trim($_POST['destination_city']));
    $departure_date = trim($_POST['departure_date']);
    $departure_time = trim($_POST['departure_time']);
    $arrival_date = trim($_POST['arrival_date']);
    $arrival_time = trim($_POST['arrival_time']);
    $price = floatval($_POST['price']);
    $capacity = intval($_POST['capacity']);
    if (!validate_date($departure_date) || !validate_time($departure_time) ||
        !validate_date($arrival_date) || !validate_time($arrival_time)) {
        $_SESSION['error'] = "Tarih veya saat formatı geçersiz! Tarih: GG/AA/YYYY, Saat: HH.MM";
        header("Location: /sefer/sefer_ekle.php");
        exit;
    }
    list($d, $m, $y) = explode('/', $departure_date);
    list($h, $i) = explode('.', $departure_time);
    $departure_dt = DateTime::createFromFormat('Y-m-d H:i', "$y-$m-$d $h:$i");
    list($d, $m, $y) = explode('/', $arrival_date);
    list($h, $i) = explode('.', $arrival_time);
    $arrival_dt = DateTime::createFromFormat('Y-m-d H:i', "$y-$m-$d $h:$i");
    if ($arrival_dt <= $departure_dt) {
        $_SESSION['error'] = "Varış zamanı, kalkış zamanından sonra olmalıdır.";
        header("Location: sefer_ekle.php");
        exit;
    }
    $departure_time_db = $departure_dt->format('Y-m-d H:i:s');
    $arrival_time_db = $arrival_dt->format('Y-m-d H:i:s');
    $sql = "INSERT INTO Trips
            (id, company_id, destination_city, arrival_time, departure_time, departure_city, price, capacity, created_date)
            VALUES (:id, :company_id, :destination_city, :arrival_time, :departure_time, :departure_city, :price, :capacity, datetime('now'))";
    $stmt = $pdo->prepare($sql);
    if($stmt->execute([
        ':id'=>$id, ':company_id'=>$company_id, ':destination_city'=>$destination_city,
        ':arrival_time'=>$arrival_time_db, ':departure_time'=>$departure_time_db,
        ':departure_city'=>$departure_city, ':price'=>$price, ':capacity'=>$capacity
    ])) {
        $_SESSION['success'] = "Yeni sefer başarıyla eklendi.";
    } else {
        $_SESSION['error'] = "Sefer eklenirken bir hata oluştu!";
    }
    header("Location: sefer_ekle.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sefer Ekle - VatanBilet</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Inter',sans-serif;
    background:#0a0a0a;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}
/* Container */
.container{
    max-width:700px;
    width:100%;
}
/* Card */
.card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:20px;
    box-shadow:0 4px 20px rgba(0,0,0,0.4),0 0 40px rgba(212,175,55,0.1);
    border:1px solid rgba(212,175,55,0.2);
    overflow:hidden;
}
/* Header */
.card-header{
    background:linear-gradient(135deg,#d4af37,#f4d03f);
    padding:32px 32px 28px;
    text-align:center;
    position:relative;
    overflow:hidden;
}
.card-header::before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:radial-gradient(circle at top right,rgba(255,255,255,0.1),transparent);
    pointer-events:none;
}
.card-header-icon{
    width:72px;
    height:72px;
    border-radius:50%;
    background:rgba(0,0,0,0.15);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:36px;
    margin:0 auto 16px;
    backdrop-filter:blur(10px);
    box-shadow:0 0 30px rgba(0,0,0,0.2);
    position:relative;
}
.card-header h2{
    color:#000;
    font-size:28px;
    font-weight:700;
    margin-bottom:8px;
    text-shadow:0 2px 4px rgba(0,0,0,0.1);
    position:relative;
}
.card-header p{
    color:rgba(0,0,0,0.7);
    font-size:15px;
    position:relative;
}
/* Body */
.card-body{
    padding:32px;
}
/* Messages */
.alert{
    padding:14px 16px;
    border-radius:12px;
    margin-bottom:24px;
    font-size:14px;
    font-weight:500;
    display:flex;
    align-items:center;
    gap:10px;
    backdrop-filter:blur(10px);
}
.alert-success{
    background:linear-gradient(135deg,rgba(16,185,129,0.15),rgba(5,150,105,0.1));
    color:#34d399;
    border:1px solid rgba(16,185,129,0.3);
    box-shadow:0 0 20px rgba(16,185,129,0.1);
}
.alert-error{
    background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(220,38,38,0.1));
    color:#fca5a5;
    border:1px solid rgba(239,68,68,0.3);
    box-shadow:0 0 20px rgba(239,68,68,0.1);
}
.alert::before{
    font-size:20px;
}
.alert-success::before{content:'✓';}
.alert-error::before{content:'⚠';}
/* Form */
.form-group{
    margin-bottom:20px;
}
.form-label{
    display:block;
    margin-bottom:8px;
    color:#f4d03f;
    font-weight:600;
    font-size:14px;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
.form-input{
    width:100%;
    padding:12px 16px;
    border-radius:10px;
    border:1px solid rgba(212,175,55,0.3);
    background:rgba(26,26,26,0.6);
    color:#ffffff;
    font-size:15px;
    font-family:'Inter',sans-serif;
    transition:all 0.3s;
}
.form-input:focus{
    outline:none;
    border-color:rgba(212,175,55,0.6);
    box-shadow:0 0 0 3px rgba(212,175,55,0.1),0 0 20px rgba(212,175,55,0.2);
    background:rgba(26,26,26,0.8);
}
.form-input::placeholder{
    color:rgba(255,255,255,0.3);
}
.form-help{
    display:block;
    margin-top:6px;
    color:rgba(255,255,255,0.5);
    font-size:13px;
}
/* Form Row */
.form-row{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px;
}
/* Button */
.btn-group{
    display:flex;
    gap:12px;
    margin-top:32px;
}
.btn{
    flex:1;
    padding:12px 24px;
    border-radius:10px;
    font-size:15px;
    font-weight:600;
    text-align:center;
    text-decoration:none;
    cursor:pointer;
    transition:all 0.3s;
    border:none;
    font-family:'Inter',sans-serif;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}
.btn-primary{
    background:linear-gradient(135deg,#d4af37,#f4d03f);
    color:#000;
    box-shadow:0 0 20px rgba(212,175,55,0.3);
}
.btn-primary:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 16px rgba(212,175,55,0.4),0 0 30px rgba(212,175,55,0.3);
}
.btn-primary:active{
    transform:translateY(0);
}
.btn-secondary{
    background:rgba(26,26,26,0.8);
    color:rgba(255,255,255,0.7);
    border:1px solid rgba(212,175,55,0.3);
}
.btn-secondary:hover{
    background:rgba(212,175,55,0.1);
    color:#f4d03f;
    border-color:rgba(212,175,55,0.5);
}
.back-link{
    display:inline-flex;
    align-items:center;
    gap:8px;
    color:rgba(255,255,255,0.6);
    text-decoration:none;
    font-size:14px;
    font-weight:500;
    margin-bottom:20px;
    padding:10px 18px;
    border-radius:10px;
    transition:all 0.3s;
    background:rgba(26,26,26,0.6);
    border:1px solid rgba(212,175,55,0.2);
    backdrop-filter:blur(10px);
}
/* Info Box */
.info-box{
    background:linear-gradient(135deg,rgba(212,175,55,0.1),rgba(212,175,55,0.05));
    border:1px solid rgba(212,175,55,0.3);
    border-radius:12px;
    padding:14px 16px;
    margin-bottom:24px;
    font-size:13px;
    color:rgba(255,255,255,0.8);
    line-height:1.6;
}
.info-box strong{
    display:block;
    margin-bottom:6px;
    color:#f4d03f;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
/* Responsive */
@media(max-width:640px){
    .card-header{
        padding:24px 20px;
    }
    .card-header h2{
        font-size:24px;
    }
    .card-body{
        padding:24px 20px;
    }
    .form-row{
        grid-template-columns:1fr;
    }
    .btn-group{
        flex-direction:column;
    }
}
</style>
</head>
<body>
<div class="container">
    <a href="/index.php" class="back-link">Ana Sayfa</a>

    <div class="card">
        <div class="card-header">
            <div class="card-header-icon">🚌</div>
            <h2>Yeni Sefer Ekle</h2>
            <p>Firmanız için yeni bir sefer oluşturun</p>
        </div>
        <div class="card-body">
            <?php if($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="info-box">
                <strong>📅 Tarih ve Saat Formatı:</strong>
                Tarih: GG/AA/YYYY (Örn: 15/12/2025) • Saat: HH.MM (Örn: 14.30)
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label class="form-label" for="departure_city">Kalkış Şehri *</label>
                    <input type="text" id="departure_city" name="departure_city" class="form-input"
                           placeholder="Örn: İstanbul" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="destination_city">Varış Şehri *</label>
                    <input type="text" id="destination_city" name="destination_city" class="form-input"
                           placeholder="Örn: Ankara" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="departure_date">Kalkış Tarihi *</label>
                        <input type="text" id="departure_date" name="departure_date" class="form-input"
                               placeholder="GG/AA/YYYY" required>
                        <small class="form-help">Örn: 15/12/2025</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="departure_time">Kalkış Saati *</label>
                        <input type="text" id="departure_time" name="departure_time" class="form-input"
                               placeholder="HH.MM" required>
                        <small class="form-help">Örn: 14.30</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="arrival_date">Varış Tarihi *</label>
                        <input type="text" id="arrival_date" name="arrival_date" class="form-input"
                               placeholder="GG/AA/YYYY" required>
                        <small class="form-help">Örn: 15/12/2025</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="arrival_time">Varış Saati *</label>
                        <input type="text" id="arrival_time" name="arrival_time" class="form-input"
                               placeholder="HH.MM" required>
                        <small class="form-help">Örn: 20.45</small>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="price">Bilet Fiyatı (₺) *</label>
                        <input type="number" id="price" name="price" class="form-input"
                               placeholder="250.00" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="capacity">Kapasite *</label>
                        <input type="number" id="capacity" name="capacity" class="form-input"
                               placeholder="40" min="1" required>
                        <small class="form-help">Toplam koltuk sayısı</small>
                    </div>
                </div>
                <div class="btn-group">
                    <a href="/firma/firma_panel.php" class="btn btn-secondary">İptal</a>
                    <button type="submit" name="create_trip" class="btn btn-primary">
                        🚌 Sefer Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>