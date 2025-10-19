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

$stmt = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company_id = $stmt->fetchColumn();
if (!$company_id) {
    $_SESSION['error'] = "Firma bilgisi bulunamadı!";
    header("Location: /firma/firma_panel.php");
    exit;
}

$trip_id = $_GET['id'] ?? '';
if (!$trip_id) {
    $_SESSION['error'] = "Sefer ID eksik!";
    header("Location: /firma/firma_panel.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_trip'])) {
    // CSRF kontrolü
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Geçersiz istek (CSRF).");
    }
    
    
    $departure_city = trim($_POST['departure_city']);
    $destination_city = trim($_POST['destination_city']);
    $departure_time_input = trim($_POST['departure_time']);
    $arrival_time_input = trim($_POST['arrival_time']);
    $price = floatval($_POST['price']);
    $capacity = intval($_POST['capacity']);
    
    
    $departure_dt = DateTime::createFromFormat('d.m.Y H:i', $departure_time_input);
    $arrival_dt = DateTime::createFromFormat('d.m.Y H:i', $arrival_time_input);
    
    if (!$departure_dt || !$arrival_dt) {
        $_SESSION['error'] = "Geçersiz tarih formatı! Lütfen GG.AA.YYYY SS:DD formatında girin.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $trip_id);
        exit;
    }
    
    $departure_time = $departure_dt->format('Y-m-d H:i:s');
    $arrival_time = $arrival_dt->format('Y-m-d H:i:s');
    

    $sql = "UPDATE Trips SET departure_city = ?, destination_city = ?, departure_time = ?, arrival_time = ?, price = ?, capacity = ?
            WHERE id = ? AND company_id = ?";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute([$departure_city, $destination_city, $departure_time, $arrival_time, $price, $capacity, $trip_id, $company_id])) {
        $_SESSION['success'] = "Sefer başarıyla güncellendi.";
    } else {
        $_SESSION['error'] = "Sefer güncellenirken hata oluştu.";
    }
    header("Location: /sefer/sefer_kaldir.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM Trips WHERE id = ? AND company_id = ?");
$stmt->execute([$trip_id, $company_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$trip) {
    $_SESSION['error'] = "Sefer bulunamadı veya yetkiniz yok.";
    header("Location: /firma/firma_panel.php");
    exit;
}
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Sefer Düzenle - VatanBilet</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body {
    font-family: 'Inter', sans-serif;
    background: #0a0a0a;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    position: relative;
    overflow-x: hidden;
}
body::before {
    content: '';
    position: absolute;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(212,175,55,0.08) 0%, transparent 70%);
    border-radius: 50%;
    top: -250px;
    right: -250px;
    z-index: 0;
    animation: pulse 4s ease-in-out infinite;
}
body::after {
    content: '';
    position: absolute;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(212,175,55,0.06) 0%, transparent 70%);
    border-radius: 50%;
    bottom: -200px;
    left: -200px;
    z-index: 0;
    animation: pulse 5s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
}
.container {
    background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
    padding: 45px 40px;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(212,175,55,0.1);
    border: 1px solid rgba(212,175,55,0.2);
    width: 100%;
    max-width: 550px;
    animation: slideUp 0.5s ease-out;
    position: relative;
    z-index: 1;
}
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.header {
    text-align: center;
    margin-bottom: 35px;
}
.header-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    box-shadow: 0 8px 20px rgba(212,175,55,0.4), 0 0 30px rgba(212,175,55,0.2);
}
.header-icon svg {
    width: 35px;
    height: 35px;
    stroke: #000;
    fill: none;
    stroke-width: 2;
}
h2 {
    background: linear-gradient(135deg, #ffffff 0%, #d4af37 50%, #f4d03f 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 8px;
    text-shadow: 0 0 30px rgba(212,175,55,0.3);
}
.subtitle {
    color: rgba(255,255,255,0.6);
    font-size: 14px;
    font-weight: 400;
}
.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 25px;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: alertSlide 0.4s ease-out;
    backdrop-filter: blur(10px);
}
@keyframes alertSlide {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.success {
    background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(5,150,105,0.1));
    color: #34d399;
    border: 1px solid rgba(16,185,129,0.3);
    box-shadow: 0 0 20px rgba(16,185,129,0.1);
}
.success::before {
    content: '✓';
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: #10b981;
    color: white;
    border-radius: 50%;
    font-weight: bold;
    flex-shrink: 0;
}
.error {
    background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(220,38,38,0.1));
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.3);
    box-shadow: 0 0 20px rgba(239,68,68,0.1);
}
.error::before {
    content: '✕';
    display: flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    font-weight: bold;
    flex-shrink: 0;
}
.form-group {
    margin-bottom: 22px;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}
label {
    display: block;
    color: #f4d03f;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
    letter-spacing: 0.3px;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}
input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid rgba(212,175,55,0.3);
    border-radius: 12px;
    font-size: 15px;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s ease;
    background: rgba(26,26,26,0.6);
    color: #ffffff;
}
input:focus {
    border-color: rgba(212,175,55,0.6);
    outline: none;
    background: rgba(26,26,26,0.8);
    box-shadow: 0 0 0 3px rgba(212,175,55,0.1), 0 0 20px rgba(212,175,55,0.2);
}
input::placeholder {
    color: rgba(255,255,255,0.3);
}
.hint {
    font-size: 12px;
    color: rgba(255,255,255,0.5);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.hint::before {
    content: 'ℹ️';
    font-size: 11px;
}
.button-group {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}
button, .btn-back {
    flex: 1;
    padding: 15px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
button[type="submit"] {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    color: #000;
    box-shadow: 0 4px 15px rgba(212,175,55,0.4);
}
button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(212,175,55,0.5);
}
button[type="submit"]:active {
    transform: translateY(0);
}
.btn-back {
    background: rgba(26,26,26,0.8);
    color: rgba(255,255,255,0.7);
    border: 1px solid rgba(212,175,55,0.3);
}
.btn-back:hover {
    background: rgba(212,175,55,0.1);
    color: #f4d03f;
    border-color: rgba(212,175,55,0.5);
}
.route-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 10px 0;
    color: #f4d03f;
    font-weight: 600;
    font-size: 18px;
    text-shadow: 0 0 15px rgba(212,175,55,0.4);
}
@media (max-width: 500px) {
    .container {
        padding: 35px 25px;
    }
    h2 {
        font-size: 24px;
    }
    .header-icon {
        width: 60px;
        height: 60px;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .button-group {
        flex-direction: column;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-icon">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
        </div>
        <h2>Sefer Düzenle</h2>
        <p class="subtitle">Sefer bilgilerini güncelleyin</p>
    </div>
    <?php if ($success): ?>
    <div class="alert success"><?=htmlspecialchars($success)?></div>
    <?php elseif ($error): ?>
    <div class="alert error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
        <div class="form-row">
            <div class="form-group">
                <label for="departure_city">Kalkış Şehri</label>
                <input type="text" id="departure_city" name="departure_city" value="<?=htmlspecialchars($trip['departure_city'])?>" required>
            </div>
            <div class="form-group">
                <label for="destination_city">Varış Şehri</label>
                <input type="text" id="destination_city" name="destination_city" value="<?=htmlspecialchars($trip['destination_city'])?>" required>
            </div>
        </div>
        <div class="route-indicator">→</div>
        <div class="form-row">
            <div class="form-group">
                <label for="departure_time">Kalkış Zamanı</label>
                <input type="text" id="departure_time" name="departure_time" value="<?=date('d.m.Y H:i', strtotime($trip['departure_time']))?>" placeholder="31.12.2025 14:30" required>
                <div class="hint">GG.AA.YYYY SS:DD</div>
            </div>
            <div class="form-group">
                <label for="arrival_time">Varış Zamanı</label>
                <input type="text" id="arrival_time" name="arrival_time" value="<?=date('d.m.Y H:i', strtotime($trip['arrival_time']))?>" placeholder="31.12.2025 20:30" required>
                <div class="hint">GG.AA.YYYY SS:DD</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="price">Bilet Fiyatı (₺)</label>
                <input type="number" id="price" name="price" step="0.01" value="<?=htmlspecialchars($trip['price'])?>" placeholder="150.00" required>
            </div>
            <div class="form-group">
                <label for="capacity">Kapasite</label>
                <input type="number" id="capacity" name="capacity" value="<?=htmlspecialchars($trip['capacity'])?>" placeholder="45" min="1" required>
            </div>
        </div>
        <div class="button-group">
            <a href="/sefer/sefer_kaldir.php" class="btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Geri
            </a>
            <button type="submit" name="update_trip">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Güncelle
            </button>
        </div>
    </form>
</div>
</body>
</html>