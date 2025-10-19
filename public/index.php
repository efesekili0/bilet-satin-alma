<?php 
if (session_status() === PHP_SESSION_NONE) 
ob_start();
session_start(); 
require __DIR__ . '/includes/database.php'; 
include __DIR__ . '/includes/navbar.php';

$role = $_SESSION['role'] ?? 'visitor';
$user_id = $_SESSION['user_id'] ?? null;

function getCities() {
    return [
        'Adana','Adıyaman','Afyonkarahisar','Ağrı','Amasya','Ankara','Antalya','Artvin','Aydın','Balıkesir',
        'Bilecik','Bingöl','Bitlis','Bolu','Burdur','Bursa','Çanakkale','Çankırı','Çorum','Denizli',
        'Diyarbakır','Edirne','Elazığ','Erzincan','Erzurum','Eskişehir','Gaziantep','Giresun','Gümüşhane','Hakkari',
        'Hatay','Isparta','Mersin','İstanbul','İzmir','Kars','Kastamonu','Kayseri','Kırklareli','Kırşehir',
        'Kocaeli','Konya','Kütahya','Malatya','Manisa','Kahramanmaraş','Mardin','Muğla','Muş','Nevşehir',
        'Niğde','Ordu','Rize','Sakarya','Samsun','Siirt','Sinop','Sivas','Tekirdağ','Tokat',
        'Trabzon','Tunceli','Şanlıurfa','Uşak','Van','Yozgat','Zonguldak','Aksaray','Bayburt','Karaman',
        'Kırıkkale','Batman','Şırnak','Bartın','Ardahan','Iğdır','Yalova','Karabük','Kilis','Osmaniye','Düzce'
    ];
}

$results = [];
$depCity = $destCity = $depDate = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_trip'])) {
    $depCity = $_POST['departure_city'] ?? '';
    $destCity = $_POST['destination_city'] ?? '';
    $depDate = $_POST['departure_date'] ?? null;

    if ($depCity && $destCity) {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $sql = "SELECT t.*, b.name AS company_name 
                FROM Trips t 
                JOIN Bus_Company b ON t.company_id = b.id
                WHERE t.departure_city = :depCity 
                  AND t.destination_city = :destCity
                  AND t.departure_time > :now";
        $params = [
            ':depCity' => $depCity, 
            ':destCity' => $destCity,
            ':now' => $now
        ];

        if ($depDate) {
            $sql .= " AND date(t.departure_time) = :depDate";
            $params[':depDate'] = DateTime::createFromFormat('d/m/Y', $depDate)->format('Y-m-d');
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
              

try {
    $stmt = $pdo->query("
    SELECT c.*, b.name AS company_name
    FROM Coupons c
    LEFT JOIN Bus_Company b ON c.company_id = b.id
    WHERE date(c.expire_date) >= date('now')
    ORDER BY c.created_at DESC
    ");
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $coupons = [];
}


function isUser() {
    return ($_SESSION['role'] ?? '') === 'user';
}


function getOccupiedSeats($pdo, $trip_id) {
    $stmt = $pdo->prepare("
        SELECT bs.seat_number 
        FROM Booked_Seats bs 
        JOIN Tickets t ON t.id = bs.ticket_id
        WHERE t.trip_id = :trip_id AND t.status = 'active'
    ");
    $stmt->execute([':trip_id' => $trip_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


$occupiedSeatsData = [];
foreach($results as $trip) {
    $occupiedSeatsData[$trip['id']] = getOccupiedSeats($pdo, $trip['id']);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VatanBilet - Ana Sayfa</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:'Inter',sans-serif;
    background:#0a0a0a;
    min-height:100vh;
    padding-bottom:60px;
}

/* Hero Section */
.hero-section{
    background:linear-gradient(135deg,#0a0a0a 0%,#1a1a1a 50%,#0f0f0f 100%);
    padding:80px 20px 100px;
    text-align:center;
    position:relative;
    overflow:hidden;
    border-bottom:2px solid rgba(212,175,55,0.2);
}
.hero-section::before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:0;
    bottom:0;
    background:radial-gradient(circle at 50% 50%,rgba(212,175,55,0.05) 0%,transparent 70%);
}
.hero-section::after{
    content:'🎫';
    position:absolute;
    font-size:200px;
    opacity:0.03;
    top:50%;
    left:50%;
    transform:translate(-50%,-50%) rotate(-15deg);
    pointer-events:none;
}
.hero-content{
    position:relative;
    z-index:1;
}
.hero-title{
    font-size:48px;
    font-weight:700;
    background:linear-gradient(135deg,#ffffff 0%,#d4af37 50%,#f4d03f 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    margin-bottom:16px;
    text-shadow:0 0 30px rgba(212,175,55,0.3);
    letter-spacing:-0.5px;
}
.hero-subtitle{
    font-size:18px;
    color:rgba(255,255,255,0.7);
    margin-bottom:40px;
    font-weight:400;
}

/* Container */
.container{
    max-width:1200px;
    margin:0 auto;
    padding:0 20px;
}

/* Search Form */
.search-card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:20px;
    box-shadow:0 10px 40px rgba(0,0,0,0.5),0 0 20px rgba(212,175,55,0.1);
    padding:36px;
    margin-top:-60px;
    position:relative;
    z-index:2;
    border:1px solid rgba(212,175,55,0.2);
}
.search-title{
    font-size:22px;
    font-weight:700;
    color:#f4d03f;
    margin-bottom:28px;
    display:flex;
    align-items:center;
    gap:12px;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
.search-title::before{
    content:'🔍';
    font-size:26px;
    filter:drop-shadow(0 0 8px rgba(212,175,55,0.4));
}
.search-form{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:18px;
}
.form-group{
    display:flex;
    flex-direction:column;
}
.form-label{
    font-size:13px;
    font-weight:600;
    color:#d4af37;
    margin-bottom:10px;
    text-transform:uppercase;
    letter-spacing:1px;
}
.form-select,.form-input{
    padding:14px 18px;
    border-radius:12px;
    border:1.5px solid rgba(212,175,55,0.3);
    background:rgba(255,255,255,0.03);
    color:#ffffff;
    font-size:15px;
    font-family:'Inter',sans-serif;
    transition:all 0.3s;
}
.form-select:focus,.form-input:focus{
    outline:none;
    border-color:#d4af37;
    box-shadow:0 0 0 3px rgba(212,175,55,0.15);
    background:rgba(255,255,255,0.05);
}
.form-select option{
    background:#1a1a1a;
    color:#ffffff;
}
.btn-search{
    padding:14px 36px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#d4af37 0%,#f4d03f 50%,#d4af37 100%);
    color:#000000;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
    transition:all 0.3s;
    align-self:flex-end;
    box-shadow:0 4px 15px rgba(212,175,55,0.3);
}
.btn-search:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 25px rgba(212,175,55,0.5);
    filter:brightness(1.1);
}

/* Section */
.section{
    padding:60px 0;
}
.section-title{
    font-size:36px;
    font-weight:700;
    background:linear-gradient(135deg,#ffffff 0%,#d4af37 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
    margin-bottom:12px;
    text-align:center;
}
.section-subtitle{
    font-size:16px;
    color:rgba(255,255,255,0.6);
    margin-bottom:40px;
    text-align:center;
}

/* Trip Cards */
.trips-grid{
    display:flex;
    flex-direction:column;
    gap:24px;
}
.trip-card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    border-radius:20px;
    padding:28px;
    box-shadow:0 4px 20px rgba(0,0,0,0.4);
    border:1px solid rgba(212,175,55,0.2);
    transition:all 0.3s;
    position:relative;
    overflow:hidden;
}
.trip-card::before{
    content:'';
    position:absolute;
    top:0;
    right:0;
    width:200px;
    height:200px;
    background:radial-gradient(circle,rgba(212,175,55,0.05) 0%,transparent 70%);
    pointer-events:none;
}
.trip-card:hover{
    transform:translateY(-4px);
    box-shadow:0 8px 30px rgba(212,175,55,0.2);
    border-color:rgba(212,175,55,0.4);
}
.trip-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    margin-bottom:24px;
    padding-bottom:20px;
    border-bottom:2px solid rgba(212,175,55,0.15);
}
.company-info h3{
    font-size:24px;
    color:#f4d03f;
    margin-bottom:8px;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
.price-badge{
    background:linear-gradient(135deg,#d4af37 0%,#f4d03f 100%);
    color:#000000;
    padding:10px 24px;
    border-radius:12px;
    font-size:22px;
    font-weight:700;
    box-shadow:0 4px 15px rgba(212,175,55,0.3);
}
.trip-details{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
    margin-bottom:24px;
}
.detail-item{
    display:flex;
    align-items:flex-start;
    gap:14px;
}
.detail-icon{
    width:44px;
    height:44px;
    border-radius:12px;
    background:linear-gradient(135deg,rgba(212,175,55,0.1) 0%,rgba(212,175,55,0.05) 100%);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:22px;
    flex-shrink:0;
    border:1px solid rgba(212,175,55,0.2);
}
.detail-content{
    flex:1;
}
.detail-label{
    font-size:12px;
    color:#d4af37;
    font-weight:600;
    text-transform:uppercase;
    margin-bottom:6px;
    letter-spacing:0.5px;
}
.detail-value{
    font-size:16px;
    color:#ffffff;
    font-weight:600;
}
.btn-select-seat{
    width:100%;
    padding:14px 28px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#d4af37 0%,#f4d03f 100%);
    color:#000000;
    font-size:16px;
    font-weight:700;
    cursor:pointer;
    transition:all 0.3s;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    box-shadow:0 4px 15px rgba(212,175,55,0.3);
}
.btn-select-seat:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 25px rgba(212,175,55,0.5);
    filter:brightness(1.1);
}
.btn-select-seat::before{
    content:'🪑';
    font-size:20px;
}

/* Seat Map */
.seat-map{
    display:none;
    flex-direction:column;
    gap:12px;
    justify-content:center;
    align-items:center;
    margin-top:28px;
    padding:40px 48px;
    background:linear-gradient(180deg,#1a1a1a 0%,#0f0f0f 50%,#1a1a1a 100%);
    border-radius:24px;
    box-shadow:inset 0 8px 20px rgba(0,0,0,0.5),0 0 30px rgba(212,175,55,0.1);
    position:relative;
    border:2px solid rgba(212,175,55,0.2);
}
.seat-map::before{
    content:'';
    position:absolute;
    top:15px;
    left:50%;
    transform:translateX(-50%);
    width:calc(100% - 90px);
    height:calc(100% - 30px);
    border-radius:40px;
    border:3px solid rgba(212,175,55,0.15);
    pointer-events:none;
    box-shadow:inset 0 4px 15px rgba(0,0,0,0.3);
}
.seat-row{
    display:flex;
    gap:10px;
    justify-content:center;
    align-items:center;
}
.seat{
    width:48px;
    height:48px;
    border-radius:10px;
    text-align:center;
    line-height:48px;
    background:linear-gradient(135deg,#2a2a2a 0%,#1a1a1a 100%);
    border:2px solid rgba(212,175,55,0.3);
    cursor:pointer;
    transition:all 0.3s;
    font-weight:700;
    font-size:14px;
    color:#ffffff;
    box-shadow:0 3px 8px rgba(0,0,0,0.3);
    position:relative;
    z-index:1;
}
.seat:hover:not(.occupied){
    border-color:#d4af37;
    transform:scale(1.1);
    box-shadow:0 0 15px rgba(212,175,55,0.4);
}
.seat.selected{
    background:linear-gradient(135deg,#d4af37 0%,#f4d03f 100%);
    color:#000000;
    border-color:#d4af37;
    box-shadow:0 0 20px rgba(212,175,55,0.5);
}
.seat.occupied{
    background:#3a3a3a;
    color:#6b7280;
    cursor:not-allowed;
    border-color:#4a4a4a;
    opacity:0.5;
}
.driver-icon{
    width:2px;
    height:60px;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:flex-start;
    position:relative;
    z-index:1;
    transform:translateX(-40px);
}
.driver-icon::after{
    content:'';
    width:24px;
    height:24px;
    border-radius:50%;
    background:#ffd1a3;
    border:2px solid #d4af37;
    position:absolute;
    top:8px;
    box-shadow:0 0 10px rgba(212,175,55,0.4);
}
.driver-body{
    position:absolute;
    top:34px;
    width:34px;
    height:20px;
    background:#e8e8e8;
    border-radius:10px 10px 0 0;
    border:2px solid #d4af37;
    border-bottom:none;
}
.driver-tie{
    position:absolute;
    top:34px;
    width:0;
    height:0;
    border-left:5px solid transparent;
    border-right:5px solid transparent;
    border-top:14px solid #d4af37;
    z-index:2;
}

/* Payment Section */
.payment-section{
    display:none;
    margin-top:24px;
    padding-top:24px;
    border-top:2px solid rgba(212,175,55,0.2);
}
.selected-info{
    background:linear-gradient(135deg,rgba(212,175,55,0.1) 0%,rgba(212,175,55,0.05) 100%);
    padding:20px;
    border-radius:12px;
    margin-bottom:20px;
    text-align:center;
    border:1px solid rgba(212,175,55,0.2);
}
.selected-info-text{
    font-size:14px;
    color:#d4af37;
    margin-bottom:10px;
    font-weight:600;
    text-transform:uppercase;
}
.selected-seats-display{
    font-size:20px;
    font-weight:700;
    color:#f4d03f;
    text-shadow:0 0 10px rgba(212,175,55,0.3);
}
.btn-payment{
    width:100%;
    padding:16px 28px;
    border:none;
    border-radius:12px;
    background:linear-gradient(135deg,#10b981 0%,#059669 100%);
    color:#ffffff;
    font-size:17px;
    font-weight:700;
    cursor:pointer;
    transition:all 0.3s;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    box-shadow:0 4px 15px rgba(16,185,129,0.3);
}
.btn-payment:hover{
    transform:translateY(-2px);
    box-shadow:0 6px 25px rgba(16,185,129,0.5);
}
.btn-payment::before{
    content:'💳';
    font-size:22px;
}

/* Coupons */
.coupons-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
    gap:24px;
}
.coupon-card{
    background:linear-gradient(135deg,#1a1a1a 0%,#0f0f0f 100%);
    color:#fff;
    padding:28px;
    border-radius:20px;
    box-shadow:0 4px 20px rgba(0,0,0,0.4);
    transition:all 0.3s;
    position:relative;
    overflow:hidden;
    border:1px solid rgba(212,175,55,0.3);
}
.coupon-card::before{
    content:'';
    position:absolute;
    top:-50%;
    right:-50%;
    width:200%;
    height:200%;
    background:radial-gradient(circle,rgba(212,175,55,0.1) 0%,transparent 70%);
    animation:shimmer 4s infinite;
}
@keyframes shimmer{
    0%{transform:translate(-50%,-50%) rotate(0deg);}
    100%{transform:translate(-50%,-50%) rotate(360deg);}
}
.coupon-card:hover{
    transform:translateY(-4px);
    box-shadow:0 8px 30px rgba(212,175,55,0.3);
    border-color:rgba(212,175,55,0.5);
}
.coupon-code{
    font-size:26px;
    font-weight:700;
    margin-bottom:14px;
    letter-spacing:3px;
    position:relative;
    z-index:1;
    color:#f4d03f;
    text-shadow:0 0 15px rgba(212,175,55,0.4);
}
.coupon-discount{
    font-size:36px;
    font-weight:700;
    margin:20px 0;
    position:relative;
    z-index:1;
    background:linear-gradient(135deg,#d4af37 0%,#f4d03f 100%);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    background-clip:text;
}
.coupon-info{
    font-size:14px;
    margin:10px 0;
    opacity:0.9;
    position:relative;
    z-index:1;
    color:rgba(255,255,255,0.8);
}
.coupon-company{
    font-size:13px;
    opacity:0.8;
    margin-top:14px;
    padding-top:14px;
    border-top:1px solid rgba(212,175,55,0.2);
    position:relative;
    z-index:1;
    color:rgba(255,255,255,0.7);
}
.coupon-expire{
    font-size:12px;
    opacity:0.7;
    margin-top:10px;
    position:relative;
    z-index:1;
    color:#d4af37;
}

/* Empty State */
.empty-state{
    text-align:center;
    padding:80px 20px;
}
.empty-icon{
    font-size:80px;
    margin-bottom:24px;
    opacity:0.3;
    filter:drop-shadow(0 0 20px rgba(212,175,55,0.2));
}
.empty-text{
    font-size:18px;
    color:rgba(255,255,255,0.5);
}

/* Responsive */
@media(max-width:768px){
    .hero-title{font-size:36px;}
    .search-card{padding:28px 24px;}
    .search-form{grid-template-columns:1fr;}
    .trip-details{grid-template-columns:1fr;}
    .section-title{font-size:30px;}
    .coupons-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>

<!-- Hero Section -->
<div class="hero-section">
    <div class="hero-content">
        <h1 class="hero-title">Otobüs Bileti Bul</h1>
        <p class="hero-subtitle">Türkiye'nin her yerine premium konforlu yolculuk deneyimi</p>
    </div>
</div>

<div class="container">
    <!-- Search Card -->
    <div class="search-card">
        <h2 class="search-title">Sefer Ara</h2>
        <form method="POST" class="search-form">
            <div class="form-group">
                <label class="form-label">Nereden</label>
                <select name="departure_city" class="form-select" required>
                    <option value="">Kalkış Şehri Seçin</option>
                    <?php foreach(getCities() as $city): ?>
                        <option value="<?= $city ?>" <?= ($depCity==$city)?'selected':'' ?>><?= $city ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Nereye</label>
                <select name="destination_city" class="form-select" required>
                    <option value="">Varış Şehri Seçin</option>
                    <?php foreach(getCities() as $city): ?>
                        <option value="<?= $city ?>" <?= ($destCity==$city)?'selected':'' ?>><?= $city ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tarih (Opsiyonel)</label>
                <input type="text" name="departure_date" class="form-input" placeholder="GG/AA/YYYY" value="<?= $depDate ? date('d/m/Y', strtotime($depDate)) : '' ?>">
            </div>
            <button type="submit" name="search_trip" class="btn-search">Sefer Ara</button>
        </form>
    </div>

    <!-- Trips Section -->
    <?php if($results): ?>
    <div class="section">
        <h2 class="section-title">Bulunan Seferler</h2>
        <p class="section-subtitle"><?= count($results) ?> premium sefer bulundu</p>
        
        <div class="trips-grid">
            <?php foreach($results as $trip): ?>
                <div class="trip-card" 
                     data-trip-id="<?= $trip['id'] ?>"
                     data-capacity="<?= $trip['capacity'] ?>"
                     data-price="<?= $trip['price'] ?>"
                     data-company="<?= htmlspecialchars($trip['company_name']) ?>"
                     data-departure="<?= htmlspecialchars($trip['departure_city']) ?>"
                     data-destination="<?= htmlspecialchars($trip['destination_city']) ?>"
                     data-departure-time="<?= date('d/m/Y H:i', strtotime($trip['departure_time'])) ?>"
                     data-arrival-time="<?= date('d/m/Y H:i', strtotime($trip['arrival_time'])) ?>">
                    
                    <div class="trip-header">
                        <div class="company-info">
                            <h3><?= htmlspecialchars($trip['company_name']) ?></h3>
                        </div>
                        <div class="price-badge"><?= htmlspecialchars($trip['price']) ?>₺</div>
                    </div>

                    <div class="trip-details">
                        <div class="detail-item">
                            <div class="detail-icon">🚌</div>
                            <div class="detail-content">
                                <div class="detail-label">Kalkış</div>
                                <div class="detail-value"><?= htmlspecialchars($trip['departure_city']) ?></div>
                                <div style="font-size:13px;color:rgba(255,255,255,0.5);margin-top:4px;">
                                    <?= date('d/m/Y H:i', strtotime($trip['departure_time'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">📍</div>
                            <div class="detail-content">
                                <div class="detail-label">Varış</div>
                                <div class="detail-value"><?= htmlspecialchars($trip['destination_city']) ?></div>
                                <div style="font-size:13px;color:rgba(255,255,255,0.5);margin-top:4px;">
                                    <?= date('d/m/Y H:i', strtotime($trip['arrival_time'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">👥</div>
                            <div class="detail-content">
                                <div class="detail-label">Kapasite</div>
                                <div class="detail-value"><?= htmlspecialchars($trip['capacity']) ?> Koltuk</div>
                            </div>
                        </div>
                    </div>

                    <?php if(isUser()): ?>
                        <button class="btn-select-seat seat-btn">Koltuk Seç</button>
                        <div class="seat-map"></div>
                        <div class="payment-section">
                            <div class="selected-info">
                                <div class="selected-info-text">Seçilen Koltuklar</div>
                                <div class="selected-seats-display">-</div>
                            </div>
                            <form method="POST" action="/bilet/bilet_al.php">
                                <input type="hidden" name="trip_id" value="<?= $trip['id'] ?>">
                                <input type="hidden" name="seats" class="selected-seats" value="">
                                <button type="submit" class="btn-payment">Ödemeye Geç</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php elseif($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="empty-state">
            <div class="empty-icon">🔍</div>
            <p class="empty-text">Aramanıza uygun sefer bulunamadı</p>
        </div>
    <?php endif; ?>

    <!-- Coupons Section -->
    <div class="section">
        <h2 class="section-title">Aktif Kuponlar</h2>
        <p class="section-subtitle">Premium indirim fırsatlarından yararlanın</p>
        
        <?php if(count($coupons) === 0): ?>
            <div class="empty-state">
                <div class="empty-icon">🎟️</div>
                <p class="empty-text">Şu anda aktif kupon bulunmuyor</p>
            </div>
        <?php else: ?>
            <div class="coupons-grid">
                <?php foreach($coupons as $c): ?>
                    <div class="coupon-card">
                        <div class="coupon-code"><?= htmlspecialchars($c['code']) ?></div>
                        <div class="coupon-discount">%<?= htmlspecialchars($c['discount']) ?> İndirim</div>
                        <div class="coupon-info">📊 Kullanım Limiti: <?= htmlspecialchars($c['usage_limit']) ?></div>
                        <div class="coupon-company">
                            🏢 <?= $c['company_id'] === null ? 'Tüm Firmalar' : htmlspecialchars($c['company_name'] ?? 'Bilinmiyor') ?>
                        </div>
                        <div class="coupon-expire">⏰ Son Kullanma: <?= date('d/m/Y', strtotime($c['expire_date'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const occupiedSeatsData = <?php echo json_encode($occupiedSeatsData); ?>;

function generateSeatLayout(capacity) {
    if (capacity === 30) {
        return [
            [3, 6, 9, 12, 15, 18, 21, 24, 27, 30],
            [2, 5, 8, 11, 14, 17, 20, 23, 26, 29],
            [null, null, null, null, null, null, null, null, null, null],
            ['driver',1 , 4, 7, 10, 13, 16, 19, 22, 25,28]
        ];
    } else if (capacity === 40) {
        return [
            [3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36, 40],
            [2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35, 39],
            [null,null, null, null, null, null, null, null, null, null, null, null, 38],
            ['driver',1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34, 37]
        ];
    }
    return [
        [3, 6, 9, 12, 15, 18, 21, 24, 27, 30],
        [2, 5, 8, 11, 14, 17, 20, 23, 26, 29],
        [null, null, null,null, null, null, null, null, null, null],
        ['driver', 1, 4, 7, 10, 13, 16, 19, 22, 25, 28]
    ];
}

document.querySelectorAll('.trip-card').forEach(card => {
    const btn = card.querySelector('.seat-btn');
    const map = card.querySelector('.seat-map');
    const paymentSection = card.querySelector('.payment-section');
    const seatsInput = card.querySelector('.selected-seats');
    const seatsDisplay = card.querySelector('.selected-seats-display');
    let selectedSeats = [];

    if (!btn) return;

    btn.addEventListener('click', () => {
        const isVisible = map.style.display === 'flex';
        
        if (isVisible) {
            map.style.display = 'none';
            paymentSection.style.display = 'none';
            selectedSeats = [];
            seatsInput.value = '';
            btn.innerHTML = '🪑 Koltuk Seç';
        } else {
            map.style.display = 'flex';
            map.innerHTML = '';
            selectedSeats = [];
            btn.innerHTML = '✕ Kapat';

            const tripId = card.getAttribute('data-trip-id');
            const capacity = parseInt(card.getAttribute('data-capacity')) || 30;
            const occupiedSeats = occupiedSeatsData[tripId] || [];

            const seatRows = generateSeatLayout(capacity);

            seatRows.forEach(row => {
                const rowDiv = document.createElement('div');
                rowDiv.className = 'seat-row';
                
                row.forEach(num => {
                    if (num === null) {
                        const empty = document.createElement('div');
                        empty.style.width = '48px';
                        empty.style.height = '48px';
                        rowDiv.appendChild(empty);
                        return;
                    }

                    if (num === 'driver') {
                        const driver = document.createElement('div');
                        driver.className = 'driver-icon';
                        
                        const eyes = document.createElement('div');
                        eyes.className = 'driver-eyes';
                        driver.appendChild(eyes);
                        
                        const body = document.createElement('div');
                        body.className = 'driver-body';
                        driver.appendChild(body);
                        
                        const tie = document.createElement('div');
                        tie.className = 'driver-tie';
                        driver.appendChild(tie);
                        
                        rowDiv.appendChild(driver);
                        return;
                    }

                    const seat = document.createElement('div');
                    seat.className = 'seat';
                    seat.textContent = num;
                    
                    if (occupiedSeats.includes(num.toString()) || occupiedSeats.includes(num)) {
                        seat.classList.add('occupied');
                    }

                    seat.addEventListener('click', () => {
                        if (seat.classList.contains('occupied')) return;
                        
                        if (seat.classList.contains('selected')) {
                            seat.classList.remove('selected');
                            selectedSeats = selectedSeats.filter(s => s !== num);
                        } else {
                            seat.classList.add('selected');
                            selectedSeats.push(num);
                        }
                        
                        if (selectedSeats.length > 0) {
                            paymentSection.style.display = 'block';
                            seatsInput.value = selectedSeats.join(',');
                            seatsDisplay.textContent = selectedSeats.sort((a, b) => a - b).join(', ');
                        } else {
                            paymentSection.style.display = 'none';
                            seatsInput.value = '';
                            seatsDisplay.textContent = '-';
                        }
                    });

                    rowDiv.appendChild(seat);
                });
                
                map.appendChild(rowDiv);
            });
        }
    });
});
</script>

</body>
</html>