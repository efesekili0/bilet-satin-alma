<?php
ob_start();
session_start();
require_once __DIR__ . "/includes/database.php";

if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

if(empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])){
        $message = "Geçersiz istek!";
    } else {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        if (empty($fullname) || empty($email) || empty($password)) {
            $message = "Lütfen tüm alanları doldurun.";
        } else {
            
            $stmt = $pdo->prepare("SELECT id, fullname, password, role FROM User WHERE email = ? AND fullname = ?");
            $stmt->execute([$email, $fullname]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($password, $user['password'])) {
                
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['fullname'] = $user['fullname'];
                header("Location: /index.php");
                exit;
            } else {
                $message = "E-posta veya şifre yanlış.";
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
<title>Giriş Yap - VatanBilet</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #0a0a0a;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    color: #ffffff;
    padding: 20px;
}

.login-wrapper {
    display: flex;
    background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
    border-radius: 24px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.8), 0 0 80px rgba(212, 175, 55, 0.15);
    overflow: hidden;
    max-width: 1100px;
    width: 100%;
    border: 1px solid rgba(212, 175, 55, 0.2);
}

.login-banner {
    flex: 1;
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
    padding: 60px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    color: white;
    position: relative;
    border-right: 1px solid rgba(212, 175, 55, 0.2);
}

.login-banner::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 30% 50%, rgba(212, 175, 55, 0.05) 0%, transparent 60%);
    opacity: 0.5;
}

.login-banner::after {
    content: '🎫';
    position: absolute;
    font-size: 250px;
    opacity: 0.03;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) rotate(-15deg);
    pointer-events: none;
}

.logo-section {
    position: relative;
    z-index: 1;
}

.logo-section h1 {
    font-size: 42px;
    font-weight: 700;
    margin-bottom: 20px;
    letter-spacing: -0.5px;
    background: linear-gradient(135deg, #ffffff 0%, #d4af37 50%, #f4d03f 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 0 30px rgba(212, 175, 55, 0.3);
}

.logo-section p {
    font-size: 16px;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 300;
}

.feature-list {
    margin-top: 50px;
    position: relative;
    z-index: 1;
}

.feature-item {
    display: flex;
    align-items: center;
    margin-bottom: 24px;
    padding: 16px;
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.05) 0%, rgba(212, 175, 55, 0.02) 100%);
    border-radius: 12px;
    border: 1px solid rgba(212, 175, 55, 0.15);
    transition: all 0.3s ease;
}

.feature-item:hover {
    border-color: rgba(212, 175, 55, 0.3);
    transform: translateX(5px);
}

.feature-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.15) 0%, rgba(212, 175, 55, 0.1) 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    font-size: 24px;
    border: 1px solid rgba(212, 175, 55, 0.2);
    filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.2));
}

.feature-text {
    font-size: 15px;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
}

.login-container {
    flex: 1;
    padding: 60px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.login-header {
    margin-bottom: 40px;
}

.login-header h2 {
    font-size: 32px;
    font-weight: 700;
    background: linear-gradient(135deg, #ffffff 0%, #d4af37 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 12px;
}

.login-header p {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.6);
    font-weight: 400;
}

.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #d4af37;
    margin-bottom: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.input-wrapper {
    position: relative;
}

input[type="text"],
input[type="email"],
input[type="password"] {
    width: 100%;
    padding: 14px 18px;
    border-radius: 12px;
    border: 1.5px solid rgba(212, 175, 55, 0.3);
    background: rgba(255, 255, 255, 0.03);
    color: #ffffff;
    font-size: 15px;
    font-family: 'Inter', sans-serif;
    transition: all 0.3s ease;
}

input[type="text"]:focus,
input[type="email"]:focus,
input[type="password"]:focus {
    outline: none;
    border-color: #d4af37;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
    background: rgba(255, 255, 255, 0.05);
}

input::placeholder {
    color: rgba(255, 255, 255, 0.4);
}

button[type="submit"] {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 50%, #d4af37 100%);
    color: #000000;
    font-size: 17px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'Inter', sans-serif;
    margin-top: 12px;
    box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
    position: relative;
    overflow: hidden;
}

button[type="submit"]::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

button[type="submit"]:hover::before {
    width: 300px;
    height: 300px;
}

button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 30px rgba(212, 175, 55, 0.5);
    filter: brightness(1.1);
}

button[type="submit"]:active {
    transform: translateY(0);
}

.message {
    padding: 16px 18px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.message::before {
    content: '⚠';
    margin-right: 12px;
    font-size: 20px;
}

.error {
    background: linear-gradient(135deg, rgba(220, 38, 38, 0.15) 0%, rgba(239, 68, 68, 0.1) 100%);
    color: #fca5a5;
    border: 1px solid rgba(220, 38, 38, 0.3);
}

.success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(5, 150, 105, 0.1) 100%);
    color: #6ee7b7;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.success::before {
    content: '✓';
}

.register-link {
    text-align: center;
    margin-top: 40px;
    padding-top: 32px;
    border-top: 1px solid rgba(212, 175, 55, 0.15);
}

.register-link p {
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
}

.register-link a {
    color: #f4d03f;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    text-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
}

.register-link a:hover {
    color: #d4af37;
    text-decoration: underline;
}

@media (max-width: 768px) {
    .login-wrapper {
        flex-direction: column;
    }
    
    .login-banner {
        padding: 40px 30px;
        border-right: none;
        border-bottom: 1px solid rgba(212, 175, 55, 0.2);
    }
    
    .login-container {
        padding: 40px 30px;
    }
    
    .logo-section h1 {
        font-size: 32px;
    }
    
    .login-header h2 {
        font-size: 26px;
    }
}
</style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-banner">
        <div class="logo-section">
            <h1>VatanBilet</h1>
            <p>Türkiye'nin premium bilet platformuna hoş geldiniz. Lüks yolculuk deneyiminiz başlasın.</p>
        </div>
        <div class="feature-list">
            <div class="feature-item">
                <div class="feature-icon">🎫</div>
                <div class="feature-text">Türkiye’nin her yerine sefer</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">🔒</div>
                <div class="feature-text">Güvenli ödeme sistemi</div>
            </div>
            <div class="feature-item">
                <div class="feature-icon">⚡</div>
                <div class="feature-text">Anında bilet teslimi</div>
            </div>
        </div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <h2>Hesabınıza Giriş Yapın</h2>
            <p>Premium etkinliklere erişmek için bilgilerinizi girin</p>
        </div>

        <?php if(!empty($message)) echo "<div class='message error'>".$message."</div>"; ?>

        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="form-group">
                <label for="fullname">Ad Soyad</label>
                <div class="input-wrapper">
                    <input type="text" id="fullname" name="fullname" placeholder="Adınızı ve soyadınızı girin" required autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label for="email">E-posta Adresi</label>
                <div class="input-wrapper">
                    <input type="email" id="email" name="email" placeholder="ornek@email.com" required autocomplete="off">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Şifre</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Şifrenizi girin" required autocomplete="off">
                </div>
            </div>

            <button type="submit">Giriş Yap</button>
        </form>

        <div class="register-link">
            <p>Hesabınız yok mu? <a href="/register.php">Hemen Kayıt Olun</a></p>
        </div>
    </div>
</div>

</body>
</html>