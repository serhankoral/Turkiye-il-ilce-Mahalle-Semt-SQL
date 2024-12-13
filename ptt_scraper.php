<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

gc_enable();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PTT Posta Kodu Veri Çekme</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f0f0f0; }
        pre { 
            font-family: Consolas, monospace; 
            font-size: 14px; 
            background-color: white; 
            padding: 20px; 
            border-radius: 5px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-height: 600px;
            overflow-y: auto;
        }
        .progress-container {
            width: 100%;
            background-color: #f0f0f0;
            padding: 3px;
            border-radius: 3px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,.2);
            margin: 10px 0;
        }
        .progress-bar {
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: #fff;
            text-align: center;
            background-color: #4CAF50;
            transition: width .6s ease;
            height: 20px;
            border-radius: 3px;
            width: 0%;
        }
        .status {
            margin: 10px 0;
            padding: 10px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .il { color: #006600; font-weight: bold; }
        .ilce { color: #990000; }
        .mahalle { color: #000099; }
        .ozet { color: #0066cc; font-weight: bold; }
    </style>
</head>
<body>
    <h2>PTT Posta Kodu Veri Çekme</h2>
    <div class="progress-container">
        <div class="progress-bar" id="progress" style="width: 0%">0%</div>
    </div>
    <div class="status" id="status">İşlem başlatılıyor...</div>
    <pre id="output">
<?php

function clearMemory() {
    if (gc_enabled()) {
        gc_collect_cycles();
    }
}

$baseUrl = 'https://postakodu.ptt.gov.tr';

$ch = null;

function initCurl() {
    global $ch;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, "");
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    }
    return $ch;
}

$cache = [];
function getCached($key) {
    global $cache;
    return isset($cache[$key]) ? $cache[$key] : null;
}

function setCached($key, $value) {
    global $cache;
    $cache[$key] = $value;
}

function getViewState($html) {
    if (preg_match('/<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return '';
}

function getViewStateGenerator($html) {
    if (preg_match('/<input type="hidden" name="__VIEWSTATEGENERATOR" id="__VIEWSTATEGENERATOR" value="([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return '';
}

function getEventValidation($html) {
    if (preg_match('/<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="([^"]+)"/', $html, $matches)) {
        return $matches[1];
    }
    return '';
}

function makeRequest($url, $postData = null) {
    global $ch;
    
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, "");
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    } else {
        curl_setopt($ch, CURLOPT_POST, false);
    }
    
    $response = curl_exec($ch);
    
    if ($response === false) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    
    // Debug: İlk isteğin yanıtını kontrol et
    if ($postData === null) {
        file_put_contents('debug_response.html', $response);
        echo "Debug: HTML yanıtı debug_response.html dosyasına kaydedildi.\n";
    }
    
    return $response;
}

function clearTables($pdo) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE mahalleler");
        $pdo->exec("TRUNCATE TABLE ilceler");
        $pdo->exec("TRUNCATE TABLE iller");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "✅ Tablolar başarıyla temizlendi.\n\n";
    } catch (PDOException $e) {
        throw new Exception("Tablolar temizlenirken hata oluştu: " . $e->getMessage());
    }
}

function saveIlData($pdo, $ilKodu, $ilData) {
    try {
        // İl ekle
        $stmt = $pdo->prepare("INSERT INTO iller (il_adi) VALUES (?) ON DUPLICATE KEY UPDATE il_id=LAST_INSERT_ID(il_id)");
        $stmt->execute([$ilData['il_adi']]);
        $ilId = $pdo->lastInsertId();
        
        // İlçeleri ekle
        foreach ($ilData['ilceler'] as $ilceKodu => $ilceData) {
            $stmt = $pdo->prepare("INSERT INTO ilceler (il_id, ilce_adi) VALUES (?, ?) ON DUPLICATE KEY UPDATE ilce_id=LAST_INSERT_ID(ilce_id)");
            $stmt->execute([$ilId, $ilceData['ilce_adi']]);
            $ilceId = $pdo->lastInsertId();
            
            // Mahalleleri ekle
            foreach ($ilceData['mahalleler'] as $mahalleKodu => $mahalleData) {
                $stmt = $pdo->prepare("INSERT INTO mahalleler (ilce_id, mahalle_adi, posta_kodu) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE mahalle_id=LAST_INSERT_ID(mahalle_id)");
                $stmt->execute([$ilceId, $mahalleData['mahalle_adi'], $mahalleData['posta_kodu']]);
            }
        }
    } catch (PDOException $e) {
        throw new Exception("Veri kaydedilirken hata oluştu: " . $e->getMessage());
    }
}

try {
    // Veritabanı bağlantısı
    $pdo = new PDO(
        "mysql:host=localhost;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    // create_tables.sql dosyasını çalıştır
    $sql = file_get_contents(__DIR__ . '/create_tables.sql');
    $pdo->exec($sql);
    
    // Veritabanını seç
    $pdo->exec("USE ptt_db");
    
    // Tabloları temizle
    clearTables($pdo);
    
    echo "<span class='ozet'>PTT Posta Kodu Verileri Çekiliyor...</span>\n";
    echo "=====================================\n\n";
    
    $startTime = microtime(true);
    
    // Ana sayfayı çek
    $response = makeRequest($baseUrl);
    
    // Debug: HTML içeriğini kontrol et
    echo "HTML içeriği kontrol ediliyor...\n";
    if (strpos($response, 'MainContent_DropDownList1') === false) {
        echo "UYARI: DropDownList1 bulunamadı. HTML yanıtı:\n";
        echo substr($response, 0, 500) . "...\n";
    }
    
    // İl listesini al (DropDownList1)
    $pattern = '/<select[^>]*?name=["\']ctl00\$MainContent\$DropDownList1["\'][^>]*>(.*?)<\/select>/si';
    if (!preg_match($pattern, $response, $selectMatch)) {
        throw new Exception("İl listesi bulunamadı! Lütfen debug_response.html dosyasını kontrol edin.");
    }
    
    // İlleri parse et
    preg_match_all('/<option\s+value="([^"]+)"[^>]*>(.*?)<\/option>/s', $selectMatch[1], $options, PREG_SET_ORDER);
    
    // Boş ve seçili olanları filtrele
    $options = array_filter($options, function($option) {
        return !empty($option[1]) && $option[1] != "-1";
    });
    
    $toplamIlceSayisi = 0;
    $tamamlananIlceSayisi = 0;
    
    echo "İl sayısı: " . count($options) . "\n\n";
    
    // Önce toplam ilçe sayısını hesapla
    foreach ($options as $option) {
        $ilKodu = trim($option[1]);
        $ilAdi = trim($option[2]);
        
        // İlçeleri al
        $postData = [
            '__EVENTTARGET' => 'ctl00$MainContent$DropDownList1',
            '__EVENTARGUMENT' => '',
            '__LASTFOCUS' => '',
            '__VIEWSTATE' => getViewState($response),
            '__VIEWSTATEGENERATOR' => getViewStateGenerator($response),
            '__EVENTVALIDATION' => getEventValidation($response),
            'ctl00$MainContent$DropDownList1' => $ilKodu
        ];
        
        $ilceResponse = makeRequest($baseUrl, $postData);
        
        // İlçe listesini parse et
        if (preg_match('/<select[^>]*name="ctl00\$MainContent\$DropDownList2"[^>]*>(.*?)<\/select>/s', $ilceResponse, $ilceSelectMatch)) {
            preg_match_all('/<option\s+value="([^"]+)"[^>]*>(.*?)<\/option>/s', $ilceSelectMatch[1], $ilceOptions, PREG_SET_ORDER);
            foreach ($ilceOptions as $ilceOption) {
                if (!empty($ilceOption[1]) && $ilceOption[1] != "-1") {
                    $toplamIlceSayisi++;
                }
            }
        }
        echo "  $ilAdi ili için ilçeler hesaplandı. (Toplam: $toplamIlceSayisi)\n";
    }
    
    if ($toplamIlceSayisi == 0) {
        throw new Exception("Hiç ilçe bulunamadı! Lütfen HTML yanıtını kontrol edin.");
    }
    
    echo "\nToplam $toplamIlceSayisi ilçe bulundu. Veri çekme işlemi başlıyor...\n\n";
    
    foreach ($options as $option) {
        $ilKodu = trim($option[1]);
        $ilAdi = trim($option[2]);
        
        echo "🏢 <span class='il'>$ilAdi</span> ili işleniyor...\n";
        $ilBaslangic = microtime(true);
        
        $ilData = ['il_adi' => $ilAdi, 'ilceler' => []];
        
        // İlçeleri al
        $postData = [
            '__EVENTTARGET' => 'ctl00$MainContent$DropDownList1',
            '__EVENTARGUMENT' => '',
            '__LASTFOCUS' => '',
            '__VIEWSTATE' => getViewState($response),
            '__VIEWSTATEGENERATOR' => getViewStateGenerator($response),
            '__EVENTVALIDATION' => getEventValidation($response),
            'ctl00$MainContent$DropDownList1' => $ilKodu
        ];
        
        $ilceResponse = makeRequest($baseUrl, $postData);
        
        // İlçe listesini parse et
        if (preg_match('/<select[^>]*name="ctl00\$MainContent\$DropDownList2"[^>]*>(.*?)<\/select>/s', $ilceResponse, $ilceSelectMatch)) {
            preg_match_all('/<option\s+value="([^"]+)"[^>]*>(.*?)<\/option>/s', $ilceSelectMatch[1], $ilceOptions, PREG_SET_ORDER);
            
            foreach ($ilceOptions as $ilceOption) {
                $ilceKodu = trim($ilceOption[1]);
                $ilceAdi = trim($ilceOption[2]);
                
                if (!empty($ilceKodu) && $ilceKodu != "-1") {
                    echo "  📍 <span class='ilce'>$ilceAdi</span> ilçesi işleniyor...\n";
                    
                    $ilData['ilceler'][$ilceKodu] = [
                        'ilce_adi' => $ilceAdi,
                        'mahalleler' => []
                    ];
                    
                    // Mahalleleri al
                    $postData = [
                        '__EVENTTARGET' => 'ctl00$MainContent$DropDownList2',
                        '__EVENTARGUMENT' => '',
                        '__LASTFOCUS' => '',
                        '__VIEWSTATE' => getViewState($ilceResponse),
                        '__VIEWSTATEGENERATOR' => getViewStateGenerator($ilceResponse),
                        '__EVENTVALIDATION' => getEventValidation($ilceResponse),
                        'ctl00$MainContent$DropDownList1' => $ilKodu,
                        'ctl00$MainContent$DropDownList2' => $ilceKodu
                    ];
                    
                    $mahalleResponse = makeRequest($baseUrl, $postData);
                    
                    // Mahalle listesini parse et
                    if (preg_match('/<select[^>]*name="ctl00\$MainContent\$DropDownList3"[^>]*>(.*?)<\/select>/s', $mahalleResponse, $mahalleSelectMatch)) {
                        preg_match_all('/<option\s+value="([^"]+)"[^>]*>(.*?)<\/option>/s', $mahalleSelectMatch[1], $mahalleOptions, PREG_SET_ORDER);
                        
                        foreach ($mahalleOptions as $mahalleOption) {
                            $mahalleKodu = trim($mahalleOption[1]);
                            $mahalleBilgi = trim($mahalleOption[2]);
                            
                            if (!empty($mahalleKodu) && $mahalleKodu != "-1") {
                                // Mahalle adı ve posta kodunu ayır (Örnek: "MAHALLE ADI / 34000")
                                if (preg_match('/^(.*?)\s*\/\s*(\d+)$/', $mahalleBilgi, $matches)) {
                                    $mahalleAdi = trim($matches[1]);
                                    $postaKodu = trim($matches[2]);
                                    
                                    echo "    🏠 <span class='mahalle'>$mahalleAdi</span> mahallesi: <span class='posta-kodu'>$postaKodu</span>\n";
                                    
                                    $ilData['ilceler'][$ilceKodu]['mahalleler'][$mahalleKodu] = [
                                        'mahalle_adi' => $mahalleAdi,
                                        'posta_kodu' => $postaKodu
                                    ];
                                }
                                
                                usleep(25000); // 0.025 saniye bekle
                                clearMemory();
                            }
                        }
                    }
                    
                    $tamamlananIlceSayisi++;
                    if ($toplamIlceSayisi > 0) { // Sıfıra bölme hatasını önle
                        $yuzde = round(($tamamlananIlceSayisi / $toplamIlceSayisi) * 100, 1);
                        echo "<script>
                            document.getElementById('progress').style.width = '$yuzde%';
                            document.getElementById('progress').innerText = '$yuzde%';
                            document.getElementById('status').innerText = '$ilAdi - $ilceAdi ilçesi tamamlandı ($tamamlananIlceSayisi/$toplamIlceSayisi)';
                        </script>\n";
                        flush();
                    }
                    
                    usleep(25000); // 0.025 saniye bekle
                    clearMemory();
                }
            }
        }
        
        // İl verilerini kaydet
        saveIlData($pdo, $ilKodu, $ilData);
        
        $ilSure = round(microtime(true) - $ilBaslangic, 2);
        echo "\n📊 $ilAdi ili tamamlandı (Süre: $ilSure saniye)\n\n";
        
        clearMemory();
    }
    
    $toplamSure = round(microtime(true) - $startTime, 2);
    echo "<span class='ozet'>✅ İşlem tamamlandı! (Toplam süre: $toplamSure saniye)</span>\n\n";
    
    echo "<script>
        document.getElementById('status').innerText = 'Veriler SQL\'e aktarılıyor...';
        document.getElementById('progress').style.width = '100%';
        document.getElementById('progress').innerText = '100%';
    </script>";
    flush();
    
    include 'export_to_sql.php';
    
} catch (Exception $e) {
    echo "❌ HATA: " . $e->getMessage() . "\n";
} finally {
    if ($ch !== null) {
        curl_close($ch);
    }
}

?>
    </pre>
    <script>
        setInterval(function() {
            var pre = document.getElementById('output');
            pre.scrollTop = pre.scrollHeight;
        }, 100);
    </script>
</body>
</html>
