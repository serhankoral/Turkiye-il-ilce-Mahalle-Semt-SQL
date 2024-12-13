<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '256M');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PTT Posta Kodu Veri Çekme</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f0f0;
        }
        pre {
            font-family: Consolas, monospace;
            font-size: 14px;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
    <h2>PTT Posta Kodu Veri Çekme İşlemi</h2>
    <div class="progress-container">
        <div class="progress-bar" id="progress" style="width: 0%">0%</div>
    </div>
    <div class="status" id="status">İşlem başlatılıyor...</div>
    <pre id="output">
<?php

$baseUrl = 'https://postakodu.ptt.gov.tr';

// CURL bağlantısını yeniden kullanmak için global değişken
$ch = null;

function initCurl() {
    global $ch;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_NODELAY => 1,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            CURLOPT_COOKIEJAR => 'cookies.txt',
            CURLOPT_COOKIEFILE => 'cookies.txt',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: tr-TR,tr;q=0.9',
                'Cache-Control: no-cache',
                'Connection: keep-alive'
            ]
        ]);
    }
    return $ch;
}

// Önbellek sistemi
$cache = [];
function getCached($key) {
    global $cache;
    return isset($cache[$key]) ? $cache[$key] : null;
}

function setCached($key, $value) {
    global $cache;
    $cache[$key] = $value;
}

function makeRequest($url, $method = 'GET', $postData = null) {
    global $ch;
    $ch = initCurl();
    
    // Önbellekte var mı kontrol et
    $cacheKey = $method . $url . serialize($postData);
    $cachedResponse = getCached($cacheKey);
    if ($cachedResponse !== null) {
        return $cachedResponse;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }
    } else {
        curl_setopt($ch, CURLOPT_POST, false);
    }
    
    $response = curl_exec($ch);
    
    if(curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        throw new Exception("HTTP Hata Kodu: " . $httpCode);
    }
    
    // Yanıtı önbelleğe al
    setCached($cacheKey, $response);
    
    return $response;
}

try {
    echo "<span class='ozet'>PTT Posta Kodu Verileri Çekiliyor...</span>\n";
    echo "=====================================\n\n";
    
    $startTime = microtime(true);
    
    $response = makeRequest($baseUrl);
    
    if (!preg_match('/<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="([^"]+)"/', $response, $viewstate)) {
        throw new Exception("ViewState bulunamadı!");
    }
    if (!preg_match('/<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="([^"]+)"/', $response, $eventvalidation)) {
        throw new Exception("EventValidation bulunamadı!");
    }
    
    if (!preg_match('/<select[^>]*id="MainContent_DropDownList1"[^>]*>(.*?)<\/select>/s', $response, $selectMatch)) {
        throw new Exception("İl listesi bulunamadı!");
    }
    
    preg_match_all('/<option\s+value="([^"]+)"\s*>([^<]+)<\/option>/s', $selectMatch[1], $options, PREG_SET_ORDER);
    
    $tumVeriler = [];
    $ilSayaci = 0;
    $toplamIl = count(array_filter($options, function($opt) {
        return !empty($opt[1]) && $opt[1] != "-1";
    }));
    
    foreach ($options as $option) {
        $ilKodu = trim($option[1]);
        $ilAdi = trim($option[2]);
        
        if (!empty($ilKodu) && $ilKodu != "-1") {
            $ilSayaci++;
            $yuzde = round(($ilSayaci / $toplamIl) * 100, 1);
            
            // Progress bar güncelle
            echo "<script>
                document.getElementById('progress').style.width = '$yuzde%';
                document.getElementById('progress').innerText = '$yuzde%';
                document.getElementById('status').innerText = '$ilAdi işleniyor... ($ilSayaci/$toplamIl)';
            </script>";
            flush();
            ob_flush();
            
            $ilStartTime = microtime(true);
            echo "<span class='il'>[İL] $ilAdi</span> (İşlenen: $ilSayaci/$toplamIl - %$yuzde)\n";
            
            $postData = [
                '__EVENTTARGET' => 'ctl00$MainContent$DropDownList1',
                '__EVENTARGUMENT' => '',
                '__VIEWSTATE' => $viewstate[1],
                '__EVENTVALIDATION' => $eventvalidation[1],
                'ctl00$MainContent$DropDownList1' => $ilKodu
            ];
            
            $ilceResponse = makeRequest($baseUrl . '/Default.aspx', 'POST', $postData);
            
            if (!preg_match('/<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="([^"]+)"/', $ilceResponse, $newViewstate)) {
                throw new Exception("İlçe sayfasında ViewState bulunamadı!");
            }
            if (!preg_match('/<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="([^"]+)"/', $ilceResponse, $newEventvalidation)) {
                throw new Exception("İlçe sayfasında EventValidation bulunamadı!");
            }
            
            if (preg_match('/<select[^>]*id="MainContent_DropDownList2"[^>]*>(.*?)<\/select>/s', $ilceResponse, $ilceMatch)) {
                preg_match_all('/<option\s+value="([^"]+)"\s*>([^<]+)<\/option>/s', $ilceMatch[1], $ilceOptions, PREG_SET_ORDER);
                
                $ilceler = [];
                foreach ($ilceOptions as $ilceOption) {
                    $ilceKodu = trim($ilceOption[1]);
                    $ilceAdi = trim($ilceOption[2]);
                    
                    if (!empty($ilceKodu) && $ilceKodu != "-1") {
                        echo "    <span class='ilce'>[İLÇE] $ilceAdi</span>\n";
                        
                        $postData = [
                            '__EVENTTARGET' => 'ctl00$MainContent$DropDownList2',
                            '__EVENTARGUMENT' => '',
                            '__VIEWSTATE' => $newViewstate[1],
                            '__EVENTVALIDATION' => $newEventvalidation[1],
                            'ctl00$MainContent$DropDownList1' => $ilKodu,
                            'ctl00$MainContent$DropDownList2' => $ilceKodu
                        ];
                        
                        $mahalleResponse = makeRequest($baseUrl . '/Default.aspx', 'POST', $postData);
                        
                        if (!preg_match('/<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="([^"]+)"/', $mahalleResponse, $mahalleViewstate)) {
                            throw new Exception("Mahalle sayfasında ViewState bulunamadı!");
                        }
                        if (!preg_match('/<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="([^"]+)"/', $mahalleResponse, $mahalleEventvalidation)) {
                            throw new Exception("Mahalle sayfasında EventValidation bulunamadı!");
                        }
                        
                        if (preg_match('/<select[^>]*id="MainContent_DropDownList3"[^>]*>(.*?)<\/select>/s', $mahalleResponse, $mahalleMatch)) {
                            preg_match_all('/<option\s+value="([^"]+)"\s*>([^<]+)<\/option>/s', $mahalleMatch[1], $mahalleOptions, PREG_SET_ORDER);
                            
                            $mahalleler = [];
                            foreach ($mahalleOptions as $mahalleOption) {
                                $mahalleKodu = trim($mahalleOption[1]);
                                $mahalleAdi = trim($mahalleOption[2]);
                                
                                if (!empty($mahalleKodu) && $mahalleKodu != "-1") {
                                    $postData = [
                                        '__EVENTTARGET' => 'ctl00$MainContent$DropDownList3',
                                        '__EVENTARGUMENT' => '',
                                        '__VIEWSTATE' => $mahalleViewstate[1],
                                        '__EVENTVALIDATION' => $mahalleEventvalidation[1],
                                        'ctl00$MainContent$DropDownList1' => $ilKodu,
                                        'ctl00$MainContent$DropDownList2' => $ilceKodu,
                                        'ctl00$MainContent$DropDownList3' => $mahalleKodu
                                    ];
                                    
                                    $postaKoduResponse = makeRequest($baseUrl . '/Default.aspx', 'POST', $postData);
                                    
                                    if (preg_match('/<span[^>]*id="MainContent_Label1"[^>]*>(.*?)<\/span>/s', $postaKoduResponse, $postaKoduMatch)) {
                                        $postaKodu = trim($postaKoduMatch[1]);
                                        printf("        %-30s [POSTA KODU] %s\n", "<span class='mahalle'>[MAHALLE] $mahalleAdi</span>", $postaKodu);
                                        
                                        $mahalleler[$mahalleKodu] = [
                                            'mahalle_adi' => $mahalleAdi,
                                            'posta_kodu' => $postaKodu
                                        ];
                                    }
                                    
                                    usleep(25000); // 0.025 saniye bekleme
                                }
                            }
                            
                            if (!empty($mahalleler)) {
                                $ilceler[$ilceKodu] = [
                                    'ilce_adi' => $ilceAdi,
                                    'mahalleler' => $mahalleler
                                ];
                            }
                        }
                        
                        usleep(25000); // 0.025 saniye bekleme
                    }
                }
                
                if (!empty($ilceler)) {
                    $tumVeriler[$ilKodu] = [
                        'il_adi' => $ilAdi,
                        'ilceler' => $ilceler
                    ];
                }
            }
            
            usleep(25000); // 0.025 saniye bekleme
            
            // Her il için verileri kaydet
            $json = json_encode($tumVeriler, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents('ptt_veriler.json', $json);
            
            $ilEndTime = microtime(true);
            $ilSure = round($ilEndTime - $ilStartTime, 2);
            echo "\nVeriler kaydedildi: $ilAdi tamamlandı. (Süre: {$ilSure} saniye)\n";
            echo "Toplam İlerleme: $ilSayaci/$toplamIl il (%$yuzde)\n\n";
        }
    }
    
    // İstatistikler
    $toplamIlce = 0;
    $toplamMahalle = 0;
    
    foreach ($tumVeriler as $il) {
        foreach ($il['ilceler'] as $ilce) {
            $toplamIlce++;
            $toplamMahalle += count($ilce['mahalleler']);
        }
    }
    
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime, 2);
    
    echo "\n<span class='ozet'>=== ÖZET ===</span>\n";
    echo "Toplam $toplamIl il, $toplamIlce ilçe ve $toplamMahalle mahalle/posta kodu verisi kaydedildi.\n";
    echo "Toplam işlem süresi: $totalTime saniye\n";
    echo "Veriler 'ptt_veriler.json' dosyasına kaydedildi.\n";
    
    // SQL dönüşümünü başlat
    echo "\n<span class='ozet'>SQL dönüşümü başlatılıyor...</span>\n";
    include 'export_to_sql.php';
    
} catch (Exception $e) {
    echo "Hata oluştu: " . $e->getMessage() . "\n";
} finally {
    if ($ch !== null) {
        curl_close($ch);
    }
    if (file_exists('cookies.txt')) {
        unlink('cookies.txt');
    }
}
?>
    </pre>
    <script>
        // Otomatik scroll
        setInterval(function() {
            var pre = document.getElementById('output');
            pre.scrollTop = pre.scrollHeight;
        }, 100);
    </script>
</body>
</html>
