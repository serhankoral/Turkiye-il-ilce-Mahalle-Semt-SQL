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
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: tr-TR,tr;q=0.9',
                'Cache-Control: no-cache',
                'Connection: keep-alive',
                'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
            ]
        ]);
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
    $ch = initCurl();
    
    $cacheKey = $url . serialize($postData);
    $cachedResponse = getCached($cacheKey);
    if ($cachedResponse !== null) {
        return $cachedResponse;
    }
    
    curl_setopt($ch, CURLOPT_URL, $url);
    
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
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
    
    setCached($cacheKey, $response);
    return $response;
}

function saveIlData($ilKodu, $ilData) {
    $tempDir = 'temp_data';
    if (!file_exists($tempDir)) {
        mkdir($tempDir);
    }
    file_put_contents("$tempDir/il_$ilKodu.json", json_encode($ilData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function mergeIlData() {
    $tempDir = 'temp_data';
    $tumVeriler = [];
    
    if (!file_exists($tempDir)) {
        return $tumVeriler;
    }
    
    $files = glob("$tempDir/il_*.json");
    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        $ilKodu = basename($file, '.json');
        $ilKodu = substr($ilKodu, 3);
        $tumVeriler[$ilKodu] = $data;
        unlink($file);
    }
    
    // Klasör boş ise sil
    $remainingFiles = glob("$tempDir/*");
    if (empty($remainingFiles)) {
        rmdir($tempDir);
    }
    
    return $tumVeriler;
}

try {
    echo "<span class='ozet'>PTT Posta Kodu Verileri Çekiliyor...</span>\n";
    echo "=====================================\n\n";
    
    $startTime = microtime(true);
    
    $response = makeRequest($baseUrl);
    
    $viewState = getViewState($response);
    $viewStateGenerator = getViewStateGenerator($response);
    $eventValidation = getEventValidation($response);
    
    if (!preg_match('/<select[^>]*id="MainContent_DropDownList1"[^>]*>(.*?)<\/select>/s', $response, $selectMatch)) {
        throw new Exception("İl listesi bulunamadı!");
    }
    
    preg_match_all('/<option\s+value="([^"]+)"\s*>([^<]+)<\/option>/s', $selectMatch[1], $options, PREG_SET_ORDER);
    
    // Test modunu kaldır, tüm illeri al
    $options = array_filter($options, function($option) {
        return !empty($option[1]) && $option[1] != "-1";
    });
    
    // İlçe sayısını önceden hesapla
    $toplamIlceSayisi = 0;
    $tamamlananIlceSayisi = 0;
    
    echo "İlçe sayısı hesaplanıyor...\n";
    
    foreach ($options as $option) {
        $ilKodu = trim($option[1]);
        $ilAdi = trim($option[2]);
        
        // İlçeleri al
        $postData = [
            '__EVENTTARGET' => 'ctl00$MainContent$DropDownList1',
            '__EVENTARGUMENT' => '',
            '__LASTFOCUS' => '',
            '__VIEWSTATE' => $viewState,
            '__VIEWSTATEGENERATOR' => $viewStateGenerator,
            '__EVENTVALIDATION' => $eventValidation,
            'ctl00$MainContent$DropDownList1' => $ilKodu
        ];
        
        $ilceResponse = makeRequest($baseUrl, $postData);
        
        if (preg_match('/<select[^>]*id="MainContent_DropDownList2"[^>]*>(.*?)<\/select>/s', $ilceResponse, $ilceSelectMatch)) {
            preg_match_all('/<option\s+value="([^"]+)"\s*>([^<]+)<\/option>/s', $ilceSelectMatch[1], $ilceOptions, PREG_SET_ORDER);
            foreach ($ilceOptions as $ilceOption) {
                if (!empty($ilceOption[1]) && $ilceOption[1] != "-1") {
                    $toplamIlceSayisi++;
                }
            }
        }
        
        echo "  $ilAdi ili için ilçeler hesaplandı.\n";
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
            '__VIEWSTATE' => $viewState,
            '__VIEWSTATEGENERATOR' => $viewStateGenerator,
            '__EVENTVALIDATION' => $eventValidation,
            'ctl00$MainContent$DropDownList1' => $ilKodu
        ];
        
        $ilceResponse = makeRequest($baseUrl, $postData);
        $viewState = getViewState($ilceResponse);
        $viewStateGenerator = getViewStateGenerator($ilceResponse);
        $eventValidation = getEventValidation($ilceResponse);
        
        if (preg_match('/<select[^>]*id="MainContent_DropDownList2"[^>]*>(.*?)<\/select>/s', $ilceResponse, $ilceSelectMatch)) {
            preg_match_all('/<option\s+value="([^"]+)"\s*>([^<]+)<\/option>/s', $ilceSelectMatch[1], $ilceOptions, PREG_SET_ORDER);
            
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
                        '__VIEWSTATE' => $viewState,
                        '__VIEWSTATEGENERATOR' => $viewStateGenerator,
                        '__EVENTVALIDATION' => $eventValidation,
                        'ctl00$MainContent$DropDownList1' => $ilKodu,
                        'ctl00$MainContent$DropDownList2' => $ilceKodu
                    ];
                    
                    $mahalleResponse = makeRequest($baseUrl, $postData);
                    $viewState = getViewState($mahalleResponse);
                    $viewStateGenerator = getViewStateGenerator($mahalleResponse);
                    $eventValidation = getEventValidation($mahalleResponse);
                    
                    if (preg_match('/<select[^>]*id="MainContent_DropDownList3"[^>]*>(.*?)<\/select>/s', $mahalleResponse, $mahalleSelectMatch)) {
                        preg_match_all('/<option\s+value="([^"]+)"\s*>([^<]+)<\/option>/s', $mahalleSelectMatch[1], $mahalleOptions, PREG_SET_ORDER);
                        
                        foreach ($mahalleOptions as $mahalleOption) {
                            $mahalleKodu = trim($mahalleOption[1]);
                            $mahalleAdi = trim($mahalleOption[2]);
                            
                            if (!empty($mahalleKodu) && $mahalleKodu != "-1") {
                                // Posta kodunu al
                                $postData = [
                                    '__EVENTTARGET' => '',
                                    '__EVENTARGUMENT' => '',
                                    '__LASTFOCUS' => '',
                                    '__VIEWSTATE' => $viewState,
                                    '__VIEWSTATEGENERATOR' => $viewStateGenerator,
                                    '__EVENTVALIDATION' => $eventValidation,
                                    'ctl00$MainContent$DropDownList1' => $ilKodu,
                                    'ctl00$MainContent$DropDownList2' => $ilceKodu,
                                    'ctl00$MainContent$DropDownList3' => $mahalleKodu,
                                    'ctl00$MainContent$Button1' => 'Sorgula'
                                ];
                                
                                $postaKoduResponse = makeRequest($baseUrl, $postData);
                                
                                if (preg_match('/<span[^>]*id="MainContent_Label1"[^>]*>(.*?)<\/span>/s', $postaKoduResponse, $postaKoduMatch)) {
                                    $postaKodu = trim($postaKoduMatch[1]);
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
                    $yuzde = round(($tamamlananIlceSayisi / $toplamIlceSayisi) * 100, 1);
                    echo "<script>
                        document.getElementById('progress').style.width = '$yuzde%';
                        document.getElementById('progress').innerText = '$yuzde%';
                        document.getElementById('status').innerText = '$ilAdi - $ilceAdi ilçesi tamamlandı ($tamamlananIlceSayisi/$toplamIlceSayisi)';
                    </script>\n";
                    flush();
                    
                    usleep(25000); // 0.025 saniye bekle
                    clearMemory();
                }
            }
        }
        
        // İl verilerini kaydet
        saveIlData($ilKodu, $ilData);
        
        $ilSure = round(microtime(true) - $ilBaslangic, 2);
        echo "\n📊 $ilAdi ili tamamlandı (Süre: $ilSure saniye)\n\n";
        
        clearMemory();
    }
    
    $tumVeriler = mergeIlData();
    file_put_contents('ptt_veriler.json', json_encode($tumVeriler, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
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
