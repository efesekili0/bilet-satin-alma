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


$stmt = $pdo->prepare("SELECT company_id FROM User WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company_id = $stmt->fetchColumn();
if (!$company_id) {
    $_SESSION['error'] = "Firma bilgisi bulunamadı.";
    header("Location: /login.php");
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trip'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = "Geçersiz istek (CSRF).";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $trip_id = $_POST['trip_id'] ?? '';
    if (!$trip_id) {
        $_SESSION['error'] = "Sefer ID eksik.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    
    $stmt = $pdo->prepare("SELECT departure_city, destination_city, departure_time 
                           FROM Trips 
                           WHERE id = ? AND company_id = ?");
    $stmt->execute([$trip_id, $company_id]);
    $trip_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$trip_info) {
        $_SESSION['error'] = "Sefer bulunamadı veya yetkiniz yok.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    
    $stmt = $pdo->prepare("SELECT t.id, t.user_id, t.total_price, t.status, u.email, u.fullname 
                           FROM Tickets t
                           JOIN User u ON t.user_id = u.id
                           WHERE t.trip_id = ?");
    $stmt->execute([$trip_id]);
    $all_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    
    $active_tickets = array_filter($all_tickets, function($t) {
        return $t['status'] === 'active';
    });

    try {
        
        $pdo->beginTransaction();

        $refund_count = 0;
        $total_refund = 0;

        
        foreach($all_tickets as $ticket) {
            
            $stmt = $pdo->prepare("DELETE FROM Booked_Seats WHERE ticket_id = ?");
            $stmt->execute([$ticket['id']]);

            
            $stmt = $pdo->prepare("DELETE FROM Tickets WHERE id = ?");
            $stmt->execute([$ticket['id']]);
        }

        
        if (count($active_tickets) > 0) {
            foreach($active_tickets as $res) {
                
                $stmt = $pdo->prepare("UPDATE User 
                                       SET balance = balance + ? 
                                       WHERE id = ?");
                $stmt->execute([$res['total_price'], $res['user_id']]);

                $refund_count++;
                $total_refund += $res['total_price'];

                
                try {
                    $notification_message = "Sefer iptal edildi: " . 
                                          htmlspecialchars($trip_info['departure_city']) . " → " . 
                                          htmlspecialchars($trip_info['destination_city']) . 
                                          " (" . date('d.m.Y H:i', strtotime($trip_info['departure_time'])) . "). " .
                                          number_format($res['total_price'], 2) . " ₺ bakiyenize iade edildi.";
                    
                    
                    $stmt = $pdo->prepare("INSERT INTO Notifications (id, user_id, message, created_at) 
                                           VALUES (?, ?, ?, datetime('now'))");
                    $stmt->execute([uniqid('notif_'), $res['user_id'], $notification_message]);
                } catch (Exception $e) {
                    
                }
            }
        }

       
        $stmt = $pdo->prepare("DELETE FROM Trips WHERE id = ? AND company_id = ?");
        $stmt->execute([$trip_id, $company_id]);

        $pdo->commit();

        if ($refund_count > 0) {
            $_SESSION['success'] = "Sefer başarıyla iptal edildi. {$refund_count} kullanıcıya toplam " . 
                                   number_format($total_refund, 2) . " ₺ bakiye iadesi yapıldı.";
        } else {
            $_SESSION['success'] = "Sefer başarıyla silindi.";
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Sefer iptal edilirken hata oluştu: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}


$stmt = $pdo->prepare("SELECT id, departure_city, destination_city, departure_time, arrival_time, price, capacity FROM Trips WHERE company_id = ? ORDER BY departure_time ASC");
$stmt->execute([$company_id]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>Firma Seferlerim - VatanBilet</title>
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
    padding: 40px 20px;
    position: relative;
}

body::before {
    content: '';
    position: fixed;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(212,175,55,0.08) 0%, transparent 70%);
    border-radius: 50%;
    top: -300px;
    right: -300px;
    z-index: 0;
    animation: pulse 4s ease-in-out infinite;
}

body::after {
    content: '';
    position: fixed;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(212,175,55,0.06) 0%, transparent 70%);
    border-radius: 50%;
    bottom: -250px;
    left: -250px;
    z-index: 0;
    animation: pulse 5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
    padding: 40px;
    border-radius: 24px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(212,175,55,0.1);
    border: 1px solid rgba(212,175,55,0.2);
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
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 35px;
    padding-bottom: 25px;
    border-bottom: 2px solid rgba(212,175,55,0.2);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.header-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(212,175,55,0.4), 0 0 30px rgba(212,175,55,0.2);
}

.header-icon svg {
    width: 30px;
    height: 30px;
    stroke: #000;
    fill: none;
    stroke-width: 2;
}

h1 {
    background: linear-gradient(135deg, #ffffff 0%, #d4af37 50%, #f4d03f 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-size: 32px;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 0 30px rgba(212,175,55,0.3);
}

.subtitle {
    color: rgba(255,255,255,0.6);
    font-size: 14px;
    margin-top: 5px;
}

.alert {
    padding: 16px 20px;
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

.info-box {
    background: linear-gradient(135deg, rgba(212,175,55,0.1), rgba(212,175,55,0.05));
    border: 1px solid rgba(212,175,55,0.3);
    color: rgba(255,255,255,0.8);
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 25px;
    font-size: 14px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.info-box::before {
    content: 'ℹ️';
    font-size: 18px;
    flex-shrink: 0;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: rgba(212,175,55,0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    border: 1px solid rgba(212,175,55,0.2);
}

.empty-icon svg {
    width: 40px;
    height: 40px;
    stroke: #d4af37;
    fill: none;
    stroke-width: 2;
}

.empty-state h3 {
    color: #f4d03f;
    font-size: 20px;
    margin-bottom: 10px;
    text-shadow: 0 0 10px rgba(212,175,55,0.3);
}

.empty-state p {
    color: rgba(255,255,255,0.6);
    margin-bottom: 25px;
}

.empty-state a {
    display: inline-block;
    padding: 12px 28px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
    color: #000;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(212,175,55,0.4);
}

.empty-state a:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(212,175,55,0.5);
}

.table-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid rgba(212,175,55,0.2);
}

.table {
    width: 100%;
    border-collapse: collapse;
    background: rgba(26,26,26,0.6);
}

.table thead {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
}

.table th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #000;
    white-space: nowrap;
}

.table td {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(212,175,55,0.1);
    color: rgba(255,255,255,0.8);
    font-size: 14px;
}

.table tbody tr {
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background: rgba(212,175,55,0.05);
}

.table tbody tr:last-child td {
    border-bottom: none;
}

.route {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #ffffff;
}

.route-arrow {
    color: #f4d03f;
    font-weight: normal;
}

.datetime {
    white-space: nowrap;
    color: rgba(255,255,255,0.7);
}

.price {
    font-weight: 600;
    color: #34d399;
    white-space: nowrap;
}

.capacity {
    display: inline-block;
    padding: 4px 12px;
    background: rgba(212,175,55,0.15);
    color: #f4d03f;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid rgba(212,175,55,0.3);
}

.actions {
    display: flex;
    gap: 8px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-edit {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: white;
    box-shadow: 0 0 15px rgba(6,182,212,0.3);
}

.btn-edit:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(6,182,212,0.4);
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
.btn-delete {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    box-shadow: 0 0 15px rgba(239,68,68,0.3);
}

.btn-delete:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239,68,68,0.4);
}

@media (max-width: 768px) {
    .container {
        padding: 25px 20px;
    }
    
    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    h1 {
        font-size: 24px;
    }
    
    .table-wrapper {
        border: none;
    }
    
    .table, .table thead, .table tbody, .table th, .table td, .table tr {
        display: block;
    }
    
    .table thead {
        display: none;
    }
    
    .table tr {
        margin-bottom: 20px;
        border: 1px solid rgba(212,175,55,0.2);
        border-radius: 12px;
        padding: 16px;
        background: rgba(26,26,26,0.8);
    }
    
    .table td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border: none;
    }
    
    .table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #f4d03f;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    
    .actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
<script>
function confirmDelete(tripId){
    if(confirm("⚠️ DİKKAT!\n\nBu seferi iptal etmek istediğinize emin misiniz?\n\n• Tüm aktif biletler iptal edilecek\n• Kullanıcılara bakiye iadesi yapılacak\n• Bu işlem geri alınamaz\n\nDevam etmek istiyor musunuz?")){
        const form = document.getElementById('deleteForm');
        form.trip_id.value = tripId;
        form.submit();
    }
}
</script>
</head>
<body>
<div class="container">
    <a href="/admin/admin_panel.php" class="back-link">Ana Sayfa</a>
    <div class="header">
        <div class="header-left">
            <div class="header-icon">
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
            </div>
            <div>
                <h1>Firma Seferlerim</h1>
                <p class="subtitle">Tüm seferlerinizi görüntüleyin ve yönetin</p>
            </div>
        </div>
    </div>

    <?php if ($success): ?><div class="alert success"><?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <div class="info-box">
        Seferleri iptal ettiğinizde, tüm aktif biletler otomatik olarak iptal edilir ve kullanıcılara bakiye iadesi yapılır.
    </div>

    <?php if (count($trips) === 0): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
            </div>
            <h3>Henüz Sefer Yok</h3>
            <p>Firmanız için henüz eklenmiş bir sefer bulunmuyor.</p>
            <a href="/sefer/sefer_ekle.php">Yeni Sefer Ekle</a>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Güzergah</th>
                        <th>Kalkış Zamanı</th>
                        <th>Varış Zamanı</th>
                        <th>Fiyat</th>
                        <th>Kapasite</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($trips as $t): ?>
                    <tr>
                        <td data-label="Güzergah">
                            <span class="route">
                                <?=htmlspecialchars($t['departure_city'])?> 
                                <span class="route-arrow">→</span> 
                                <?=htmlspecialchars($t['destination_city'])?>
                            </span>
                        </td>
                        <td data-label="Kalkış" class="datetime"><?=date('d.m.Y H:i', strtotime($t['departure_time']))?></td>
                        <td data-label="Varış" class="datetime"><?=date('d.m.Y H:i', strtotime($t['arrival_time']))?></td>
                        <td data-label="Fiyat"><span class="price"><?=number_format($t['price'],2)?> ₺</span></td>
                        <td data-label="Kapasite"><span class="capacity"><?=intval($t['capacity'])?></span></td>
                        <td data-label="İşlemler">
                            <div class="actions">
                                <a class="btn btn-edit" href="/sefer/sefer_duzenle.php?id=<?=urlencode($t['id'])?>">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    Düzenle
                                </a>
                                <button type="button" class="btn btn-delete" onclick="confirmDelete('<?=htmlspecialchars($t['id'], ENT_QUOTES)?>')">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                    İptal Et
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- gizli POST formu -->
    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf)?>">
        <input type="hidden" name="trip_id" value="">
        <input type="hidden" name="delete_trip" value="1">
    </form>
</div>
</body>
</html>