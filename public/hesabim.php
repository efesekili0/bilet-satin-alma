<?php
ob_start();
session_start();
require_once __DIR__ . '/includes/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}


$stmt = $pdo->prepare("SELECT id, fullname, email, balance FROM User WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


$stmt2 = $pdo->prepare("
    SELECT 
        tk.id AS ticket_id,
        tr.departure_city,
        tr.destination_city,
        tr.departure_time,
        tr.arrival_time,
        tk.total_price AS price,   -- Trips.price yerine Tickets.total_price
        b.name AS company_name
    FROM Tickets tk
    JOIN Trips tr ON tk.trip_id = tr.id
    JOIN Bus_Company b ON tr.company_id = b.id
    WHERE tk.user_id = ?
");
$stmt2->execute([$_SESSION['user_id']]);
$tickets = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if(isset($_POST['update_password'])){
    $new_pass = $_POST['new_password'];
    if(strlen($new_pass) >= 6){
        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE User SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $_SESSION['user_id']]);
        $password_success = "Şifre başarıyla güncellendi!";
    } else {
        $password_error = "Şifre en az 8 karakter olmalı!";
    }
}

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Hesabım - VatanBilet</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #0a0a0a;
    color: #e4e6eb;
    line-height: 1.6;
    min-height: 100vh;
}

.header {
    background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
    border-bottom: 1px solid rgba(212,175,55,0.2);
    padding: 1.5rem 0;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5), 0 0 40px rgba(212,175,55,0.1);
}

.header-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    background: linear-gradient(135deg, #ffffff 0%, #d4af37 50%, #f4d03f 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 30px rgba(212,175,55,0.3);
}

.container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 20px;
}

.profile-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.card {
    background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    border: 1px solid rgba(212,175,55,0.2);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: 0 8px 20px rgba(212,175,55,0.2);
    transform: translateY(-2px);
    border-color: rgba(212,175,55,0.3);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid rgba(212,175,55,0.2);
}

.card-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    box-shadow: 0 0 20px rgba(212,175,55,0.3);
}

.card-header h3 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #f4d03f;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.875rem 0;
    border-bottom: 1px solid rgba(212,175,55,0.1);
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 0.875rem;
    color: rgba(255,255,255,0.5);
    font-weight: 500;
}

.info-value {
    font-size: 0.9375rem;
    color: #ffffff;
    font-weight: 500;
}

.balance-card .info-value {
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 20px rgba(212,175,55,0.3);
}

.tickets-section {
    background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    border: 1px solid rgba(212,175,55,0.2);
    margin-bottom: 2rem;
}

.ticket-table-wrapper {
    overflow-x: auto;
    margin-top: 1.5rem;
    border-radius: 12px;
    border: 1px solid rgba(212,175,55,0.2);
}

.ticket-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.ticket-table thead {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
}

.ticket-table th {
    padding: 1rem;
    text-align: left;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #000;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ticket-table td {
    padding: 1.25rem 1rem;
    font-size: 0.9375rem;
    color: rgba(255,255,255,0.8);
    border-bottom: 1px solid rgba(212,175,55,0.1);
    background: rgba(26,26,26,0.6);
}

.ticket-table tbody tr {
    transition: background-color 0.2s ease;
}

.ticket-table tbody tr:hover {
    background: rgba(212,175,55,0.05);
}

.ticket-table tbody tr:hover td {
    background: rgba(212,175,55,0.05);
}

.ticket-id {
    font-weight: 600;
    color: #f4d03f;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.company-name {
    font-weight: 500;
    color: #ffffff;
}

.price {
    font-weight: 600;
    color: #34d399;
}

.pdf-btn {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    color: #000;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(212,175,55,0.3);
}

.pdf-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(212,175,55,0.5);
}

.pdf-btn:active {
    transform: translateY(0);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255,255,255,0.5);
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.3;
    filter: grayscale(1);
}

.password-section {
    background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    border: 1px solid rgba(212,175,55,0.2);
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    color: #f4d03f;
    margin-bottom: 0.5rem;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.9375rem;
    background: rgba(26,26,26,0.6);
    border: 1px solid rgba(212,175,55,0.3);
    border-radius: 10px;
    transition: all 0.3s ease;
    font-family: inherit;
    color: #ffffff;
}

.form-input:focus {
    outline: none;
    border-color: rgba(212,175,55,0.6);
    box-shadow: 0 0 0 3px rgba(212,175,55,0.1), 0 0 20px rgba(212,175,55,0.2);
    background: rgba(26,26,26,0.8);
}

.form-input::placeholder {
    color: rgba(255,255,255,0.3);
}

.btn-primary {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    color: #000;
    border: none;
    padding: 0.875rem 2rem;
    border-radius: 10px;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(212,175,55,0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(212,175,55,0.5);
}

.btn-primary:active {
    transform: translateY(0);
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


.alert {
    padding: 1rem 1.25rem;
    border-radius: 10px;
    margin-top: 1rem;
    font-size: 0.9375rem;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

.alert-success {
    background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(5,150,105,0.1));
    color: #34d399;
    border: 1px solid rgba(16,185,129,0.3);
    box-shadow: 0 0 20px rgba(16,185,129,0.1);
}

.alert-error {
    background: linear-gradient(135deg, rgba(239,68,68,0.15), rgba(220,38,38,0.1));
    color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.3);
    box-shadow: 0 0 20px rgba(239,68,68,0.1);
}

@media(max-width: 768px) {
    .container {
        padding: 0 1rem;
    }
    
    .card {
        padding: 1.5rem;
    }
    
    .ticket-table th,
    .ticket-table td {
        padding: 0.75rem 0.5rem;
        font-size: 0.875rem;
    }
    
    .header h1 {
        font-size: 1.5rem;
    }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-content">
        <h1>Hesap Yönetimi</h1>
    </div>
</div>

<div class="container">
    <a href="/index.php" class="back-link">Ana Sayfa</a>
    
    <div class="profile-section">
        <!-- Profil Bilgileri -->
        <div class="card">
            <div class="card-header">
                <div class="card-icon">👤</div>
                <h3>Profil Bilgileri</h3>
            </div>
            <div class="info-row">
                <span class="info-label">Ad Soyad</span>
                <span class="info-value"><?= e($user['fullname']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">E-posta</span>
                <span class="info-value"><?= e($user['email']) ?></span>
            </div>
        </div>

        <!-- Bakiye -->
        <div class="card balance-card">
            <div class="card-header">
                <div class="card-icon">💳</div>
                <h3>Kredi Bilgisi</h3>
            </div>
            <div class="info-row">
                <span class="info-label">Mevcut Bakiye</span>
                <span class="info-value"><?= number_format($user['balance'], 2) ?> ₺</span>
            </div>
        </div>
    </div>

    <!-- Bilet Geçmişi -->
    <div class="tickets-section">
        <div class="card-header">
            <div class="card-icon">🎫</div>
            <h3>Bilet Geçmişi</h3>
        </div>
        
        <?php if(count($tickets) > 0): ?>
        <div class="ticket-table-wrapper">
            <table class="ticket-table">
                <thead>
                    <tr>
                        <th>Bilet No</th>
                        <th>Şirket</th>
                        <th>Kalkış</th>
                        <th>Varış</th>
                        <th>Kalkış Zamanı</th>
                        <th>Varış Zamanı</th>
                        <th>Tutar</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tickets as $ticket): ?>
                    <tr>
                        <td class="ticket-id">#<?= e($ticket['ticket_id']) ?></td>
                        <td class="company-name"><?= e($ticket['company_name']) ?></td>
                        <td><?= e($ticket['departure_city']) ?></td>
                        <td><?= e($ticket['destination_city']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($ticket['departure_time'])) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($ticket['arrival_time'])) ?></td>
                        <td class="price"><?= number_format($ticket['price'], 2) ?> ₺</td>
                        <td>
                            <form action="/bilet/bilet_indir.php" method="POST" target="_blank" style="margin:0;">
                                <input type="hidden" name="ticket_id" value="<?= e($ticket['ticket_id']) ?>">
                                <button type="submit" class="pdf-btn">📄 PDF İndir</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">🎫</div>
            <p>Henüz satın alınmış biletiniz bulunmamaktadır.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Şifre Güncelle -->
    <div class="password-section">
        <div class="card-header">
            <div class="card-icon">🔒</div>
            <h3>Şifre Değiştir</h3>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="new_password">Yeni Şifre</label>
                <input 
                    type="password" 
                    id="new_password"
                    name="new_password" 
                    class="form-input"
                    placeholder="Minimum 6 karakter" 
                    required
                >
            </div>
            <button type="submit" name="update_password" class="btn-primary">🔐 Şifreyi Güncelle</button>
        </form>
        
        <?php if(isset($password_success)): ?>
            <div class="alert alert-success"><?= $password_success ?></div>
        <?php endif; ?>
        <?php if(isset($password_error)): ?>
            <div class="alert alert-error"><?= $password_error ?></div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>