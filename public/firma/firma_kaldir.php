<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /login.php");
    exit;
}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];


$messages = ['success' => null, 'error' => null];


function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_company'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $messages['error'] = "Geçersiz istek (CSRF doğrulaması başarısız).";
    } else {
        $company_id = trim($_POST['company_id'] ?? '');
        if ($company_id === '') {
            $messages['error'] = "Lütfen bir firma seçin.";
        } else {
            try {
               
                $pdo->beginTransaction();

                
                $stmt = $pdo->prepare("DELETE FROM User WHERE role = 'firma_admin' AND company_id = :cid");
                $stmt->execute([':cid' => $company_id]);

              
                $stmt = $pdo->prepare("SELECT id FROM Trips WHERE company_id = :cid");
                $stmt->execute([':cid' => $company_id]);
                $trip_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

         
                if (!empty($trip_ids)) {
                  
                    $placeholders = implode(',', array_fill(0, count($trip_ids), '?'));

                   
                    $delTickets = $pdo->prepare("DELETE FROM Tickets WHERE trip_id IN ($placeholders)");
                    $delTickets->execute($trip_ids);

                   
                    $delTrips = $pdo->prepare("DELETE FROM Trips WHERE id IN ($placeholders)");
                    $delTrips->execute($trip_ids);
                }

                
                $stmt = $pdo->prepare("DELETE FROM Coupons WHERE company_id = :cid");
                $stmt->execute([':cid' => $company_id]);

                $stmt = $pdo->prepare("DELETE FROM Bus_Company WHERE id = :cid");
                $stmt->execute([':cid' => $company_id]);

             
                $pdo->commit();
                $messages['success'] = "Firma ve ilişkili tüm kayıtlar başarıyla silindi!";
            } catch (Exception $ex) {
              
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("firma_kaldir error: " . $ex->getMessage());
                $messages['error'] = "Firma silinirken hata oluştu. Lütfen tekrar deneyin.";
            }
        }
    }
}


try {
    $stmt = $pdo->query("SELECT id, name FROM Bus_Company ORDER BY name ASC");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log("Firma listesi çekilemedi: " . $ex->getMessage());
    $companies = [];
    $messages['error'] = $messages['error'] ?? "Firma listesi yüklenirken hata oluştu.";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Firma Kaldır - VatanBilet</title>
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
    position:relative;
    overflow:hidden;
}

body::before{
    content:'';
    position:absolute;
    width:500px;
    height:500px;
    background:radial-gradient(circle,rgba(239,68,68,0.08) 0%,transparent 70%);
    border-radius:50%;
    top:-250px;
    right:-250px;
    z-index:0;
    animation:pulse 4s ease-in-out infinite;
}

body::after{
    content:'';
    position:absolute;
    width:400px;
    height:400px;
    background:radial-gradient(circle,rgba(239,68,68,0.06) 0%,transparent 70%);
    border-radius:50%;
    bottom:-200px;
    left:-200px;
    z-index:0;
    animation:pulse 5s ease-in-out infinite;
}

@keyframes pulse{
    0%,100%{transform:scale(1);opacity:1;}
    50%{transform:scale(1.1);opacity:0.8;}
}

/* Container */
.container{
    max-width:600px;
    width:100%;
    position:relative;
    z-index:1;
}

/* Card */
.card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:20px;
    box-shadow:0 4px 20px rgba(0,0,0,0.5),0 0 40px rgba(239,68,68,0.1);
    border:1px solid rgba(239,68,68,0.3);
    overflow:hidden;
}

/* Header - Warning Style */
.card-header{
    background:linear-gradient(135deg,#dc2626,#b91c1c);
    padding:32px 32px 28px;
    text-align:center;
    position:relative;
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
    background:rgba(255,255,255,0.2);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:36px;
    margin:0 auto 16px;
    backdrop-filter:blur(10px);
    box-shadow:0 0 30px rgba(0,0,0,0.3);
    position:relative;
}
.card-header h2{
    color:#fff;
    font-size:28px;
    font-weight:700;
    margin-bottom:8px;
    text-shadow:0 2px 8px rgba(0,0,0,0.3);
    position:relative;
}
.card-header p{
    color:rgba(255,255,255,0.9);
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

/* Warning Box */
.warning-box{
    background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(220,38,38,0.1));
    border:2px solid rgba(239,68,68,0.4);
    border-radius:12px;
    padding:16px;
    margin-bottom:24px;
    backdrop-filter:blur(10px);
}
.warning-box-title{
    display:flex;
    align-items:center;
    gap:10px;
    font-size:15px;
    font-weight:700;
    color:#fca5a5;
    margin-bottom:12px;
    text-shadow:0 0 10px rgba(239,68,68,0.3);
}
.warning-box-title::before{
    content:'⚠️';
    font-size:24px;
}
.warning-list{
    list-style:none;
    padding:0;
    margin:0;
}
.warning-list li{
    font-size:14px;
    color:#fca5a5;
    line-height:1.8;
    padding-left:24px;
    position:relative;
}
.warning-list li::before{
    content:'×';
    position:absolute;
    left:8px;
    font-weight:700;
    font-size:18px;
    color:#ef4444;
}

/* Form */
.form-group{
    margin-bottom:24px;
}
.form-label{
    display:block;
    margin-bottom:8px;
    color:#fca5a5;
    font-weight:600;
    font-size:14px;
    text-shadow:0 0 10px rgba(239,68,68,0.3);
}
.form-select{
    width:100%;
    padding:12px 16px;
    border-radius:10px;
    border:1px solid rgba(239,68,68,0.3);
    background:rgba(26,26,26,0.6);
    color:#ffffff;
    font-size:15px;
    font-family:'Inter',sans-serif;
    transition:all 0.3s;
}
.form-select:focus{
    outline:none;
    border-color:rgba(239,68,68,0.6);
    box-shadow:0 0 0 3px rgba(239,68,68,0.1),0 0 20px rgba(239,68,68,0.2);
    background:rgba(26,26,26,0.8);
}
.form-select option{
    background:#1a1a1a;
    color:#ffffff;
}

/* Buttons */
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
    gap:8px;
}
.btn-danger{
    background:linear-gradient(135deg,#dc2626,#b91c1c);
    color:#fff;
    box-shadow:0 0 20px rgba(220,38,38,0.3);
}
.btn-danger:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 16px rgba(220,38,38,0.4),0 0 30px rgba(220,38,38,0.3);
}
.btn-danger:active{
    transform:translateY(0);
}
.btn-danger::before{
    content:'🗑️';
    font-size:18px;
}
.btn-secondary{
    background:rgba(26,26,26,0.8);
    color:rgba(255,255,255,0.7);
    border:1px solid rgba(239,68,68,0.3);
}
.btn-secondary:hover{
    background:rgba(239,68,68,0.1);
    color:#fca5a5;
    border-color:rgba(239,68,68,0.5);
}

/* Info Note */
.info-note{
    background:rgba(26,26,26,0.6);
    border:1px solid rgba(239,68,68,0.2);
    border-radius:10px;
    padding:14px 16px;
    margin-top:20px;
    font-size:13px;
    color:rgba(255,255,255,0.6);
    line-height:1.6;
}
.info-note strong{
    color:#fca5a5;
    text-shadow:0 0 10px rgba(239,68,68,0.3);
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
    .btn-group{
        flex-direction:column-reverse;
    }
}
</style>
</head>
<body>

<div class="container">
    <a href="/admin/admin_panel.php" class="back-link">Admin Paneline Dön</a>
    <div class="card">
        <div class="card-header">
            <div class="card-header-icon">🗑️</div>
            <h2>Firma Kaldır</h2>
            <p>Dikkatli olun! Bu işlem geri alınamaz</p>
        </div>

        <div class="card-body">
            <?php if($messages['success']): ?>
                <div class="alert alert-success"><?= e($messages['success']) ?></div>
            <?php endif; ?>
            <?php if($messages['error']): ?>
                <div class="alert alert-error"><?= e($messages['error']) ?></div>
            <?php endif; ?>

            <!-- Warning Box -->
            <div class="warning-box">
                <div class="warning-box-title">Bu İşlem Aşağıdakileri Silecek</div>
                <ul class="warning-list">
                    <li>Firmaya ait tüm yönetici hesapları</li>
                    <li>Firmaya ait tüm seferler</li>
                    <li>Seferlere ait tüm biletler</li>
                    <li>Firmaya ait tüm kuponlar</li>
                    <li>Firma kaydının kendisi</li>
                </ul>
            </div>

            <form method="POST" id="deleteForm" onsubmit="return confirmDelete();">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <div class="form-group">
                    <label class="form-label" for="company_id">Silinecek Firmayı Seçin *</label>
                    <select name="company_id" id="company_id" class="form-select" required>
                        <option value="">-- Firma Seçiniz --</option>
                        <?php foreach($companies as $c): ?>
                            <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="btn-group">
                    <a href="/admin/admin_panel.php" class="btn btn-secondary">İptal</a>
                    <button type="submit" name="delete_company" class="btn btn-danger">Firmayı Kalıcı Olarak Sil</button>
                </div>
            </form>

            <div class="info-note">
                <strong>⚠️ Önemli:</strong> Silme işlemi gerçekleştikten sonra veriler geri getirilemez. 
                İşleme devam etmeden önce lütfen seçiminizi dikkatlice kontrol edin.
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(){
    const sel = document.getElementById('company_id');
    if(!sel.value){
        alert('Lütfen bir firma seçin.');
        return false;
    }
    
    const selectedName = sel.options[sel.selectedIndex].text;
    
    // İki aşamalı onay
    const firstConfirm = confirm(
        '⚠️ DİKKAT!\n\n' +
        '"' + selectedName + '" firmasını ve tüm ilişkili kayıtları (yöneticiler, seferler, biletler, kuponlar) ' +
        'kalıcı olarak silmek istediğinize emin misiniz?\n\n' +
        'Bu işlem GERİ ALINAMAZ!'
    );
    
    if(!firstConfirm) return false;
    
    // İkinci onay
    const secondConfirm = confirm(
        'Son onay:\n\n' +
        'Firma: ' + selectedName + '\n\n' +
        'Tüm verilerin kalıcı olarak silineceğini anlıyorum ve onaylıyorum.'
    );
    
    return secondConfirm;
}
</script>

</body>
</html>