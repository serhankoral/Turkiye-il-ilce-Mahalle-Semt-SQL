<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>JSON Veri Kontrolü</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f0f0f0; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stats { margin: 20px 0; padding: 15px; background: #f8f8f8; border-radius: 5px; }
        .il { color: #006600; font-weight: bold; }
        .ilce { color: #990000; margin-left: 20px; }
        .mahalle { color: #000099; margin-left: 40px; }
        .error { color: red; padding: 10px; background: #fff0f0; border-left: 4px solid red; }
        .success { color: green; padding: 10px; background: #f0fff0; border-left: 4px solid green; }
        pre { background: #f8f8f8; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h2>JSON Veri Kontrolü</h2>
        <?php
        try {
            // JSON dosyasını oku
            $jsonData = file_get_contents('ptt_veriler.json');
            $data = json_decode($jsonData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON Hata: ' . json_last_error_msg());
            }

            // Genel istatistikler
            echo "<div class='stats'>";
            echo "<h3>Genel İstatistikler:</h3>";
            echo "Toplam İl Sayısı: " . count($data) . "<br>";
            
            $toplamIlce = 0;
            $toplamMahalle = 0;
            foreach ($data as $il) {
                if (isset($il['ilceler'])) {
                    $toplamIlce += count($il['ilceler']);
                    foreach ($il['ilceler'] as $ilce) {
                        if (isset($ilce['mahalleler'])) {
                            $toplamMahalle += count($ilce['mahalleler']);
                        }
                    }
                }
            }
            echo "Toplam İlçe Sayısı: " . $toplamIlce . "<br>";
            echo "Toplam Mahalle Sayısı: " . $toplamMahalle . "<br>";
            echo "</div>";

            // İlk il örneği
            $ilkIl = reset($data);
            echo "<h3>İlk İl Örneği:</h3>";
            echo "<div class='il'>İl Adı: " . $ilkIl['il_adi'] . "</div>";
            
            if (isset($ilkIl['ilceler'])) {
                $ilkIlce = reset($ilkIl['ilceler']);
                echo "<div class='ilce'>İlk İlçe: " . $ilkIlce['ilce_adi'] . "</div>";
                
                if (isset($ilkIlce['mahalleler'])) {
                    $ilkMahalle = reset($ilkIlce['mahalleler']);
                    echo "<div class='mahalle'>İlk Mahalle Örneği:</div>";
                    echo "<pre>" . json_encode($ilkMahalle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
                }
            }

            // Veri yapısı kontrolü
            echo "<h3>Veri Yapısı Kontrolü:</h3>";
            $hatalar = [];
            
            foreach ($data as $ilKodu => $il) {
                if (!isset($il['il_adi'])) {
                    $hatalar[] = "İl adı eksik: Kod = $ilKodu";
                }
                if (!isset($il['ilceler']) || !is_array($il['ilceler'])) {
                    $hatalar[] = "{$il['il_adi']} ili için ilçe verisi eksik veya hatalı";
                    continue;
                }
                
                foreach ($il['ilceler'] as $ilceKodu => $ilce) {
                    if (!isset($ilce['ilce_adi'])) {
                        $hatalar[] = "{$il['il_adi']} ili için ilçe adı eksik: Kod = $ilceKodu";
                    }
                    if (!isset($ilce['mahalleler']) || !is_array($ilce['mahalleler'])) {
                        $hatalar[] = "{$il['il_adi']} - {$ilce['ilce_adi']} için mahalle verisi eksik veya hatalı";
                        continue;
                    }
                    
                    foreach ($ilce['mahalleler'] as $mahalleKodu => $mahalle) {
                        if (!isset($mahalle['mahalle_adi'])) {
                            $hatalar[] = "{$il['il_adi']} - {$ilce['ilce_adi']} için mahalle adı eksik: Kod = $mahalleKodu";
                        }
                        if (!isset($mahalle['posta_kodu'])) {
                            $hatalar[] = "{$il['il_adi']} - {$ilce['ilce_adi']} - {$mahalle['mahalle_adi']} için posta kodu eksik";
                        }
                    }
                }
            }

            if (empty($hatalar)) {
                echo "<div class='success'>✅ Veri yapısı kontrol edildi. Herhangi bir hata bulunamadı.</div>";
            } else {
                echo "<div class='error'>❌ Veri yapısında hatalar bulundu:</div>";
                echo "<ul>";
                foreach ($hatalar as $hata) {
                    echo "<li>$hata</li>";
                }
                echo "</ul>";
            }

        } catch (Exception $e) {
            echo "<div class='error'>❌ " . $e->getMessage() . "</div>";
        }
        ?>
    </div>
</body>
</html>
?> 