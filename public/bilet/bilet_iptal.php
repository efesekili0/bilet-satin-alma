<?php
date_default_timezone_set('Europe/Istanbul');
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';


$pdo->exec("
    UPDATE Tickets
    SET status = 'expired'
    WHERE status = 'active'
    AND id IN (
        SELECT tk.id
        FROM Tickets tk
        JOIN Trips tr ON tk.trip_id = tr.id
        WHERE tr.departure_time <= datetime('now')
    )
");


if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}


function e($s) { 
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$success_message = '';
$error_message = '';
$selected_ticket = null;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    $ticket_id = $_POST['ticket_id'] ?? '';
    
    try {
        $pdo->beginTransaction();
 
        $stmt = $pdo->prepare("
            SELECT 
                tk.id,
                tk.user_id,
                tk.total_price,
                tk.status,
                tr.departure_time
            FROM Tickets tk
            JOIN Trips tr ON tk.trip_id = tr.id
            WHERE tk.id = ? AND tk.user_id = ?
        ");
        $stmt->execute([$ticket_id, $_SESSION['user_id']]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ticket) {
            throw new Exception("Bilet bulunamadı veya size ait değil!");
        }
        
        if ($ticket['status'] === 'canceled') {
            throw new Exception("Bu bilet zaten iptal edilmiş!");
        }

        $now = new DateTime('now');
        
       
        $departure = DateTime::createFromFormat('Y-m-d H:i:s', $ticket['departure_time']);
        if (!$departure) {
            $departure = DateTime::createFromFormat('Y-m-d H:i', $ticket['departure_time']);
        }
        if (!$departure) {
            $departure = new DateTime($ticket['departure_time']);
        }
        
        $time_difference = $departure->getTimestamp() - $now->getTimestamp();
        
        if ($time_difference < 3600) {
            throw new Exception("Kalkış saatine 1 saatten az kaldı! İptal işlemi yapılamaz.");
        }

       
        $stmt = $pdo->prepare("UPDATE Tickets SET status = 'canceled' WHERE id = ?");
        $stmt->execute([$ticket_id]);

        
        $stmt = $pdo->prepare("UPDATE User SET balance = balance + ? WHERE id = ?");
        $stmt->execute([$ticket['total_price'], $_SESSION['user_id']]);

     
        $stmt = $pdo->prepare("DELETE FROM Booked_Seats WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);

        $pdo->commit();
        
        $success_message = "Biletiniz başarıyla iptal edildi! " . number_format($ticket['total_price'], 2) . " TL hesabınıza iade edildi.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_ticket'])) {
    $ticket_id = $_POST['ticket_id'] ?? '';
    
    $stmt = $pdo->prepare("
        SELECT 
            tk.id AS ticket_id,
            tk.total_price,
            tk.status,
            tr.departure_city,
            tr.destination_city,
            tr.departure_time,
            tr.arrival_time,
            b.name AS company_name,
            GROUP_CONCAT(bs.seat_number, ', ') AS seat_numbers
        FROM Tickets tk
        JOIN Trips tr ON tk.trip_id = tr.id
        JOIN Bus_Company b ON tr.company_id = b.id
        LEFT JOIN Booked_Seats bs ON bs.ticket_id = tk.id
        WHERE tk.id = ? AND tk.user_id = ? AND tk.status = 'active'
        GROUP BY tk.id
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $selected_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$selected_ticket) {
        $error_message = "Seçilen bilet bulunamadı veya size ait değil!";
    }
}

$stmt = $pdo->prepare("
    SELECT 
        tk.id AS ticket_id,
        tk.total_price,
        tk.status,
        tr.departure_city,
        tr.destination_city,
        tr.departure_time,
        tr.arrival_time,
        b.name AS company_name,
        GROUP_CONCAT(bs.seat_number, ', ') AS seat_numbers
    FROM Tickets tk
    JOIN Trips tr ON tk.trip_id = tr.id
    JOIN Bus_Company b ON tr.company_id = b.id
    LEFT JOIN Booked_Seats bs ON bs.ticket_id = tk.id
    WHERE tk.user_id = ? 
    AND tk.status = 'active'
    AND tr.departure_time > datetime('now')
    GROUP BY tk.id
    ORDER BY tr.departure_time ASC
");
$stmt->execute([$_SESSION['user_id']]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcı bilgisi
$stmt = $pdo->prepare("SELECT balance FROM User WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Bilet İptal - VatanBilet</title>
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
    padding: 40px 20px;
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
    max-width: 1200px;
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    padding: 1.5rem 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4),0 0 40px rgba(212,175,55,0.1);
    margin-bottom: 2rem;
    border: 1px solid rgba(212,175,55,0.2);
}

.header h1 {
    font-size: 1.75rem;
    font-weight: 700;
    background: linear-gradient(135deg,#ffffff 0%,#d4af37 50%,#f4d03f 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-shadow: 0 0 30px rgba(212,175,55,0.3);
}

.balance-badge {
    background: linear-gradient(135deg, #d4af37, #f4d03f);
    color: #000;
    font-weight: 700;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-size: 1rem;
    box-shadow: 0 0 20px rgba(212,175,55,0.3);
}

/* Alert Messages */
.alert {
    padding: 1rem 1.25rem;
    border-radius: 12px;
    margin-bottom: 1.5rem;
    font-weight: 500;
    animation: slideDown 0.4s ease-out;
    border: 1px solid;
    backdrop-filter: blur(10px);
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

/* Step Indicator */
.step-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius: 16px;
    border: 1px solid rgba(212,175,55,0.2);
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
}

.step {
    font-weight: 600;
    font-size: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.step.active {
    background: linear-gradient(135deg, #d4af37, #f4d03f);
    color: #000;
    box-shadow: 0 0 20px rgba(212,175,55,0.3);
}

.step.inactive {
    color: rgba(255,255,255,0.4);
}

.step-arrow {
    color: #d4af37;
    font-size: 1.25rem;
}

/* Tickets Table */
.tickets-section {
    background: linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    border: 1px solid rgba(212,175,55,0.2);
}

.section-header {
    margin-bottom: 1.5rem;
}

.section-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f4d03f;
    margin-bottom: 0.5rem;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.section-header p {
    color: rgba(255,255,255,0.6);
    font-size: 0.9375rem;
}

.table-wrapper {
    overflow-x: auto;
    margin-top: 1rem;
    border-radius: 12px;
    border: 1px solid rgba(212,175,55,0.2);
}

.tickets-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.tickets-table thead {
    background: rgba(26,26,26,0.8);
}

.tickets-table th {
    padding: 1rem;
    text-align: left;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #f4d03f;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid rgba(212,175,55,0.3);
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.tickets-table td {
    padding: 1.25rem 1rem;
    font-size: 0.9375rem;
    color: rgba(255,255,255,0.8);
    border-bottom: 1px solid rgba(212,175,55,0.1);
}

.tickets-table tbody tr {
    transition: all 0.2s ease;
}

.tickets-table tbody tr:hover {
    background: rgba(212,175,55,0.05);
}

.ticket-id {
    font-weight: 600;
    color: #f4d03f;
}

.route {
    font-weight: 600;
    color: #e4e6eb;
}

.price {
    font-weight: 600;
    color: #34d399;
}

/* Buttons */
.btn {
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0.625rem 1.5rem;
    font-size: 0.9375rem;
    font-family: inherit;
}

.btn-select {
    background: linear-gradient(135deg, #d4af37, #f4d03f);
    color: #000;
    box-shadow: 0 0 20px rgba(212,175,55,0.3);
}

.btn-select:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(212,175,55,0.4);
}

.btn-cancel {
    background: linear-gradient(135deg,rgba(239,68,68,0.9),rgba(220,38,38,0.9));
    color: #fff;
    box-shadow: 0 0 20px rgba(239,68,68,0.3);
}

.btn-cancel:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239,68,68,0.4);
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

.btn-primary {
    background: linear-gradient(135deg, #d4af37, #f4d03f);
    color: #000;
    padding: 0.875rem 2rem;
    box-shadow: 0 0 20px rgba(212,175,55,0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(212,175,55,0.4);
}

/* Confirmation Card */
.confirmation-card {
    background: linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius: 16px;
    padding: 2.5rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4),0 0 40px rgba(212,175,55,0.1);
    border: 1px solid rgba(212,175,55,0.2);
    max-width: 700px;
    margin: 0 auto;
}

.confirmation-card h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f4d03f;
    margin-bottom: 1.5rem;
    text-align: center;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.route-display {
    text-align: center;
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #ffffff 0%, #d4af37 50%, #f4d03f 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 1.5rem 0;
}

.ticket-details {
    background: rgba(15,15,15,0.8);
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1.5rem 0;
    border: 1px solid rgba(212,175,55,0.2);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.875rem 0;
    border-bottom: 1px solid rgba(212,175,55,0.1);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 500;
    color: rgba(255,255,255,0.6);
    font-size: 0.9375rem;
}

.detail-value {
    font-weight: 600;
    color: #e4e6eb;
    font-size: 0.9375rem;
}

.refund-amount {
    color: #34d399 !important;
    font-size: 1.25rem !important;
    font-weight: 700 !important;
}

/* Warning Boxes */
.warning-box {
    border-radius: 12px;
    padding: 1.25rem;
    text-align: center;
    font-weight: 500;
    margin: 1.5rem 0;
    border: 2px solid;
    backdrop-filter: blur(10px);
}

.warning-box.safe {
    background: linear-gradient(135deg,rgba(16,185,129,0.15),rgba(5,150,105,0.1));
    color: #34d399;
    border-color: rgba(16,185,129,0.3);
    box-shadow: 0 0 20px rgba(16,185,129,0.1);
}

.warning-box.danger {
    background: linear-gradient(135deg,rgba(239,68,68,0.15),rgba(220,38,38,0.1));
    color: #fca5a5;
    border-color: rgba(239,68,68,0.3);
    box-shadow: 0 0 20px rgba(239,68,68,0.1);
}

.warning-box strong {
    display: block;
    font-size: 1.125rem;
    margin-bottom: 0.5rem;
}

.warning-box small {
    display: block;
    margin-top: 0.5rem;
    opacity: 0.9;
}

/* Button Group */
.button-group {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.button-group form {
    flex: 1;
}

.button-group .btn {
    width: 100%;
}

/* Empty State */
.empty-state {
    background: linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    padding: 4rem 2rem;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    border: 1px solid rgba(212,175,55,0.2);
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
    filter: grayscale(1);
}

.empty-state h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #f4d03f;
    margin-bottom: 0.5rem;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.empty-state p {
    color: rgba(255,255,255,0.6);
    margin-bottom: 1.5rem;
}

/* Back Link */
.back-link {
    display: inline-block;
    margin-top: 2rem;
    text-align: center;
}

.back-link a {
    display: inline-block;
    background: linear-gradient(135deg, #d4af37, #f4d03f);
    color: #000;
    padding: 0.875rem 2rem;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 0 20px rgba(212,175,55,0.3);
}

.back-link a:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(212,175,55,0.4);
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media(max-width: 768px) {
    body {
        padding: 20px 10px;
    }
    
    .header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .header h1 {
        font-size: 1.5rem;
    }
    
    .tickets-section {
        padding: 1.5rem;
    }
    
    .tickets-table {
        font-size: 0.875rem;
    }
    
    .tickets-table th,
    .tickets-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .button-group {
        flex-direction: column;
    }
    
    .confirmation-card {
        padding: 1.5rem;
    }
    
    .route-display {
        font-size: 1.5rem;
    }
}
</style>
</head>
<body>

<div class="container">
    
    <div class="header">
        <h1>🎫 Bilet İptal İşlemleri</h1>
        <div class="balance-badge">
            💰 Bakiye: <?= number_format($user['balance'], 2) ?> TL
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            ✅ <?= e($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            ❌ <?= e($error_message) ?>
        </div>
    <?php endif; ?>

    <div class="step-indicator">
        <span class="step <?= $selected_ticket ? 'inactive' : 'active' ?>">1️⃣ Bilet Seç</span>
        <span class="step-arrow">→</span>
        <span class="step <?= $selected_ticket ? 'active' : 'inactive' ?>">2️⃣ İptal Et</span>
    </div>

    <?php if (!$selected_ticket): ?>
       
        <?php if (count($tickets) > 0): ?>
            <div class="tickets-section">
                <div class="section-header">
                    <h2>Aktif Biletleriniz</h2>
                    <p>İptal etmek istediğiniz bileti seçiniz</p>
                </div>
                
                <div class="table-wrapper">
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th>Bilet No</th>
                                <th>Şirket</th>
                                <th>Güzergah</th>
                                <th>Tarih</th>
                                <th>Kalkış</th>
                                <th>Koltuk</th>
                                <th>Ücret</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($tickets as $ticket): ?>
                            <tr>
                                <td class="ticket-id">#<?= e($ticket['ticket_id']) ?></td>
                                <td><?= e($ticket['company_name']) ?></td>
                                <td class="route"><?= e($ticket['departure_city']) ?> → <?= e($ticket['destination_city']) ?></td>
                                <td><?= date('d.m.Y', strtotime($ticket['departure_time'])) ?></td>
                                <td><?= date('H:i', strtotime($ticket['departure_time'])) ?></td>
                                <td><?= e($ticket['seat_numbers'] ?: '-') ?></td>
                                <td class="price"><?= number_format($ticket['total_price'], 2) ?> TL</td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="ticket_id" value="<?= e($ticket['ticket_id']) ?>">
                                        <button type="submit" name="select_ticket" class="btn btn-select">Seç</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">😔</div>
                <h2>Aktif Biletiniz Bulunmuyor</h2>
                <p>İptal edilebilecek biletiniz yok</p>
                <a href="/hesabim.php" class="btn btn-primary">← Hesabıma Dön</a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        
        <?php
            $now = new DateTime('now');
            
            
            $departure = DateTime::createFromFormat('Y-m-d H:i:s', $selected_ticket['departure_time']);
            
           
            if (!$departure) {
                $departure = DateTime::createFromFormat('Y-m-d H:i', $selected_ticket['departure_time']);
            }
            
           
            if (!$departure) {
                $departure = DateTime::createFromFormat('d.m.Y H:i', $selected_ticket['departure_time']);
            }
            if (!$departure) {
                $departure = DateTime::createFromFormat('d.m.Y H:i:s', $selected_ticket['departure_time']);
            }
            if (!$departure) {
                $departure = DateTime::createFromFormat('d/m/Y H:i', $selected_ticket['departure_time']);
            }
            
           
            if (!$departure) {
                $departure = new DateTime($selected_ticket['departure_time']);
            }
            
            $time_difference = $departure->getTimestamp() - $now->getTimestamp();
            
            
            $days_left = floor($time_difference / 86400);
            $hours_left = floor(($time_difference % 86400) / 3600);
            $minutes_left = floor(($time_difference % 3600) / 60);
            $can_cancel = $time_difference >= 3600;
       
            if ($days_left > 0) {
                $time_text = "$days_left gün $hours_left saat $minutes_left dakika";
            } else {
                $time_text = "$hours_left saat $minutes_left dakika";
            }

?>
    <div class="confirmation-card">
            <h2>⚠️ Bilet İptal Onayı</h2>

            <div class="route-display">
                <?= e($selected_ticket['departure_city']) ?> → <?= e($selected_ticket['destination_city']) ?>
            </div>

            <div class="ticket-details">
                <div class="detail-row">
                    <span class="detail-label">🎫 Bilet No</span>
                    <span class="detail-value"><?= e($selected_ticket['ticket_id']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">🏢 Şirket</span>
                    <span class="detail-value"><?= e($selected_ticket['company_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">📅 Tarih</span>
                    <span class="detail-value"><?= date('d.m.Y', strtotime($selected_ticket['departure_time'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">🕐 Kalkış</span>
                    <span class="detail-value"><?= date('H:i', strtotime($selected_ticket['departure_time'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">🕐 Varış</span>
                    <span class="detail-value"><?= date('H:i', strtotime($selected_ticket['arrival_time'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">💺 Koltuk</span>
                    <span class="detail-value"><?= e($selected_ticket['seat_numbers'] ?: 'Belirtilmemiş') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">💵 İade Tutarı</span>
                    <span class="detail-value refund-amount">
                        <?= number_format($selected_ticket['total_price'], 2) ?> TL
                    </span>
                </div>
            </div>

            <?php if ($can_cancel): ?>
                <div class="warning-box safe">
                    <strong>✅ İptal Edilebilir</strong>
                    Kalkışa <?= $hours_left ?> saat <?= $minutes_left ?> dakika var.
                    <small><?= number_format($selected_ticket['total_price'], 2) ?> TL hesabınıza iade edilecektir.</small>
                </div>

                <div class="button-group">
                    <form method="POST">
                        <button type="submit" class="btn btn-back">← Geri Dön</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="ticket_id" value="<?= e($selected_ticket['ticket_id']) ?>">
                        <button type="submit" name="confirm_cancel" class="btn btn-cancel" 
                                onclick="return confirm('Bu bileti iptal etmek istediğinizden EMİN MİSİNİZ?');">
                            🗑️ İptal Et
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <div class="warning-box danger">
                    <strong>⚠️ İptal Edilemez!</strong>
                    Kalkış saatine 1 saatten az kaldı.
                    <small>İptal işlemi yapılamaz.</small>
                </div>

                <form method="POST">
                    <button type="submit" class="btn btn-back" style="width: 100%;">← Geri Dön</button>
                </form>
            <?php endif; ?>

        </div>
    <?php endif; ?>

    <div class="back-link">
        <a href="/index.php">← Ana Sayfa</a>
    </div>

</div>

</body>
</html>