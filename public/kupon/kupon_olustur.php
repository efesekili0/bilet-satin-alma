<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';


if(!isset($_SESSION['user_id']) || $_SESSION["role"] !== 'firma_admin'){
    header("Location: /login.php");
    exit;
}

if(isset($_POST['create_coupon'])){
    $stmt = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $company_id = $stmt->fetchColumn();
    
    if(!$company_id){
        $_SESSION['error'] = "Firma bilgisi bulunamadı.";
        header("Location: /kupon/kupon_olustur.php");
        exit;
    }
    
    $id = uniqid('coup_');
    $code = strtoupper(trim($_POST['code']));
    $discount = min(max(floatval($_POST['discount']), 0), 100);
    $usage_limit = max(intval($_POST['usage_limit']), 1);
    $expire_date_input = trim($_POST['expire_date']);
    
    $dt = DateTime::createFromFormat('d.m.Y', $expire_date_input);
    if(!$dt || $dt->format('d.m.Y') !== $expire_date_input){
        $_SESSION['error'] = "Tarih formatı geçersiz! GG.AA.YYYY şeklinde olmalı.";
        header("Location: /kupon/kupon_olustur.php");
        exit;
    }
    
    $expire_date_db = $dt->format('Y-m-d');
    
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Coupons WHERE code = ?");
    $stmt_check->execute([$code]);
    if($stmt_check->fetchColumn() > 0){
        $_SESSION['error'] = "Bu kupon kodu zaten mevcut!";
        header("Location: /kupon/kupon_olustur.php");
        exit;
    }
    
    $sql = "INSERT INTO Coupons (id, code, discount, company_id, usage_limit, expire_date, created_at)
            VALUES (:id, :code, :discount, :company_id, :usage_limit, :expire_date, datetime('now'))";
    $stmt= $pdo->prepare($sql);
    
    if($stmt->execute([
        ':id' => $id,
        ':code' => $code,
        ':discount' => $discount,
        ':company_id' => $company_id,
        ':usage_limit' => $usage_limit,
        ':expire_date' => $expire_date_db
    ])){
        $_SESSION['success'] = "Kupon başarıyla oluşturuldu!";
    } else {
        $_SESSION['error'] = "Kupon eklenirken hata oluştu!";
    }
    
    header("Location: /kupon/kupon_olustur.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Kupon Oluştur - VatanBilet</title>
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
    max-width: 480px;
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
.form-group {
    margin-bottom: 22px;
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
    font-size: 13px;
    color: rgba(255,255,255,0.5);
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.hint::before {
    content: 'ℹ️';
    font-size: 12px;
}
button {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    color: #000;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 10px;
    box-shadow: 0 4px 15px rgba(212,175,55,0.4);
}
button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(212,175,55,0.5);
}
button:active {
    transform: translateY(0);
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
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header-icon">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="7.5 4.21 12 6.81 16.5 4.21"></polyline>
                <polyline points="7.5 19.79 7.5 14.6 3 12"></polyline>
                <polyline points="21 12 16.5 14.6 16.5 19.79"></polyline>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
        </div>
        <h2>Kupon Oluştur</h2>
        <p class="subtitle">Yeni indirim kuponu tanımlayın</p>
    </div>
    <?php
    if(isset($_SESSION['success'])) {
        echo '<div class="alert success">'.htmlspecialchars($_SESSION['success']).'</div>';
        unset($_SESSION['success']);
    }
    if(isset($_SESSION['error'])) {
        echo '<div class="alert error">'.htmlspecialchars($_SESSION['error']).'</div>';
        unset($_SESSION['error']);
    }
    ?>
    <form method="POST">
        <div class="form-group">
            <label for="code">Kupon Kodu</label>
            <input type="text" id="code" name="code" placeholder="Örn: YENI2025" required>
        </div>
        <div class="form-group">
            <label for="discount">İndirim Oranı (%)</label>
            <input type="number" id="discount" name="discount" placeholder="Örn: 15" step="0.01" min="0" max="100" required>
        </div>
        <div class="form-group">
            <label for="usage_limit">Kullanım Limiti</label>
            <input type="number" id="usage_limit" name="usage_limit" placeholder="Örn: 100" min="1" required>
        </div>
        <div class="form-group">
            <label for="expire_date">Son Kullanma Tarihi</label>
            <input type="text" id="expire_date" name="expire_date" placeholder="GG.AA.YYYY" required>
            <div class="hint">Örnek: 31.12.2025</div>
        </div>
        <button type="submit" name="create_coupon">🎟️ Kupon Oluştur</button>
    </form>
</div>
</body>
</html>