<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';


if(!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin'){
    header("Location: /login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];


function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$messages = ['success'=>null, 'error'=>null];


if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_company'])){
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        $messages['error'] = "Geçersiz istek (CSRF).";
    } else {
        $name = trim($_POST['name'] ?? '');
        if($name===''){
            $messages['error'] = "Firma adı boş olamaz.";
        } else {
            $id = 'company_' . bin2hex(random_bytes(8));
            $logo_path = '';

            if(isset($_FILES['logo_file']) && $_FILES['logo_file']['error']===0){
                $allowed_types = ['image/png','image/jpeg','image/jpg','image/gif','image/webp'];
                if(!in_array($_FILES['logo_file']['type'], $allowed_types)){
                    $messages['error'] = "Sadece resim dosyaları yükleyebilirsiniz (png/jpg/gif/webp).";
                } else {
                    $uploads_dir = __DIR__ . '/uploads';
                    if(!is_dir($uploads_dir)) mkdir($uploads_dir,0755,true);

                    $tmp_name = $_FILES['logo_file']['tmp_name'];
                    $filename = bin2hex(random_bytes(6)) . "_" . basename($_FILES['logo_file']['name']);
                    $destination = "$uploads_dir/$filename";

                    if(move_uploaded_file($tmp_name, $destination)){
                        $logo_path = "uploads/$filename";
                    } else {
                        $messages['error'] = "Logo yüklenemedi.";
                    }
                }
            }

            if(!$messages['error']){
                try {
                    $stmt = $pdo->prepare("INSERT INTO Bus_Company (id,name,logo_path,created_at) 
                                           VALUES (:id,:name,:logo_path,datetime('now'))");
                    $stmt->execute([
                        ':id'=>$id,
                        ':name'=>$name,
                        ':logo_path'=>$logo_path
                    ]);
                    $messages['success'] = "Firma başarıyla eklendi!";
                } catch(Exception $ex){
                    error_log("Firma ekleme hatası: ".$ex->getMessage());
                    $messages['error'] = "Firma eklenirken hata oluştu.";
                }
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
<title>Firma Ekle - VatanBilet</title>
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
    background:radial-gradient(circle,rgba(212,175,55,0.08) 0%,transparent 70%);
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
    background:radial-gradient(circle,rgba(212,175,55,0.06) 0%,transparent 70%);
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
    box-shadow:0 4px 20px rgba(0,0,0,0.5),0 0 40px rgba(212,175,55,0.1);
    border:1px solid rgba(212,175,55,0.2);
    overflow:hidden;
}

/* Header */
.card-header{
    background:linear-gradient(135deg,#d4af37,#f4d03f);
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
    margin-bottom:24px;
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

/* File input styling */
.file-input-wrapper{
    position:relative;
}
.file-input{
    width:100%;
    padding:12px 16px;
    border-radius:10px;
    border:2px dashed rgba(212,175,55,0.4);
    background:rgba(26,26,26,0.4);
    color:rgba(255,255,255,0.6);
    font-size:14px;
    cursor:pointer;
    transition:all 0.3s;
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
.file-input:hover{
    border-color:rgba(212,175,55,0.6);
    background:rgba(212,175,55,0.05);
}
.file-input:focus{
    outline:none;
    border-color:rgba(212,175,55,0.6);
    background:rgba(26,26,26,0.6);
    box-shadow:0 0 20px rgba(212,175,55,0.2);
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
            <div class="card-header-icon">🏢</div>
            <h2>Yeni Firma Ekle</h2>
            <p>Sisteme yeni bir otobüs firması ekleyin</p>
        </div>

        <div class="card-body">
            <?php if($messages['success']): ?>
                <div class="alert alert-success"><?= e($messages['success']) ?></div>
            <?php endif; ?>
            <?php if($messages['error']): ?>
                <div class="alert alert-error"><?= e($messages['error']) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                <div class="form-group">
                    <label class="form-label" for="name">Firma Adı *</label>
                    <input type="text" id="name" name="name" class="form-input" 
                           placeholder="Örn: Vatan Turizm" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="logo_file">Firma Logosu</label>
                    <div class="file-input-wrapper">
                        <input type="file" id="logo_file" name="logo_file" class="file-input" accept="image/*">
                    </div>
                    <small class="form-help">📎 PNG, JPG, GIF veya WebP formatında yükleyebilirsiniz</small>
                </div>

                <div class="btn-group">
                    <a href="/admin/admin_panel.php" class="btn btn-secondary">İptal</a>
                    <button type="submit" name="create_company" class="btn btn-primary">🏢 Firma Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>