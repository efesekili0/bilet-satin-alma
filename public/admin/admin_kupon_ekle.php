<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/database.php';


header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");


if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];


function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}


function e($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }


$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_coupon'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $error = "Geçersiz istek (CSRF).";
    } else {
        $coupon_id = trim($_POST['coupon_id'] ?? '');
        if ($coupon_id === '') {
            $error = "Kupon ID eksik.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM Coupons WHERE id = ?");
                $stmt->execute([$coupon_id]);
                $exists = $stmt->fetchColumn();
                if (!$exists) {
                    $error = "Kupon bulunamadı.";
                } else {
                    
                    $pdo->beginTransaction();
                    
                    
                    $del_user_coupons = $pdo->prepare("DELETE FROM User_Coupons WHERE coupon_id = ?");
                    $del_user_coupons->execute([$coupon_id]);
                    
                    
                    $del = $pdo->prepare("DELETE FROM Coupons WHERE id = ?");
                    $del->execute([$coupon_id]);
                    
                    
                    $pdo->commit();
                    
                    if ($del->rowCount() > 0) {
                        $_SESSION['success'] = "Kupon başarıyla silindi!";
                    } else {
                        $_SESSION['error'] = "Kupon silme başarısız oldu.";
                    }
                    header("Location: /admin/admin_kupon_ekle.php");
                    exit;
                }
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Coupon delete error: " . $ex->getMessage());
                $error = "İşlem sırasında hata oluştu: " . $ex->getMessage();
            }
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_coupon'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $error = "Geçersiz istek (CSRF).";
    } else {
        $code = trim($_POST['code'] ?? '');
        $discount = $_POST['discount'] ?? '';
        $usage_limit = $_POST['usage_limit'] ?? '';
        $expire_date = trim($_POST['expire_date'] ?? '');

        if (!preg_match('/^[A-Za-z0-9\-_]{3,64}$/', $code)) {
            $error = "Kupon kodu geçersiz. (Sadece harf/rakam/-/_ ve 3-64 karakter arası)";
        } elseif (!is_numeric($discount) || floatval($discount) < 0 || floatval($discount) > 100) {
            $error = "İndirim değeri 0-100 arasında olmalıdır.";
        } elseif (!ctype_digit(strval($usage_limit)) || intval($usage_limit) < 0) {
            $error = "Kullanım limiti geçersiz.";
        } else {
            $expire_dt = DateTime::createFromFormat('Y-m-d', $expire_date);
            $expire_valid = $expire_dt && $expire_dt->format('Y-m-d') === $expire_date;
            if (!$expire_valid) {
                $error = "Bitiş tarihi geçersiz (YYYY-MM-DD).";
            }
        }

        if (empty($error)) {
            try {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Coupons WHERE code = ?");
                $stmt_check->execute([$code]);
                if ($stmt_check->fetchColumn() > 0) {
                    $error = "Bu kupon kodu zaten mevcut!";
                } else {
                    $stmt = $pdo->query("SELECT id FROM Bus_Company");
                    $company_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (empty($company_ids)) {
                        $error = "Hiç firma bulunamadı.";
                    } else {
                        $pdo->beginTransaction();
                        $insert_sql = "INSERT INTO Coupons (id, code, discount, company_id, usage_limit, expire_date, created_at)
                                       VALUES (:id, :code, :discount, :company_id, :usage_limit, :expire_date, datetime('now'))";
                        $ins_stmt = $pdo->prepare($insert_sql);

                        $id_base = bin2hex(random_bytes(8));
                        $created_count = 0;
                        foreach ($company_ids as $company_id) {
                            $id = "coup_{$id_base}_{$company_id}";
                            $ok = $ins_stmt->execute([
                                ':id' => $id,
                                ':code' => $code,
                                ':discount' => floatval($discount),
                                ':company_id' => $company_id,
                                ':usage_limit' => intval($usage_limit),
                                ':expire_date' => $expire_date
                            ]);
                            if ($ok) $created_count++;
                        }

                        $pdo->commit();

                        $_SESSION['success'] = "Kupon oluşturuldu. " . intval($created_count) . " firma için eklendi.";
                        header("Location: /admin/admin_kupon_ekle.php");
                        exit;
                    }
                }
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Coupon create error: " . $ex->getMessage());
                $error = "Kupon oluşturulurken hata oluştu.";
            }
        }
    }
}


try {
    $stmt = $pdo->query("SELECT Coupons.*, Bus_Company.name AS company_name 
                         FROM Coupons 
                         LEFT JOIN Bus_Company ON Coupons.company_id = Bus_Company.id
                         ORDER BY created_at DESC");
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $ex) {
    error_log("Coupon list fetch error: " . $ex->getMessage());
    $coupons = [];
    $error = $error ?? "Kuponlar yüklenirken hata oluştu.";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kupon Yönetimi - VatanBilet Admin</title>
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
    max-width:1400px;
    margin:0 auto;
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

/* Card */
.card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:16px;
    padding:28px;
    box-shadow:0 4px 20px rgba(0,0,0,0.4);
    border:1px solid rgba(212,175,55,0.2);
    margin-bottom:24px;
}
.card-title{
    font-size:18px;
    font-weight:700;
    color:#f4d03f;
    margin-bottom:20px;
    padding-bottom:16px;
    border-bottom:2px solid rgba(212,175,55,0.2);
    text-shadow:0 0 10px rgba(212,175,55,0.3);
    display:flex;
    align-items:center;
    gap:10px;
}
.card-title::before{
    content:'🎟️';
    font-size:24px;
    filter:drop-shadow(0 0 10px rgba(212,175,55,0.5));
}

/* Form Grid */
.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:20px;
    margin-bottom:20px;
}
.form-group{
    display:flex;
    flex-direction:column;
}
.form-label{
    margin-bottom:8px;
    color:#f4d03f;
    font-weight:600;
    font-size:14px;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
.form-input{
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
    font-size:13px;
    color:rgba(255,255,255,0.6);
    margin-top:16px;
    padding:14px 16px;
    background:rgba(26,26,26,0.6);
    border-radius:10px;
    border-left:3px solid rgba(212,175,55,0.5);
    backdrop-filter:blur(10px);
}
.form-help::before{
    content:'💡';
    margin-right:8px;
}

/* Button */
.btn-primary{
    padding:14px 28px;
    border-radius:10px;
    font-size:15px;
    font-weight:600;
    text-align:center;
    cursor:pointer;
    transition:all 0.3s;
    border:none;
    font-family:'Inter',sans-serif;
    background:linear-gradient(135deg,#d4af37,#f4d03f);
    color:#000;
    box-shadow:0 0 20px rgba(212,175,55,0.3);
    width:100%;
    margin-top:20px;
}
.btn-primary:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 16px rgba(212,175,55,0.4),0 0 30px rgba(212,175,55,0.3);
}

/* Table */
.table-wrapper{
    overflow-x:auto;
    border-radius:12px;
    border:1px solid rgba(212,175,55,0.2);
    background:rgba(15,15,15,0.8);
}
table{
    width:100%;
    border-collapse:collapse;
}
thead{
    background:rgba(26,26,26,0.8);
    backdrop-filter:blur(10px);
}
th{
    padding:16px;
    text-align:left;
    font-size:13px;
    font-weight:700;
    color:#f4d03f;
    text-transform:uppercase;
    letter-spacing:0.5px;
    border-bottom:2px solid rgba(212,175,55,0.3);
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
td{
    padding:14px 16px;
    font-size:14px;
    color:rgba(255,255,255,0.8);
    border-bottom:1px solid rgba(212,175,55,0.1);
}
tbody tr{
    transition:all 0.2s;
}
tbody tr:hover{
    background:rgba(212,175,55,0.05);
}
tbody tr:last-child td{
    border-bottom:none;
}

/* Badges */
.badge{
    display:inline-block;
    padding:5px 12px;
    border-radius:8px;
    font-size:12px;
    font-weight:600;
    backdrop-filter:blur(10px);
}
.badge-discount{
    background:linear-gradient(135deg,rgba(16,185,129,0.2),rgba(5,150,105,0.15));
    color:#34d399;
    border:1px solid rgba(16,185,129,0.3);
}
.badge-company{
    background:linear-gradient(135deg,rgba(59,130,246,0.2),rgba(37,99,235,0.15));
    color:#60a5fa;
    border:1px solid rgba(59,130,246,0.3);
}

/* Delete Button */
.btn-delete{
    background:linear-gradient(135deg,rgba(239,68,68,0.2),rgba(220,38,38,0.15));
    color:#fca5a5;
    border:1px solid rgba(239,68,68,0.3);
    padding:8px 16px;
    border-radius:8px;
    font-size:13px;
    font-weight:600;
    cursor:pointer;
    transition:all 0.3s;
    backdrop-filter:blur(10px);
}
.btn-delete:hover{
    background:rgba(239,68,68,0.3);
    transform:translateY(-1px);
    box-shadow:0 4px 12px rgba(239,68,68,0.2);
}

/* Empty State */
.empty-state{
    text-align:center;
    padding:60px 20px;
    color:rgba(255,255,255,0.4);
}
.empty-state-icon{
    font-size:64px;
    margin-bottom:20px;
    filter:grayscale(1) opacity(0.5);
}
.empty-state-text{
    font-size:16px;
    color:rgba(255,255,255,0.5);
}

/* Stats */
.stats-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:16px;
    margin-bottom:24px;
}
.stat-card{
    background:linear-gradient(135deg,rgba(26,26,26,0.8),rgba(15,15,15,0.6));
    border:1px solid rgba(212,175,55,0.2);
    border-radius:12px;
    padding:20px;
    backdrop-filter:blur(10px);
}
.stat-label{
    font-size:13px;
    color:rgba(255,255,255,0.6);
    margin-bottom:8px;
    text-transform:uppercase;
    letter-spacing:0.5px;
}
.stat-value{
    font-size:28px;
    font-weight:700;
    background:linear-gradient(135deg,#d4af37,#f4d03f);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
}

/* Responsive */
@media(max-width:1024px){
    .page-header{
        flex-direction:column;
        align-items:flex-start;
        gap:16px;
    }
    .page-header-user{
        text-align:left;
    }
}

@media(max-width:768px){
    body{padding:20px 16px;}
    .page-header{padding:24px 20px;}
    .card{padding:20px;}
    .page-header-content h1{font-size:24px;}
    .form-grid{grid-template-columns:1fr;}
    .stats-grid{grid-template-columns:1fr;}
    th,td{padding:10px 12px;font-size:13px;}
}

/* Scrollbar */
.table-wrapper::-webkit-scrollbar{height:8px;}
.table-wrapper::-webkit-scrollbar-track{background:rgba(26,26,26,0.6);border-radius:10px;}
.table-wrapper::-webkit-scrollbar-thumb{background:rgba(212,175,55,0.3);border-radius:10px;}
.table-wrapper::-webkit-scrollbar-thumb:hover{background:rgba(212,175,55,0.5);}
</style>
</head>
<body>

<div class="container">
    <!-- Back Link -->
    <a href="/admin/admin_panel.php" class="back-link">Admin Paneline Dön</a>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1>Kupon Yönetimi</h1>
            <p>Tüm firmalar için geçerli kuponlar oluşturun ve yönetin</p>
        </div>
        <div class="page-header-user">
            <div class="user-name"><?= e($_SESSION['fullname'] ?? 'Admin') ?></div>
            <div class="user-role"><?= e($_SESSION['role'] ?? 'Yönetici') ?></div>
        </div>
    </div>

    <!-- Messages -->
    <?php if($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Create Coupon Card -->
    <div class="card">
        <h2 class="card-title">Yeni Kupon Oluştur</h2>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="code">Kupon Kodu</label>
                    <input type="text" id="code" name="code" class="form-input" 
                           placeholder="YILBASI2025" required pattern="[A-Za-z0-9\-_]{3,64}">
                </div>

                <div class="form-group">
                    <label class="form-label" for="discount">İndirim Oranı (%)</label>
                    <input type="number" id="discount" name="discount" class="form-input" 
                           placeholder="20" step="0.01" min="0" max="100" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="usage_limit">Kullanım Limiti</label>
                    <input type="number" id="usage_limit" name="usage_limit" class="form-input" 
                           placeholder="100" min="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="expire_date">Son Kullanım Tarihi</label>
                    <input type="date" id="expire_date" name="expire_date" class="form-input" required>
                </div>
            </div>

            <div class="form-help">
                Kupon kodu sadece harf, rakam, tire (-) ve alt çizgi (_) içerebilir. 
                Oluşturulan kupon tüm firmalara otomatik olarak atanır.
            </div>

            <button type="submit" name="create_coupon" class="btn-primary">
                🎟️ Tüm Firmalar İçin Kupon Oluştur
            </button>
        </form>
    </div>

    <!-- Coupons List Card -->
    <div class="card">
        <h2 class="card-title">Mevcut Kuponlar (<?= count($coupons) ?>)</h2>
        
        <?php if(empty($coupons)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🎟️</div>
                <div class="empty-state-text">Henüz kupon oluşturulmamış</div>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Kupon Kodu</th>
                            <th>İndirim</th>
                            <th>Firma</th>
                            <th>Kullanım Limiti</th>
                            <th>Son Kullanım</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($coupons as $c): ?>
                        <tr>
                            <td><strong><?= e($c['code']) ?></strong></td>
                            <td><span class="badge badge-discount"><?= e($c['discount']) ?>%</span></td>
                            <td><span class="badge badge-company"><?= e($c['company_name'] ?? 'N/A') ?></span></td>
                            <td><?= e($c['usage_limit']) ?></td>
                            <td><?= e($c['expire_date']) ?></td>
                            <td>
                                <form method="POST" style="margin:0;display:inline;" 
                                      onsubmit="return confirm('Bu kuponu silmek istediğinize emin misiniz?');">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="coupon_id" value="<?= e($c['id']) ?>">
                                    <button type="submit" name="delete_coupon" class="btn-delete">Sil</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>