<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: /login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];

$user_id = $_SESSION['user_id'];
$messages = [];


$stmt = $pdo->prepare("SELECT fullname, email, password FROM User WHERE id = ? AND role = 'admin'");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user){
    die("Admin kullanıcı bulunamadı!");
}


if(isset($_POST['update_admin'])){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) die("CSRF Hatası");

    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];

    if(!empty($new_password)){
        if(password_verify($old_password, $user['password'])){
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        } else {
            $messages[] = ['type'=>'error','text'=>'Eski şifre yanlış!'];
        }
    } else {
        $hashed_password = $user['password'];
    }

    if(empty($messages)){
        $stmt = $pdo->prepare("UPDATE User SET fullname=:fullname, email=:email, password=:password WHERE id=:id");
        $stmt->execute([
            ':fullname'=>$fullname,
            ':email'=>$email,
            ':password'=>$hashed_password,
            ':id'=>$user_id
        ]);
        $messages[] = ['type'=>'success','text'=>'Hesap bilgileri başarıyla güncellendi!'];

        $_SESSION['fullname'] = $fullname;
        $user['fullname'] = $fullname;
        $user['email'] = $email;
        $user['password'] = $hashed_password;

        if(!empty($new_password)){
            session_unset();
            session_destroy();
            header("Location: /login.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hesap Ayarları - VatanBilet Admin</title>
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
    max-width:600px;
    width:100%;
    position:relative;
    z-index:1;
}

/* Back Link */
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
.back-link:hover{
    background:rgba(212,175,55,0.1);
    color:#f4d03f;
    border-color:rgba(212,175,55,0.4);
    transform:translateX(-4px);
}
.back-link::before{
    content:'←';
    font-size:16px;
}

/* Card */
.card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:20px;
    box-shadow:0 8px 32px rgba(0,0,0,0.6),0 0 60px rgba(212,175,55,0.15);
    border:1px solid rgba(212,175,55,0.2);
    overflow:hidden;
}

/* Header */
.card-header{
    background:linear-gradient(135deg,rgba(212,175,55,0.2) 0%,rgba(244,208,63,0.1) 100%);
    padding:40px 32px 32px;
    text-align:center;
    position:relative;
    overflow:hidden;
    border-bottom:1px solid rgba(212,175,55,0.2);
}
.card-header::before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:0;
    bottom:0;
    background:radial-gradient(circle at 50% 0%,rgba(212,175,55,0.1),transparent 70%);
    z-index:0;
}
.card-header-icon{
    width:80px;
    height:80px;
    border-radius:50%;
    background:linear-gradient(135deg,rgba(212,175,55,0.3),rgba(244,208,63,0.2));
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:40px;
    margin:0 auto 20px;
    backdrop-filter:blur(10px);
    border:2px solid rgba(212,175,55,0.3);
    box-shadow:0 0 30px rgba(212,175,55,0.2);
    position:relative;
    z-index:1;
    filter:drop-shadow(0 0 10px rgba(212,175,55,0.5));
}
.card-header h2{
    font-size:28px;
    background:linear-gradient(135deg,#ffffff 0%,#d4af37 50%,#f4d03f 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    font-weight:700;
    margin-bottom:8px;
    position:relative;
    z-index:1;
    text-shadow:0 0 30px rgba(212,175,55,0.3);
}
.card-header p{
    color:rgba(255,255,255,0.7);
    font-size:15px;
    position:relative;
    z-index:1;
}

/* Body */
.card-body{
    padding:32px;
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
.alert::before{
    font-size:20px;
}
.alert-success::before{content:'✓';}
.alert-error::before{content:'⚠';}

/* Info Box */
.info-box{
    background:rgba(26,26,26,0.6);
    border:1px solid rgba(212,175,55,0.2);
    border-radius:10px;
    padding:16px;
    margin-bottom:24px;
    backdrop-filter:blur(10px);
}
.info-box-title{
    display:flex;
    align-items:center;
    gap:10px;
    font-size:14px;
    font-weight:700;
    color:#f4d03f;
    margin-bottom:8px;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
.info-box-title::before{
    content:'ℹ️';
    font-size:18px;
    filter:drop-shadow(0 0 5px rgba(212,175,55,0.5));
}
.info-box-text{
    font-size:13px;
    color:rgba(255,255,255,0.6);
    line-height:1.6;
}

/* Form */
.form-section{
    margin-bottom:28px;
}
.form-section-title{
    font-size:16px;
    font-weight:700;
    color:#f4d03f;
    margin-bottom:16px;
    padding-bottom:12px;
    border-bottom:2px solid rgba(212,175,55,0.2);
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
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

/* Divider */
.divider{
    height:1px;
    background:linear-gradient(90deg,transparent,rgba(212,175,55,0.3),transparent);
    margin:24px 0;
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

/* Security Warning */
.security-warning{
    background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(220,38,38,0.1));
    border:1px solid rgba(239,68,68,0.3);
    border-radius:10px;
    padding:14px 16px;
    margin-top:20px;
    font-size:13px;
    color:#fca5a5;
    line-height:1.6;
    backdrop-filter:blur(10px);
}
.security-warning strong{
    display:block;
    margin-bottom:6px;
    color:#fca5a5;
}

/* Stats Row */
.stats-row{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:16px;
    margin-bottom:24px;
}
.stat-item{
    background:rgba(26,26,26,0.6);
    border:1px solid rgba(212,175,55,0.2);
    border-radius:10px;
    padding:16px;
    text-align:center;
    backdrop-filter:blur(10px);
}
.stat-label{
    font-size:12px;
    color:rgba(255,255,255,0.5);
    margin-bottom:6px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}
.stat-value{
    font-size:18px;
    font-weight:700;
    background:linear-gradient(135deg,#d4af37,#f4d03f);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
}

/* Responsive */
@media(max-width:640px){
    body{padding:20px 16px;}
    .card-header{padding:32px 24px 24px;}
    .card-header h2{font-size:24px;}
    .card-body{padding:24px 20px;}
    .btn-group{flex-direction:column;}
    .stats-row{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<div class="container">
    <!-- Back Link -->
    <a href="/admin/admin_panel.php" class="back-link">Admin Paneline Dön</a>

    <div class="card">
        <div class="card-header">
            <div class="card-header-icon">⚙️</div>
            <h2>Hesap Ayarları</h2>
            <p>Profil bilgilerinizi ve şifrenizi güncelleyin</p>
        </div>

        <div class="card-body">
            <?php foreach($messages as $msg): ?>
                <div class="alert alert-<?= $msg['type']=='error'?'error':'success' ?>">
                    <?= htmlspecialchars($msg['text']) ?>
                </div>
            <?php endforeach; ?>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-label">Hesap Rolü</div>
                    <div class="stat-value">Admin</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Kullanıcı ID</div>
                    <div class="stat-value"><?= htmlspecialchars(substr($user_id, 0, 8)) ?>...</div>
                </div>
            </div>

            <div class="info-box">
                <div class="info-box-title">Güvenlik Bilgisi</div>
                <div class="info-box-text">
                    Şifre değiştirirseniz otomatik olarak oturumunuz kapatılacak ve yeniden giriş yapmanız gerekecektir.
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <!-- Profil Bilgileri -->
                <div class="form-section">
                    <h3 class="form-section-title">Profil Bilgileri</h3>
                    
                    <div class="form-group">
                        <label class="form-label" for="fullname">Ad Soyad *</label>
                        <input type="text" id="fullname" name="fullname" class="form-input" 
                               value="<?= htmlspecialchars($user['fullname']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">E-posta Adresi *</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                        <small class="form-help">Giriş için kullanılan e-posta adresi</small>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- Şifre Değiştirme -->
                <div class="form-section">
                    <h3 class="form-section-title">Şifre Değiştir (Opsiyonel)</h3>
                    
                    <div class="form-group">
                        <label class="form-label" for="old_password">Mevcut Şifre</label>
                        <input type="password" id="old_password" name="old_password" class="form-input" 
                               placeholder="Sadece şifre değiştirmek istiyorsanız girin">
                        <small class="form-help">Şifrenizi değiştirmek için mevcut şifrenizi girin</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">Yeni Şifre</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" 
                               placeholder="En az 8 karakter">
                        <small class="form-help">Güçlü bir şifre seçin (minimum 8 karakter)</small>
                    </div>
                </div>

                <div class="btn-group">
                    <a href="admin_panel.php" class="btn btn-secondary">İptal</a>
                    <button type="submit" name="update_admin" class="btn btn-primary">
                        💾 Değişiklikleri Kaydet
                    </button>
                </div>

                <div class="security-warning">
                    <strong>⚠️ Önemli Güvenlik Uyarısı:</strong>
                    Şifrenizi değiştirdikten sonra oturumunuz kapatılacak ve yeni şifrenizle tekrar giriş yapmanız gerekecektir.
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>