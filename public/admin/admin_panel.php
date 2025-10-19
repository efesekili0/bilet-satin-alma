<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== "admin"){
    header("Location: /login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf = $_SESSION['csrf_token'];
$fullname = $_SESSION['fullname'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel - VatanBilet</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Inter',sans-serif;
    background:#0a0a0a;
    min-height:100vh;
    display:flex;
    flex-direction:column;
}

/* Topbar */
.topbar{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    color:#ffffff;
    padding:20px 40px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 4px 20px rgba(0,0,0,0.5),0 0 40px rgba(212,175,55,0.1);
    border-bottom:1px solid rgba(212,175,55,0.2);
}

.topbar-left{
    display:flex;
    align-items:center;
    gap:24px;
}

.logo{
    font-size:26px;
    font-weight:700;
    background:linear-gradient(135deg,#d4af37 0%,#f4d03f 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    text-shadow:0 0 20px rgba(212,175,55,0.3);
}

.topbar-title{
    font-size:15px;
    color:rgba(255,255,255,0.6);
    font-weight:500;
    padding-left:24px;
    border-left:1px solid rgba(212,175,55,0.2);
}

.user-info{
    display:flex;
    align-items:center;
    gap:16px;
    background:linear-gradient(135deg,rgba(212,175,55,0.1) 0%,rgba(212,175,55,0.05) 100%);
    padding:10px 20px;
    border-radius:12px;
    border:1px solid rgba(212,175,55,0.2);
}

.user-avatar{
    width:42px;
    height:42px;
    border-radius:50%;
    background:linear-gradient(135deg,#d4af37 0%,#f4d03f 100%);
    display:flex;
    align-items:center;
    justify-content:center;
    color:#000000;
    font-weight:700;
    font-size:18px;
    box-shadow:0 0 20px rgba(212,175,55,0.4);
}

.user-name{
    font-size:14px;
    color:#f4d03f;
    font-weight:600;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}

.logout-btn{
    color:rgba(255,255,255,0.7);
    text-decoration:none;
    font-size:14px;
    font-weight:600;
    padding:8px 18px;
    border-radius:8px;
    transition:all 0.3s;
    border:1px solid rgba(212,175,55,0.3);
    margin-left:8px;
}

.logout-btn:hover{
    background:rgba(212,175,55,0.15);
    color:#f4d03f;
    border-color:rgba(212,175,55,0.5);
}

/* Main container */
.main-container{
    max-width:1400px;
    width:100%;
    margin:0 auto;
    padding:50px 40px;
    flex:1;
}

.page-header{
    margin-bottom:50px;
    text-align:center;
}

.page-header h1{
    font-size:42px;
    font-weight:700;
    margin-bottom:12px;
    background:linear-gradient(135deg,#ffffff 0%,#d4af37 50%,#f4d03f 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    text-shadow:0 0 30px rgba(212,175,55,0.3);
}

.page-header p{
    font-size:16px;
    color:rgba(255,255,255,0.6);
    max-width:600px;
    margin:0 auto;
}

/* Card grid */
.card-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:28px;
}

/* Admin card */
.admin-card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:20px;
    padding:36px 28px;
    text-decoration:none;
    color:#ffffff;
    box-shadow:0 4px 20px rgba(0,0,0,0.4);
    border:1px solid rgba(212,175,55,0.2);
    transition:all 0.4s cubic-bezier(0.4,0,0.2,1);
    position:relative;
    overflow:hidden;
}

.admin-card::before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg,#d4af37,#f4d03f,#d4af37);
    transform:scaleX(0);
    transform-origin:left;
    transition:transform 0.4s;
    box-shadow:0 0 15px rgba(212,175,55,0.5);
}

.admin-card::after{
    content:'';
    position:absolute;
    top:0;
    right:0;
    width:150px;
    height:150px;
    background:radial-gradient(circle,rgba(212,175,55,0.05) 0%,transparent 70%);
    pointer-events:none;
}

.admin-card:hover{
    transform:translateY(-6px);
    box-shadow:0 12px 40px rgba(212,175,55,0.25);
    border-color:rgba(212,175,55,0.5);
}

.admin-card:hover::before{
    transform:scaleX(1);
}

.card-icon{
    width:64px;
    height:64px;
    border-radius:16px;
    background:linear-gradient(135deg,rgba(212,175,55,0.2) 0%,rgba(212,175,55,0.1) 100%);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:32px;
    margin-bottom:24px;
    transition:all 0.4s;
    border:1px solid rgba(212,175,55,0.3);
    box-shadow:0 0 20px rgba(212,175,55,0.2);
    position:relative;
    z-index:1;
}

.admin-card:hover .card-icon{
    transform:scale(1.15) rotate(5deg);
    box-shadow:0 0 30px rgba(212,175,55,0.4);
    border-color:rgba(212,175,55,0.5);
}

.card-title{
    font-size:20px;
    font-weight:700;
    color:#f4d03f;
    margin-bottom:12px;
    position:relative;
    z-index:1;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}

.card-description{
    font-size:14px;
    color:rgba(255,255,255,0.7);
    line-height:1.6;
    position:relative;
    z-index:1;
}

/* Card hover glow effect */
.admin-card:hover .card-title{
    color:#d4af37;
}

/* Responsive */
@media(max-width:768px){
    .topbar{
        padding:16px 20px;
        flex-direction:column;
        gap:16px;
        align-items:flex-start;
    }
    .topbar-left{
        flex-direction:column;
        align-items:flex-start;
        gap:12px;
        width:100%;
    }
    .topbar-title{
        padding-left:0;
        border-left:none;
        padding-top:8px;
        border-top:1px solid rgba(212,175,55,0.2);
    }
    .user-info{
        width:100%;
        justify-content:space-between;
    }
    .main-container{
        padding:30px 20px;
    }
    .page-header h1{
        font-size:32px;
    }
    .card-grid{
        grid-template-columns:1fr;
        gap:20px;
    }
}

/* Icon specific colors */
.admin-card:nth-child(1) .card-icon{
    filter:drop-shadow(0 0 15px rgba(212,175,55,0.3));
}
.admin-card:nth-child(2) .card-icon{
    filter:drop-shadow(0 0 15px rgba(212,175,55,0.3));
}
.admin-card:nth-child(3) .card-icon{
    filter:drop-shadow(0 0 15px rgba(212,175,55,0.3));
}
.admin-card:nth-child(4) .card-icon{
    filter:drop-shadow(0 0 15px rgba(212,175,55,0.3));
}
.admin-card:nth-child(5) .card-icon{
    filter:drop-shadow(0 0 15px rgba(212,175,55,0.3));
}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <div class="logo">VatanBilet Admin</div>
        <div class="topbar-title">Yönetim Sistemi</div>
    </div>
    <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr($fullname, 0, 1)) ?></div>
        <div class="user-name"><?= htmlspecialchars($fullname) ?></div>
        <a href="/logout.php" class="logout-btn">Çıkış Yap</a>
    </div>
</div>

<div class="main-container">
    <div class="page-header">
        <h1>Yönetim Paneli</h1>
        <p>Firma ve kupon yönetimi için tüm araçlara buradan erişebilirsiniz</p>
    </div>

    <div class="card-grid">
        <a class="admin-card" href="/firma/firma_ekle.php">
            <div class="card-icon">🏢</div>
            <div class="card-title">Firma Ekle</div>
            <div class="card-description">Sisteme yeni firma ekleyin ve bilgilerini düzenleyin</div>
        </a>

        <a class="admin-card" href="/firma/firma_admin_ekle.php">
            <div class="card-icon">👤</div>
            <div class="card-title">Firma Admin Ekle</div>
            <div class="card-description">Firmalara yeni yönetici hesabı oluşturun</div>
        </a>

        <a class="admin-card" href="/firma/firma_kaldir.php">
            <div class="card-icon">🗑️</div>
            <div class="card-title">Firma Kaldır</div>
            <div class="card-description">Sistemden firma ve ilgili verileri silin</div>
        </a>

        <a class="admin-card" href="/admin/admin_kupon_ekle.php">
            <div class="card-icon">💸</div>
            <div class="card-title">Kupon Ayarları</div>
            <div class="card-description">Kupon oluşturun, düzenleyin veya silin</div>
        </a>

        <a class="admin-card" href="/admin/admin_settings.php">
            <div class="card-icon">⚙️</div>
            <div class="card-title">Hesap Ayarları</div>
            <div class="card-description">Profil ve güvenlik ayarlarınızı yönetin</div>
        </a>
    </div>
</div>

</body>
</html>