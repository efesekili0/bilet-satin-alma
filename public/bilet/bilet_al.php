<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../includes/database.php';

$role = $_SESSION['role'] ?? 'visitor';
$user_id = $_SESSION['user_id'] ?? null;
if ($role !== 'user' || !$user_id) {
$_SESSION['error'] = 'Bu sayfaya erişim yetkiniz yok.';
header('Location: /login.php');
exit;
}
if (!isset($_SESSION['csrf_token'])) {
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
$_SESSION['error'] = 'Bu sayfaya direkt erişim yapılamaz.';
header('Location: /index.php');
exit;
}
$trip_id = $_POST['trip_id'] ?? '';
$seats = $_POST['seats'] ?? '';
if (empty($trip_id) || empty($seats)) {
$_SESSION['error'] = 'Eksik bilgi.';
header('Location: /index.php');
exit;
}
if (!preg_match('/^[0-9,]+$/', $seats)) {
$_SESSION['error'] = 'Geçersiz koltuk formatı.';
header('Location: /index.php');
exit;
}
$selected_seats = array_filter(array_map('intval', explode(',', $seats)), fn($s) => $s > 0 && $s <= 100);
if (empty($selected_seats)) {
$_SESSION['error'] = 'Geçerli koltuk seçmediniz.';
header('Location: /index.php');
exit;
}
if (count($selected_seats) > 10) {
$_SESSION['error'] = 'Maksimum 10 koltuk seçebilirsiniz.';
header('Location: /index.php');
exit;
}

function updateExpiredTickets($pdo) {
$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("UPDATE Tickets SET status='expired' WHERE status='active' AND id IN (SELECT t.id FROM Tickets t JOIN Trips tr ON t.trip_id = tr.id WHERE datetime(tr.departure_time) < datetime(?))");
$stmt->execute([$now]);
}
updateExpiredTickets($pdo);
try {
$stmt = $pdo->prepare("SELECT t.*, b.name AS company_name FROM Trips t JOIN Bus_Company b ON t.company_id = b.id WHERE t.id = ?");
$stmt->execute([$trip_id]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$trip) throw new Exception('Sefer bulunamadı.');
if (strtotime($trip['departure_time']) < time()) throw new Exception('Bu sefer için bilet satışı sona ermiştir.');
} catch(Exception $e) {
$_SESSION['error'] = $e->getMessage();
header('Location: /index.php');
exit;
}
try {
$stmt = $pdo->prepare("SELECT id, fullname, email, balance FROM User WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) { session_destroy(); header('Location: /login.php'); exit; }
} catch(Exception $e) {
$_SESSION['error'] = 'Bir hata oluştu.';
header('Location: /index.php');
exit;
}

$total_price = intval($trip['price']) * count($selected_seats);
$discount = 0;
$coupon_code = '';
$coupon_id = null;
$final_price = $total_price;

if (isset($_POST['apply_coupon'])) {
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) $error = 'Güvenlik hatası.';
else {
$coupon_code = trim($_POST['coupon_code'] ?? '');
if (!empty($coupon_code) && preg_match('/^[A-Z0-9\-]{3,20}$/i', $coupon_code)) {
try {
$stmt = $pdo->prepare("SELECT * FROM Coupons WHERE code=? AND (company_id IS NULL OR company_id=?) AND DATE(expire_date) >= DATE('now') AND usage_limit>0");
$stmt->execute([$coupon_code, $trip['company_id']]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);
if ($coupon) {
$stmt = $pdo->prepare("SELECT COUNT(*) FROM User_Coupons WHERE user_id=? AND coupon_id=?");
$stmt->execute([$user_id, $coupon['id']]);
if ($stmt->fetchColumn()==0) {
$discount = floatval($coupon['discount']);
$coupon_id = $coupon['id'];
$final_price = intval($total_price*(1-$discount/100));
$success = 'Kupon uygulandı! %'.number_format($discount,0).' indirim.';
 } else $error='Bu kuponu daha önce kullandınız.';
 } else $error='Geçersiz kupon kodu veya kullanım süresi dolmuş.';
 } catch(Exception $e) { $error='Bir hata oluştu.'; }
 } else $error='Geçersiz kupon formatı.';
 }
}

if (isset($_POST['complete_payment'])) {
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) $error='Güvenlik hatası.';
else {
$final_price = intval($_POST['final_price'] ?? $total_price);
$coupon_code = trim($_POST['coupon_code'] ?? '');
try {
$pdo->beginTransaction();
$stmt = $pdo->prepare("SELECT balance FROM User WHERE id=?");
$stmt->execute([$user_id]);
$current_balance=intval($stmt->fetchColumn());
if ($current_balance<$final_price) throw new Exception('Yetersiz bakiye!');
$placeholders=implode(',', array_fill(0,count($selected_seats),'?'));
$stmt=$pdo->prepare("SELECT bs.seat_number FROM Booked_Seats bs JOIN Tickets t ON t.id=bs.ticket_id WHERE t.trip_id=? AND t.status='active' AND bs.seat_number IN ($placeholders)");
$stmt->execute(array_merge([$trip_id],$selected_seats));
$occupied=$stmt->fetchAll(PDO::FETCH_COLUMN);
if(!empty($occupied)) throw new Exception('Seçtiğiniz koltuklar artık dolu: '.implode(', ',$occupied));
if (!empty($coupon_code)) {
$stmt=$pdo->prepare("SELECT * FROM Coupons WHERE code=? AND (company_id IS NULL OR company_id=?) AND usage_limit>0");
$stmt->execute([$coupon_code, $trip['company_id']]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);
if ($coupon) {
$stmt=$pdo->prepare("UPDATE Coupons SET usage_limit=usage_limit-1 WHERE id=? AND usage_limit>0");
$stmt->execute([$coupon['id']]);
$user_coupon_id=bin2hex(random_bytes(16));
$stmt=$pdo->prepare("INSERT INTO User_Coupons (id,coupon_id,user_id,created_at) VALUES (?,?,?,DATETIME('now'))");
$stmt->execute([$user_coupon_id,$coupon['id'],$user_id]);
 }
 }
$ticket_id=bin2hex(random_bytes(16));
$stmt=$pdo->prepare("INSERT INTO Tickets (id,trip_id,user_id,status,total_price,created_at) VALUES (?,?,?,?,?,DATETIME('now'))");
$stmt->execute([$ticket_id,$trip_id,$user_id,'active',$final_price]);
$stmt=$pdo->prepare("INSERT INTO Booked_Seats (id,ticket_id,seat_number,created_at) VALUES (?,?,?,DATETIME('now'))");
foreach($selected_seats as $seat){
$seat_id=bin2hex(random_bytes(16));
$stmt->execute([$seat_id,$ticket_id,$seat]);
 }
$stmt=$pdo->prepare("UPDATE User SET balance=balance-? WHERE id=?");
$stmt->execute([$final_price,$user_id]);
$pdo->commit();
$_SESSION['csrf_token']=bin2hex(random_bytes(32));
$_SESSION['success']='Biletiniz başarıyla oluşturuldu!';
header('Location: /hesabim.php');
exit;
 } catch(Exception $e) {
$pdo->rollBack();
$error=$e->getMessage();
 }
 }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ödeme - VatanBilet</title>
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
padding: 40px 20px;
color: #e2e8f0;
position: relative;
}
body::before{
content:'';
position:fixed;
width:600px;
height:600px;
background:radial-gradient(circle,rgba(212,175,55,0.08) 0%,transparent 70%);
border-radius:50%;
top:-300px;
right:-300px;
z-index:0;
animation:pulse 4s ease-in-out infinite;
}
body::after{
content:'';
position:fixed;
width:500px;
height:500px;
background:radial-gradient(circle,rgba(212,175,55,0.06) 0%,transparent 70%);
border-radius:50%;
bottom:-250px;
left:-250px;
z-index:0;
animation:pulse 5s ease-in-out infinite;
}
@keyframes pulse{
0%,100%{transform:scale(1);opacity:1;}
50%{transform:scale(1.1);opacity:0.8;}
}
.container {
max-width: 800px;
margin: 0 auto;
position: relative;
z-index: 1;
}
.back-link {
display: inline-flex;
align-items: center;
gap: 0.5rem;
color: rgba(255,255,255,0.6);
text-decoration: none;
font-weight: 500;
margin-bottom: 1.5rem;
padding: 0.625rem 1.25rem;
background: rgba(26,26,26,0.6);
border-radius: 10px;
border: 1px solid rgba(212,175,55,0.2);
transition: all 0.3s ease;
backdrop-filter: blur(10px);
}
.back-link:hover {
background: rgba(212,175,55,0.1);
color: #f4d03f;
border-color: rgba(212,175,55,0.4);
transform: translateX(-4px);
}
.back-link::before {
content: '←';
font-size: 1.125rem;
}
.payment-card {
background: linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
border-radius: 20px;
box-shadow: 0 8px 32px rgba(0,0,0,0.6),0 0 60px rgba(212,175,55,0.15);
border: 1px solid rgba(212,175,55,0.2);
overflow: hidden;
}
.payment-header {
background: linear-gradient(135deg,rgba(212,175,55,0.2) 0%,rgba(244,208,63,0.1) 100%);
padding: 2.5rem 2rem;
border-bottom: 1px solid rgba(212,175,55,0.2);
position: relative;
overflow: hidden;
}
.payment-header::before {
content: '';
position: absolute;
top: 0;
left: 0;
right: 0;
bottom: 0;
background: radial-gradient(circle at 50% 0%,rgba(212,175,55,0.1),transparent 70%);
z-index: 0;
}
.payment-header h1 {
font-size: 1.875rem;
font-weight: 700;
background: linear-gradient(135deg,#ffffff 0%,#d4af37 50%,#f4d03f 100%);
-webkit-background-clip: text;
-webkit-text-fill-color: transparent;
background-clip: text;
margin-bottom: 0.5rem;
position: relative;
z-index: 1;
text-shadow: 0 0 30px rgba(212,175,55,0.3);
}
.payment-header p {
color: rgba(255,255,255,0.7);
font-size: 0.9375rem;
position: relative;
z-index: 1;
}
.payment-body {
padding: 2rem;
}
.alert {
padding: 1rem 1.25rem;
border-radius: 12px;
margin-bottom: 1.5rem;
font-size: 0.9375rem;
font-weight: 500;
backdrop-filter: blur(10px);
border: 1px solid;
}
.alert-success {
background: linear-gradient(135deg,rgba(16,185,129,0.15),rgba(5,150,105,0.1));
color: #34d399;
border-color: rgba(16,185,129,0.3);
box-shadow: 0 0 20px rgba(16,185,129,0.1);
}
.alert-error {
background: linear-gradient(135deg,rgba(239,68,68,0.15),rgba(220,38,38,0.1));
color: #fca5a5;
border-color: rgba(239,68,68,0.3);
box-shadow: 0 0 20px rgba(239,68,68,0.1);
}
.section {
margin-bottom: 2rem;
}
.section-title {
font-size: 0.8125rem;
font-weight: 700;
color: #f4d03f;
text-transform: uppercase;
letter-spacing: 0.05em;
margin-bottom: 1rem;
text-shadow: 0 0 10px rgba(212,175,55,0.3);
}
.info-card {
background: rgba(15,15,15,0.8);
border: 1px solid rgba(212,175,55,0.2);
border-radius: 12px;
padding: 1.5rem;
backdrop-filter: blur(10px);
}
.info-row {
display: flex;
justify-content: space-between;
padding: 0.875rem 0;
border-bottom: 1px solid rgba(212,175,55,0.1);
}
.info-row:last-child {
border-bottom: none;
}
.info-label {
color: rgba(255,255,255,0.6);
font-size: 0.9375rem;
}
.info-value {
color: #e2e8f0;
font-weight: 600;
font-size: 0.9375rem;
}
.seats-grid {
display: flex;
gap: 0.625rem;
flex-wrap: wrap;
margin-top: 1rem;
}
.seat-item {
background: linear-gradient(135deg, #d4af37, #f4d03f);
color: #000;
padding: 0.625rem 1rem;
border-radius: 8px;
font-size: 0.9375rem;
font-weight: 700;
box-shadow: 0 0 20px rgba(212,175,55,0.3);
}
.coupon-form {
display: flex;
gap: 0.75rem;
}
.coupon-input {
flex: 1;
padding: 0.875rem 1rem;
background: rgba(26,26,26,0.6);
border: 1px solid rgba(212,175,55,0.3);
border-radius: 10px;
font-size: 0.9375rem;
font-family: inherit;
text-transform: uppercase;
font-weight: 500;
color: #e2e8f0;
transition: all 0.3s;
}
.coupon-input:focus {
outline: none;
border-color: rgba(212,175,55,0.6);
box-shadow: 0 0 0 3px rgba(212,175,55,0.1),0 0 20px rgba(212,175,55,0.2);
background: rgba(26,26,26,0.8);
}
.coupon-input::placeholder {
color: rgba(255,255,255,0.3);
}
.btn-coupon {
padding: 0.875rem 1.75rem;
background: linear-gradient(135deg,rgba(16,185,129,0.9),rgba(5,150,105,0.9));
color: white;
border: none;
border-radius: 10px;
font-weight: 600;
font-size: 0.9375rem;
cursor: pointer;
transition: all 0.3s ease;
box-shadow: 0 0 20px rgba(16,185,129,0.3);
}
.btn-coupon:hover {
transform: translateY(-2px);
box-shadow: 0 4px 12px rgba(16,185,129,0.4);
}
.price-summary {
background: rgba(15,15,15,0.8);
border: 1px solid rgba(212,175,55,0.2);
border-radius: 12px;
padding: 1.5rem;
backdrop-filter: blur(10px);
}
.price-row {
display: flex;
justify-content: space-between;
padding: 0.875rem 0;
font-size: 1rem;
color: #e2e8f0;
}
.price-row.discount {
color: #34d399;
font-weight: 600;
}
.price-row.total {
font-size: 1.5rem;
font-weight: 700;
border-top: 2px solid rgba(212,175,55,0.3);
padding-top: 1rem;
margin-top: 0.75rem;
background: linear-gradient(135deg,#d4af37,#f4d03f);
-webkit-background-clip: text;
-webkit-text-fill-color: transparent;
background-clip: text;
}
.balance-box {
background: linear-gradient(135deg,rgba(212,175,55,0.2),rgba(244,208,63,0.1));
border: 1px solid rgba(212,175,55,0.3);
border-radius: 12px;
padding: 1.25rem;
text-align: center;
font-weight: 700;
color: #f4d03f;
margin: 1.5rem 0;
font-size: 1.125rem;
backdrop-filter: blur(10px);
box-shadow: 0 0 20px rgba(212,175,55,0.1);
text-shadow: 0 0 10px rgba(212,175,55,0.3);
}
.btn-payment {
width: 100%;
padding: 1.125rem;
background: linear-gradient(135deg, #d4af37, #f4d03f);
color: #000;
border: none;
border-radius: 12px;
font-size: 1.0625rem;
font-weight: 700;
cursor: pointer;
transition: all 0.3s ease;
font-family: inherit;
box-shadow: 0 0 20px rgba(212,175,55,0.3);
}
.btn-payment:hover:not(:disabled) {
transform: translateY(-2px);
box-shadow: 0 4px 16px rgba(212,175,55,0.4),0 0 30px rgba(212,175,55,0.3);
}
.btn-payment:disabled {
background: rgba(75,85,99,0.5);
cursor: not-allowed;
box-shadow: none;
color: rgba(255,255,255,0.4);
}
@media(max-width: 768px) {
body {
padding: 20px 10px;
 }
.payment-header,
.payment-body {
padding: 1.5rem;
 }
.payment-header h1 {
font-size: 1.5rem;
 }
.coupon-form {
flex-direction: column;
 }
.info-row {
flex-direction: column;
gap: 0.25rem;
 }
}
</style>
</head>
<body>
<div class="container">
<a href="/index.php" class="back-link">Ana Sayfaya Dön</a>
<div class="payment-card">
<div class="payment-header">
<h1>💳 Ödeme İşlemi</h1>
<p>Bilet bilgilerinizi kontrol edin ve ödemeyi tamamlayın</p>
</div>
<div class="payment-body">
<?php if($error): ?>
<div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if($success): ?>
<div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<div class="section">
<div class="section-title">🚌 Sefer Bilgileri</div>
<div class="info-card">
<div class="info-row">
<span class="info-label">Otobüs Firması</span>
<span class="info-value"><?= htmlspecialchars($trip['company_name']) ?></span>
</div>
<div class="info-row">
<span class="info-label">Kalkış</span>
<span class="info-value"><?= htmlspecialchars($trip['departure_city']) ?> - <?= date('d/m/Y H:i', strtotime($trip['departure_time'])) ?></span>
</div>
<div class="info-row">
<span class="info-label">Varış</span>
<span class="info-value"><?= htmlspecialchars($trip['destination_city']) ?> - <?= date('d/m/Y H:i', strtotime($trip['arrival_time'])) ?></span>
</div>
</div>
</div>
<div class="section">
<div class="section-title">💺 Seçilen Koltuklar</div>
<div class="seats-grid">
<?php foreach($selected_seats as $seat): ?>
<span class="seat-item"><?= htmlspecialchars($seat) ?></span>
<?php endforeach; ?>
</div>
</div>
<div class="section">
<div class="section-title">🎟️ İndirim Kuponu</div>
<form method="POST" class="coupon-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="trip_id" value="<?= htmlspecialchars($trip_id) ?>">
<input type="hidden" name="seats" value="<?= htmlspecialchars($seats) ?>">
<input type="text" name="coupon_code" class="coupon-input" placeholder="Kupon kodu giriniz" value="<?= htmlspecialchars($coupon_code) ?>" maxlength="20">
<button type="submit" name="apply_coupon" class="btn-coupon">Uygula</button>
</form>
</div>
<div class="section">
<div class="section-title">📊 Ödeme Özeti</div>
<div class="price-summary">
<div class="price-row">
<span>Bilet Adedi</span>
<span><?= count($selected_seats) ?> adet</span>
</div>
<div class="price-row">
<span>Ara Toplam</span>
<span><?= number_format($total_price, 2) ?> ₺</span>
</div>
<?php if($discount > 0): ?>
<div class="price-row discount">
<span>İndirim (%<?= number_format($discount, 0) ?>)</span>
<span>-<?= number_format($total_price * $discount / 100, 2) ?> ₺</span>
</div>
<?php endif; ?>
<div class="price-row total">
<span>Toplam Tutar</span>
<span><?= number_format($final_price, 2) ?> ₺</span>
</div>
</div>
</div>
<div class="balance-box">
💰 Mevcut Bakiyeniz: <?= number_format($user['balance'], 2) ?> ₺
</div>
<form method="POST">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
<input type="hidden" name="trip_id" value="<?= htmlspecialchars($trip_id) ?>">
<input type="hidden" name="seats" value="<?= htmlspecialchars($seats) ?>">
<input type="hidden" name="coupon_code" value="<?= htmlspecialchars($coupon_code) ?>">
<input type="hidden" name="final_price" value="<?= htmlspecialchars($final_price) ?>">
<button type="submit" name="complete_payment" class="btn-payment" <?= ($user['balance'] < $final_price) ? 'disabled' : '' ?>>
<?= ($user['balance'] < $final_price) ? '❌ Yetersiz Bakiye' : '✅ Ödemeyi Tamamla' ?>
</button>
</form>
</div>
</div>
</div>
</body>
</html>