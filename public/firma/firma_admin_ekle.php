<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';


if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit;
}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];


function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$messages = ['success' => null, 'error' => null];


try {
    $stmt = $pdo->query("SELECT id, name FROM Bus_Company ORDER BY name ASC");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log("Firma list fetch error: " . $ex->getMessage());
    $companies = [];
    $messages['error'] = "Firma listesi alınırken hata oluştu.";
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_firma_admin'])) {

   
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $messages['error'] = "Geçersiz istek (CSRF).";
    } else {
       
        $company_id = trim($_POST['company_id'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        
        if ($company_id === '') {
            $messages['error'] = "Lütfen bir firma seçin.";
        } elseif ($fullname === '' || mb_strlen($fullname) < 2) {
            $messages['error'] = "Ad soyad en az 2 karakter olmalı.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $messages['error'] = "Geçerli bir e-posta girin.";
        } elseif (!is_string($password) || strlen($password) < 8) {
            $messages['error'] = "Şifre en az 8 karakter olmalıdır.";
        } else {
      
            $email_norm = mb_strtolower($email);

            try {
            
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Bus_Company WHERE id = ?");
                $stmt->execute([$company_id]);
                if ($stmt->fetchColumn() == 0) {
                    $messages['error'] = "Seçilen firma bulunamadı.";
                } else {
  
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM User WHERE LOWER(email) = LOWER(?)");
                    $stmt->execute([$email_norm]);
                    if ($stmt->fetchColumn() > 0) {
                        $messages['error'] = "Bu e-posta zaten kayıtlı.";
                    } else {
                
                        $pdo->beginTransaction();
                        try {
                            $id = 'usr_' . bin2hex(random_bytes(8));
                            $hashed = password_hash($password, PASSWORD_BCRYPT);

                            $ins = $pdo->prepare("INSERT INTO User
                                (id, fullname, email, password, role, company_id, created_at)
                                VALUES (:id, :fullname, :email, :password, 'firma_admin', :company_id, datetime('now'))");

                            $ins->execute([
                                ':id' => $id,
                                ':fullname' => $fullname,
                                ':email' => $email_norm,
                                ':password' => $hashed,
                                ':company_id' => $company_id
                            ]);

                            $pdo->commit();
                            $messages['success'] = "Firma admini başarıyla oluşturuldu.";
                            $fullname = $email = '';
                        } catch (Exception $ex) {
                            if ($pdo->inTransaction()) $pdo->rollBack();
                            error_log("Firma admin insert error: " . $ex->getMessage());
                            $messages['error'] = "Kayıt sırasında hata oluştu. Lütfen tekrar deneyin.";
                        }
                    }
                }
            } catch (Exception $ex) {
                error_log("Firma admin işlem hatası: " . $ex->getMessage());
                $messages['error'] = "İşlem sırasında hata oluştu.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Firma Admin Ekle - VatanBilet</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Inter',sans-serif;
    background:#0a0a0a;
    min-height:100vh;
    padding:40px 20px;
    position:relative;
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

/* Container */
.container{
    max-width:1200px;
    margin:0 auto;
    position:relative;
    z-index:1;
}

/* Page Header */
.page-header{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:16px;
    padding:32px;
    margin-bottom:24px;
    box-shadow:0 4px 20px rgba(0,0,0,0.4),0 0 40px rgba(212,175,55,0.1);
    border:1px solid rgba(212,175,55,0.2);
    display:flex;
    justify-content:space-between;
    align-items:center;
}
.page-header-content h1{
    font-size:28px;
    background:linear-gradient(135deg,#ffffff 0%,#d4af37 50%,#f4d03f 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    font-weight:700;
    margin-bottom:8px;
    text-shadow:0 0 30px rgba(212,175,55,0.3);
}
.page-header-content p{
    font-size:15px;
    color:rgba(255,255,255,0.6);
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
.page-header-user{
    text-align:right;
}
.page-header-user .user-name{
    font-size:14px;
    color:#f4d03f;
    font-weight:600;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
.page-header-user .user-role{
    font-size:13px;
    color:rgba(255,255,255,0.5);
}

/* Messages */
.alert{
    padding:14px 16px;
    border-radius:10px;
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
.alert::before{font-size:20px;}
.alert-success::before{content:'✓';}
.alert-error::before{content:'⚠';}

/* Grid Layout */
.grid{
    display:grid;
    grid-template-columns:1fr 400px;
    gap:24px;
}

/* Card */
.card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:16px;
    padding:28px;
    box-shadow:0 4px 20px rgba(0,0,0,0.4);
    border:1px solid rgba(212,175,55,0.2);
}
.card-title{
    font-size:18px;
    font-weight:700;
    color:#f4d03f;
    margin-bottom:20px;
    padding-bottom:16px;
    border-bottom:2px solid rgba(212,175,55,0.2);
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}

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
.form-input,.form-select{
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
.form-input:focus,.form-select:focus{
    outline:none;
    border-color:rgba(212,175,55,0.6);
    box-shadow:0 0 0 3px rgba(212,175,55,0.1),0 0 20px rgba(212,175,55,0.2);
    background:rgba(26,26,26,0.8);
}
.form-input::placeholder{
    color:rgba(255,255,255,0.3);
}
.form-select option{
    background:#1a1a1a;
    color:#ffffff;
}
.form-help{
    display:block;
    margin-top:6px;
    color:rgba(255,255,255,0.5);
    font-size:13px;
}

/* Buttons */
.btn-group{
    display:flex;
    gap:12px;
    margin-top:24px;
}
.btn{
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

/* Company List */
.company-list{
    display:flex;
    flex-direction:column;
    gap:10px;
    max-height:400px;
    overflow-y:auto;
    padding-right:4px;
}
.company-item{
    padding:14px 16px;
    border-radius:10px;
    background:rgba(26,26,26,0.6);
    border:1px solid rgba(212,175,55,0.2);
    display:flex;
    justify-content:space-between;
    align-items:center;
    transition:0.2s;
}
.company-item:hover{
    background:rgba(212,175,55,0.05);
    border-color:rgba(212,175,55,0.4);
}
.company-name{
    font-size:14px;
    font-weight:600;
    color:#ffffff;
    margin-bottom:4px;
}
.company-id{
    font-size:12px;
    color:rgba(255,255,255,0.5);
}
.company-badge{
    background:linear-gradient(135deg,rgba(16,185,129,0.2),rgba(5,150,105,0.15));
    color:#34d399;
    padding:4px 10px;
    border-radius:6px;
    font-size:12px;
    font-weight:600;
    border:1px solid rgba(16,185,129,0.3);
}

/* Info Box */
.info-box{
    background:rgba(26,26,26,0.6);
    border:1px solid rgba(212,175,55,0.2);
    border-radius:10px;
    padding:16px;
    margin-top:20px;
}
.info-box-title{
    font-size:14px;
    font-weight:700;
    color:#f4d03f;
    margin-bottom:12px;
    display:flex;
    align-items:center;
    gap:8px;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
.info-box-title::before{
    content:'🔒';
    font-size:18px;
}
.info-list{
    list-style:none;
    padding:0;
    margin:0;
}
.info-list li{
    font-size:13px;
    color:rgba(255,255,255,0.6);
    line-height:1.8;
    padding-left:20px;
    position:relative;
}
.info-list li::before{
    content:'•';
    position:absolute;
    left:8px;
    color:#f4d03f;
    font-weight:700;
}

/* Responsive */
@media(max-width:1024px){
    .grid{
        grid-template-columns:1fr;
    }
    .page-header{
        flex-direction:column;
        align-items:flex-start;
        gap:16px;
    }
    .page-header-user{
        text-align:left;
    }
}

@media(max-width:640px){
    body{padding:20px 16px;}
    .page-header{padding:24px 20px;}
    .card{padding:20px;}
    .page-header-content h1{font-size:24px;}
}

/* Scrollbar */
.company-list::-webkit-scrollbar{width:6px;}
.company-list::-webkit-scrollbar-track{background:rgba(26,26,26,0.6);border-radius:10px;}
.company-list::-webkit-scrollbar-thumb{background:rgba(212,175,55,0.3);border-radius:10px;}
.company-list::-webkit-scrollbar-thumb:hover{background:rgba(212,175,55,0.5);}
</style>
</head>
<body>

<div class="container">
    <!-- Page Header -->
     <a href="/admin/admin_panel.php" class="back-link">Admin Paneline Dön</a>
    <div class="page-header">
        <div class="page-header-content">
            <h1>Firma Admin Ekle</h1>
            
            <p>Seçili firmaya yeni bir yönetici hesabı oluşturun</p>
        </div>
        <div class="page-header-user">
            <div class="user-name"><?= e($_SESSION['fullname'] ?? 'Admin') ?></div>
            <div class="user-role"><?= e($_SESSION['role'] ?? 'Yönetici') ?></div>
        </div>
    </div>

    <!-- Messages -->
    <?php if($messages['success']): ?>
        <div class="alert alert-success"><?= e($messages['success']) ?></div>
    <?php endif; ?>
    <?php if($messages['error']): ?>
        <div class="alert alert-error"><?= e($messages['error']) ?></div>
    <?php endif; ?>

    <!-- Grid -->
    <div class="grid">
        <!-- Form Card -->
        <div class="card">
            <h2 class="card-title">Yönetici Bilgileri</h2>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                
                <div class="form-group">
                    <label class="form-label" for="company_id">Firma Seçin *</label>
                    <select name="company_id" id="company_id" class="form-select" required>
                        <option value="">-- Firma Seçiniz --</option>
                        <?php foreach($companies as $comp): ?>
                            <option value="<?= e($comp['id']) ?>"><?= e($comp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-help">Yöneticiyi hangi firmaya atamak istiyorsunuz?</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="fullname">Ad Soyad *</label>
                    <input type="text" id="fullname" name="fullname" class="form-input" 
                           placeholder="Örn: Ahmet Yılmaz" required value="<?= e($fullname ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">E-posta Adresi *</label>
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="email@ornek.com" required value="<?= e($email ?? '') ?>">
                    <small class="form-help">Giriş için kullanılacak e-posta adresi</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Şifre *</label>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="En az 8 karakter" required>
                    <small class="form-help">Güçlü bir şifre belirleyin (minimum 8 karakter)</small>
                </div>

                <div class="btn-group">
                    <button type="submit" name="create_firma_admin" class="btn btn-primary">
                        👤 Yönetici Oluştur
                    </button>
                    <a href="/admin/admin_panel.php" class="btn btn-secondary">İptal</a>
                </div>
            </form>
        </div>

        <!-- Sidebar -->
        <aside>
            <!-- Company List Card -->
            <div class="card">
                <h2 class="card-title">Mevcut Firmalar (<?= count($companies) ?>)</h2>
                
                <?php if(empty($companies)): ?>
                    <p style="color:rgba(255,255,255,0.5);font-size:14px;text-align:center;padding:20px;">
                        Sistemde kayıtlı firma bulunamadı.
                    </p>
                <?php else: ?>
                    <div class="company-list">
                        <?php foreach($companies as $c): ?>
                            <div class="company-item">
                                <div>
                                    <div class="company-name"><?= e($c['name']) ?></div>
                                    <div class="company-id"><?= e($c['id']) ?></div>
                                </div>
                                <span class="company-badge">Aktif</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Security Info Box -->
            <div class="info-box">
                <div class="info-box-title">Güvenlik Notları</div>
                <ul class="info-list">
                    <li>Şifreler bcrypt ile şifrelenir</li>
                    <li>E-posta adresi benzersiz olmalıdır</li>
                    <li>CSRF koruması aktiftir</li>
                    <li>Tüm girişler doğrulanır</li>
                </ul>
            </div>
        </aside>
    </div>
</div>

</body>
</html>