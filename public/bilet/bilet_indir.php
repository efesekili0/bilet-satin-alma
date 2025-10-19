<?php

ob_start();

session_start();
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';


if (!isset($_SESSION['user_id'])) {
    ob_end_clean(); 
    http_response_code(401);
    die("Bu işlem için giriş yapmalısınız!");
}


$ip = $_SERVER['REMOTE_ADDR'];
$cache_file = sys_get_temp_dir() . '/ticket_download_' . md5($ip);
if (file_exists($cache_file)) {
    $last_download = file_get_contents($cache_file);
    if (time() - $last_download < 5) {
        ob_end_clean();
        die("Çok hızlı işlem yapıyorsunuz. Lütfen bekleyin.");
    }
}
file_put_contents($cache_file, time());


$ticket_id = $_POST['ticket_id'] ?? '';

if (empty($ticket_id)) {
    ob_end_clean(); 
    http_response_code(400);
    die("Geçersiz bilet ID!");
}

try {
    
    $stmt = $pdo->prepare("
        SELECT
            tk.id AS ticket_id,
            tk.trip_id,
            tk.user_id,
            tk.total_price,
            tk.status,
            tk.created_at AS ticket_created,
            u.fullname,
            u.email,
            tr.departure_city,
            tr.destination_city,
            tr.departure_time,
            tr.arrival_time,
            tr.price AS trip_price,
            b.name AS company_name,
            GROUP_CONCAT(bs.seat_number, ', ') AS seat_numbers
        FROM Tickets tk
        JOIN User u ON tk.user_id = u.id
        JOIN Trips tr ON tk.trip_id = tr.id
        JOIN Bus_Company b ON tr.company_id = b.id
        LEFT JOIN Booked_Seats bs ON bs.ticket_id = tk.id
        WHERE tk.id = ? AND tk.user_id = ?
        GROUP BY tk.id
    ");
    $stmt->execute([$ticket_id, $_SESSION['user_id']]);
    $bilet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bilet) {
        ob_end_clean();
        http_response_code(404);
        die("Bilet bulunamadı veya erişim yetkiniz yok!");
    }

   
    $status_display = strtoupper($bilet['status'] ?? 'active');

    if ($status_display === 'ACTIVE' && strtotime($bilet['departure_time']) < time()) {
        $status_display = 'EXPIRED';
    }
    
  
    if ($status_display === 'CANCELED') {
        $status_display = 'İPTAL EDİLDİ';
    }

    
    function e($s) {
        return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    
    $departure_date = date('d.m.Y', strtotime($bilet['departure_time']));
    $departure_time_only = date('H:i', strtotime($bilet['departure_time']));
    $arrival_time_only = date('H:i', strtotime($bilet['arrival_time']));
    $download_date = date('d.m.Y H:i:s');


    ob_end_clean();

    
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Otobüs Bilet Sistemi');
    $pdf->SetAuthor('Otobüs Bilet Sistemi');
    $pdf->SetTitle('Otobüs Bileti - ' . $ticket_id);
    $pdf->SetSubject('Bilet Belgesi');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    $pdf->AddPage();
    $pdf->SetFont('dejavusans', '', 10);

    
    $html = '
        <style>
            .bilet-container { border:4px solid #c0392b; padding:15px; background-color:#ffffff; }
            .header { text-align:center; background-color:#c0392b; color:#ffffff; padding:15px; font-size:20px; font-weight:bold; margin-bottom:15px; }
            .company-name { text-align:center; font-size:16px; font-weight:bold; margin-bottom:15px; padding:10px; background-color:#f39c12; color:#ffffff; }
            .route-info { background-color:#ecf0f1; padding:15px; margin:15px 0; border-left:5px solid #3498db; font-size:16px; font-weight:bold; text-align:center; }
            .info-row { margin:8px 0; padding:8px; background-color:#f8f9fa; border-left:3px solid #95a5a6; }
            .label { color:#7f8c8d; font-weight:bold; width:130px; display:inline-block; }
            .value { color:#2c3e50; font-weight:bold; }
            .section { background-color:#e8f5e9; padding:12px; margin:15px 0; border:2px solid #27ae60; }
            .section-title { font-size:14px; color:#2c3e50; font-weight:bold; margin-bottom:8px; border-bottom:1px solid #bdc3c7; padding-bottom:3px; }
            .ticket-number { text-align:center; font-size:16px; color:#c0392b; font-weight:bold; margin-top:20px; padding:15px; background-color:#fff9c4; border:3px dashed #c0392b; }
            .status-badge { display:inline-block; padding:4px 12px; background-color:#27ae60; color:#ffffff; border-radius:3px; font-size:11px; font-weight:bold; }
            .warning { text-align:center; font-size:8px; color:#7f8c8d; margin-top:20px; padding-top:15px; border-top:2px solid #bdc3c7; line-height:1.5; }
            .watermark { text-align:right; font-size:7px; color:#95a5a6; margin-top:10px; }
        </style>
        <div class="bilet-container">
            <div class="header">🚌 OTOBÜS BİLETİ 🚌</div>
            <div class="company-name">' . e($bilet['company_name'] ?: 'Bilet Sistemi') . '</div>
            <div class="route-info">' . e($bilet['departure_city']) . ' → ' . e($bilet['destination_city']) . '</div>
            
            <div class="section">
                <div class="section-title">👤 YOLCU BİLGİLERİ</div>
                <div class="info-row"><span class="label">Ad Soyad:</span><span class="value">' . e($bilet['fullname']) . '</span></div>
                <div class="info-row"><span class="label">E-posta:</span><span class="value">' . e($bilet['email']) . '</span></div>
            </div>
            
            <div class="section" style="background-color:#fff3e0; border-color:#ff9800;">
                <div class="section-title">🚌 SEFER BİLGİLERİ</div>
                <div class="info-row"><span class="label">Tarih:</span><span class="value">' . e($departure_date) . '</span></div>
                <div class="info-row"><span class="label">Kalkış Saati:</span><span class="value">' . e($departure_time_only) . '</span></div>
                <div class="info-row"><span class="label">Varış Saati:</span><span class="value">' . e($arrival_time_only) . '</span></div>
                <div class="info-row"><span class="label">Koltuk No:</span><span class="value">' . e($bilet['seat_numbers'] ?: 'Belirtilmemiş') . '</span></div>
                <div class="info-row"><span class="label">Ücret:</span><span class="value">' . e(number_format($bilet['total_price'], 2)) . ' TL</span></div>
                <div class="info-row"><span class="label">Durum:</span><span class="value"><span class="status-badge">' . e($status_display) . '</span></span></div>
            </div>
            
            <div class="ticket-number">BİLET NO: ' . e($bilet['ticket_id']) . '<br></div>
            
            <div class="warning">
                ⚠ LÜTFEN DİKKAT ⚠<br>
                • Bu bilet geçerli bir seyahat belgesidir. Yolculuk sırasında yanınızda bulundurunuz.<br>
                • Kalkış saatinden 15 dakika önce terminalde bulunmanız gerekmektedir.<br>
                • İyi yolculuklar dileriz!
            </div>
            
            <div class="watermark">
                İndirilme Tarihi: ' . e($download_date) . ' | IP: ' . e(substr($ip, 0, -5) . 'xxx.xxx') . '
            </div>
        </div>
    ';

    
    $pdf->writeHTML($html, true, false, true, false, '');

    
    $safe_filename = 'bilet_' . preg_replace('/[^a-z0-9_-]/i', '', $ticket_id) . '.pdf';
    
    
    error_log("PDF İndirildi - Kullanıcı: {$_SESSION['user_id']}, Bilet: {$ticket_id}, IP: {$ip}");

    
    $pdf->Output($safe_filename, 'D');
    exit;

} catch (PDOException $e) {
    ob_end_clean(); 
    error_log("Bilet indirme hatası: " . $e->getMessage());
    http_response_code(500);
    die("Bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.");
} catch (Exception $e) {
    ob_end_clean(); 
    error_log("PDF oluşturma hatası: " . $e->getMessage());
    http_response_code(500);
    die("PDF oluşturulamadı. Lütfen teknik destek ile iletişime geçiniz.");
}