<?php

$role = $_SESSION['role'] ?? 'visitor'; 
$fullname = $_SESSION['fullname'] ?? '';

?>
<nav class="navbar">
    <div class="navbar-container">
        <div class="navbar-brand">
            <a href="/index.php" class="brand-link">
                <span class="brand-icon">🎫</span>
                <h2 class="brand-text">Vatan<span class="brand-highlight">Bilet</span></h2>
            </a>
        </div>
        
        <div class="navbar-menu">
            <?php if($role == 'visitor'): ?>
                <a href="/login.php" class="nav-btn nav-btn-primary">Giriş Yap</a>
                <a href="/register.php" class="nav-btn nav-btn-outline">Kayıt Ol</a>
            <?php endif; ?>
            
            <?php if($role == 'user'): ?>
                <div class="user-info">
                    <span class="user-icon">👤</span>
                    <span class="user-name"><?= htmlspecialchars($fullname) ?></span>
                </div>
                <a href="/hesabim.php" class="nav-btn nav-btn-secondary">Hesabım</a>
                <a href="/bilet/bilet_iptal.php" class="nav-btn nav-btn-secondary">🎟️ Biletlerim</a>
                <a href="/logout.php" class="nav-btn nav-btn-danger">Çıkış Yap</a>
            <?php endif; ?>
            
            <?php if($role == 'firma_admin'): ?>
                <div class="user-info">
                    <span class="user-icon">🏢</span>
                    <span class="user-name"><?= htmlspecialchars($fullname) ?></span>
                </div>
                <a href="/firma/firma_panel.php" class="nav-btn nav-btn-secondary">Firma Paneli</a>
                <a href="/hesabim.php" class="nav-btn nav-btn-secondary">Biletlerim</a>
                <a href="/logout.php" class="nav-btn nav-btn-danger">Çıkış Yap</a>
            <?php endif; ?>
            
            <?php if($role == 'admin'): ?>
                <div class="user-info">
                    <span class="user-icon">⚙️</span>
                    <span class="user-name"><?= htmlspecialchars($fullname) ?></span>
                </div>
                <a href="/admin/admin_panel.php" class="nav-btn nav-btn-secondary">Admin Paneli</a>
                <a href="/logout.php" class="nav-btn nav-btn-danger">Çıkış Yap</a>
            <?php endif; ?>
        </div>

       
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>
    </div>
</nav>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

/* Navbar Container */
.navbar {
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0f0f0f 100%);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5), 0 0 40px rgba(212, 175, 55, 0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
    font-family: 'Inter', sans-serif;
    border-bottom: 1px solid rgba(212, 175, 55, 0.2);
}

.navbar-container {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 40px;
}

/* Brand Section */
.navbar-brand {
    display: flex;
    align-items: center;
}

.brand-link {
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
}

.brand-link:hover {
    transform: scale(1.05);
}

.brand-link:hover .brand-icon {
    filter: drop-shadow(0 0 8px rgba(212, 175, 55, 0.6));
}

.brand-icon {
    font-size: 32px;
    filter: drop-shadow(0 2px 8px rgba(212, 175, 55, 0.3));
    transition: filter 0.3s ease;
}

.brand-text {
    font-size: 26px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: 0.5px;
    margin: 0;
    text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);
}

.brand-highlight {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 50%, #d4af37 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.5));
}

/* Navbar Menu */
.navbar-menu {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* User Info */
.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 18px;
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(212, 175, 55, 0.05) 100%);
    border-radius: 12px;
    margin-right: 8px;
    border: 1px solid rgba(212, 175, 55, 0.3);
    box-shadow: 0 2px 10px rgba(212, 175, 55, 0.1);
}

.user-icon {
    font-size: 18px;
    filter: drop-shadow(0 0 4px rgba(212, 175, 55, 0.4));
}

.user-name {
    font-size: 14px;
    font-weight: 600;
    color: #f4d03f;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-shadow: 0 0 8px rgba(212, 175, 55, 0.3);
}

/* Navigation Buttons */
.nav-btn {
    padding: 11px 22px;
    border-radius: 12px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
    display: inline-block;
    position: relative;
    overflow: hidden;
}

/* Primary Button */
.nav-btn-primary {
    background: linear-gradient(135deg, #d4af37 0%, #f4d03f 50%, #d4af37 100%);
    color: #000000;
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
    font-weight: 700;
}

.nav-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(212, 175, 55, 0.5);
    filter: brightness(1.1);
}

/* Outline Button */
.nav-btn-outline {
    background: transparent;
    color: #d4af37;
    border: 2px solid #d4af37;
    box-shadow: 0 0 15px rgba(212, 175, 55, 0.2);
}

.nav-btn-outline:hover {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.15) 0%, rgba(212, 175, 55, 0.25) 100%);
    border-color: #f4d03f;
    color: #f4d03f;
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
}

/* Secondary Button */
.nav-btn-secondary {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.1) 100%);
    color: #ffffff;
    border: 1px solid rgba(212, 175, 55, 0.2);
}

.nav-btn-secondary:hover {
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.15) 0%, rgba(212, 175, 55, 0.25) 100%);
    border-color: rgba(212, 175, 55, 0.4);
    color: #f4d03f;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(212, 175, 55, 0.2);
}

/* Danger Button */
.nav-btn-danger {
    background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    color: #ffffff;
    box-shadow: 0 2px 10px rgba(220, 38, 38, 0.3);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.nav-btn-danger:hover {
    background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(220, 38, 38, 0.5);
}

/* Mobile Menu Toggle */
.mobile-menu-toggle {
    display: none;
    flex-direction: column;
    gap: 5px;
    background: linear-gradient(135deg, rgba(212, 175, 55, 0.1) 0%, rgba(212, 175, 55, 0.05) 100%);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 10px;
    cursor: pointer;
    padding: 10px;
}

.hamburger-line {
    width: 25px;
    height: 3px;
    background: linear-gradient(90deg, #d4af37 0%, #f4d03f 100%);
    border-radius: 2px;
    transition: all 0.3s ease;
    box-shadow: 0 0 5px rgba(212, 175, 55, 0.3);
}

/* Mobile Responsive */
@media (max-width: 968px) {
    .navbar-container {
        padding: 16px 24px;
    }
    
    .user-name {
        max-width: 100px;
    }
    
    .navbar-menu {
        gap: 8px;
    }
    
    .nav-btn {
        padding: 9px 16px;
        font-size: 13px;
    }
}

@media (max-width: 768px) {
    .navbar-container {
        flex-wrap: wrap;
        position: relative;
    }
    
    .mobile-menu-toggle {
        display: flex;
    }
    
    .navbar-menu {
        display: none;
        flex-direction: column;
        width: 100%;
        margin-top: 16px;
        gap: 10px;
        background: linear-gradient(135deg, rgba(26, 26, 26, 0.98) 0%, rgba(15, 15, 15, 0.98) 100%);
        padding: 20px;
        border-radius: 16px;
        border: 1px solid rgba(212, 175, 55, 0.2);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.8);
    }
    
    .navbar-menu.active {
        display: flex;
    }
    
    .nav-btn {
        width: 100%;
        text-align: center;
        padding: 14px 20px;
    }
    
    .user-info {
        width: 100%;
        justify-content: center;
        margin-right: 0;
    }
    
    .user-name {
        max-width: none;
    }
    
    /* Hamburger Animation */
    .mobile-menu-toggle.active .hamburger-line:nth-child(1) {
        transform: rotate(45deg) translate(7px, 7px);
    }
    
    .mobile-menu-toggle.active .hamburger-line:nth-child(2) {
        opacity: 0;
    }
    
    .mobile-menu-toggle.active .hamburger-line:nth-child(3) {
        transform: rotate(-45deg) translate(7px, -7px);
    }
}

@media (max-width: 480px) {
    .navbar-container {
        padding: 14px 16px;
    }
    
    .brand-text {
        font-size: 22px;
    }
    
    .brand-icon {
        font-size: 28px;
    }
}
</style>

<script>
// Mobil menü toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const navbarMenu = document.querySelector('.navbar-menu');
    
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            this.classList.toggle('active');
            navbarMenu.classList.toggle('active');
        });
    }
    
    // Menü dışına tıklandığında menüyü kapat
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.navbar-container')) {
            if (navbarMenu && navbarMenu.classList.contains('active')) {
                navbarMenu.classList.remove('active');
                if (mobileMenuToggle) {
                    mobileMenuToggle.classList.remove('active');
                }
            }
        }
    });
});
</script>