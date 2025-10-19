<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';


if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'firma_admin') {
    header("Location: /login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
$fullname = $_SESSION['fullname'] ?? 'Firma Admin';

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Paneli - VatanBilet</title>
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
        .logout-link{
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
        .logout-link:hover{
            background:rgba(212,175,55,0.15);
            color:#f4d03f;
            border-color:rgba(212,175,55,0.5);
        }
        
        /* Main Container */
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
        
        /* Card Grid */
        .card-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
            gap:28px;
        }
        
        /* Menu Card */
        .menu-card{
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
        .menu-card::before{
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
        .menu-card::after{
            content:'';
            position:absolute;
            top:0;
            right:0;
            width:150px;
            height:150px;
            background:radial-gradient(circle,rgba(212,175,55,0.05) 0%,transparent 70%);
            pointer-events:none;
        }
        .menu-card:hover{
            transform:translateY(-6px);
            box-shadow:0 12px 40px rgba(212,175,55,0.25);
            border-color:rgba(212,175,55,0.5);
        }
        .menu-card:hover::before{
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
        .menu-card:hover .card-icon{
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
        .menu-card:hover .card-title{
            color:#d4af37;
        }
        
        /* Logout Card - Different Style */
        .menu-card.logout-card{
            background:linear-gradient(135deg,#2a1a1a 0%,#1a0f0f 100%);
            border-color:rgba(239,68,68,0.3);
        }
        .menu-card.logout-card::before{
            background:linear-gradient(90deg,#dc2626,#ef4444,#dc2626);
        }
        .menu-card.logout-card .card-icon{
            background:linear-gradient(135deg,rgba(239,68,68,0.2) 0%,rgba(220,38,38,0.1) 100%);
            border-color:rgba(239,68,68,0.3);
            box-shadow:0 0 20px rgba(239,68,68,0.2);
        }
        .menu-card.logout-card:hover{
            border-color:rgba(239,68,68,0.5);
            box-shadow:0 12px 40px rgba(239,68,68,0.25);
        }
        .menu-card.logout-card:hover .card-icon{
            box-shadow:0 0 30px rgba(239,68,68,0.4);
            border-color:rgba(239,68,68,0.5);
        }
        .menu-card.logout-card .card-title{
            color:#fca5a5;
        }
        .menu-card.logout-card:hover .card-title{
            color:#ef4444;
        }
        
        /* Back Card - Different Style */
        .menu-card.back-card{
            background:linear-gradient(135deg,#1a1a2a 0%,#0f0f1a 100%);
            border-color:rgba(99,102,241,0.3);
        }
        .menu-card.back-card::before{
            background:linear-gradient(90deg,#6366f1,#818cf8,#6366f1);
        }
        .menu-card.back-card .card-icon{
            background:linear-gradient(135deg,rgba(99,102,241,0.2) 0%,rgba(79,70,229,0.1) 100%);
            border-color:rgba(99,102,241,0.3);
            box-shadow:0 0 20px rgba(99,102,241,0.2);
        }
        .menu-card.back-card:hover{
            border-color:rgba(99,102,241,0.5);
            box-shadow:0 12px 40px rgba(99,102,241,0.25);
        }
        .menu-card.back-card:hover .card-icon{
            box-shadow:0 0 30px rgba(99,102,241,0.4);
            border-color:rgba(99,102,241,0.5);
        }
        .menu-card.back-card .card-title{
            color:#a5b4fc;
        }
        .menu-card.back-card:hover .card-title{
            color:#818cf8;
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
        .menu-card:nth-child(1) .card-icon{
            filter:drop-shadow(0 0 15px rgba(212,175,55,0.3));
        }
        .menu-card:nth-child(2) .card-icon{
            filter:drop-shadow(0 0 15px rgba(212,175,55,0.3));
        }
        .menu-card:nth-child(3) .card-icon{
            filter:drop-shadow(0 0 15px rgba(212,175,55,0.3));
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="topbar-left">
        <div class="logo">VatanBilet Firma</div>
        <div class="topbar-title">Premium Yönetim Sistemi</div>
    </div>
    <div class="user-info">
        <div class="user-avatar"><?= strtoupper(substr($fullname, 0, 1)) ?></div>
        <div class="user-name"><?= e($fullname) ?></div>
        <a href="/logout.php" class="logout-link">Çıkış Yap</a>
    </div>
</div>

<div class="main-container">
    <div class="page-header">
        <h1>Firma Yönetim Paneli</h1>
        <p>Sefer ve kupon işlemlerinizi buradan yönetebilirsiniz</p>
    </div>

    <div class="card-grid">
        <a class="menu-card" href="/sefer/sefer_ekle.php">
            <div class="card-icon">🚌</div>
            <div class="card-title">Sefer Ekle / Düzenle</div>
            <div class="card-description">Yeni sefer ekleyin veya mevcut seferleri düzenleyin</div>
        </a>

        <a class="menu-card" href="/sefer/sefer_kaldir.php">
            <div class="card-icon">🗑️</div>
            <div class="card-title">Sefer Kaldır</div>
            <div class="card-description">Sistemden sefer silme işlemleri</div>
        </a>

        <a class="menu-card" href="/kupon/kupon_olustur.php">
            <div class="card-icon">🎟️</div>
            <div class="card-title">Kupon Oluştur</div>
            <div class="card-description">İndirim kuponları oluşturun ve yönetin</div>
        </a>

        <a class="menu-card back-card" href="/index.php">
            <div class="card-icon">🏠</div>
            <div class="card-title">Geri Dön</div>
            <div class="card-description">Ana sayfaya dönün</div>
        </a>

        <a class="menu-card logout-card" href="/logout.php">
            <div class="card-icon">🚪</div>
            <div class="card-title">Çıkış Yap</div>
            <div class="card-description">Güvenli çıkış yapın</div>
        </a>
    </div>
</div>

</body>
</html>