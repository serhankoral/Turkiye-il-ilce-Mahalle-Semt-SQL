<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PTT Veri Aktarımı</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f0f0f0; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .progress-container { margin: 20px 0; background: #eee; padding: 3px; border-radius: 3px; }
        .progress-bar { height: 20px; background: #4CAF50; width: 0%; border-radius: 3px; transition: width .3s; }
        .status { margin: 10px 0; padding: 10px; border-left: 4px solid #4CAF50; }
        .error { color: red; border-left-color: red; }
        input, button { padding: 8px; margin: 5px 0; }
        button { background: #4CAF50; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h2>PTT Veri Aktarımı</h2>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $db = new PDO(
                    "mysql:host={$_POST['host']};charset=utf8mb4",
                    $_POST['username'],
                    $_POST['password'],
                    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                );
                
                // Veritabanını oluştur ve seç
                $db->exec("CREATE DATABASE IF NOT EXISTS {$_POST['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");
                $db->exec("USE {$_POST['database']}");
                
                // Tabloları oluştur
                $sql = file_get_contents('create_tables.sql');
                $db->exec($sql);
                
                // JSON verilerini oku
                $jsonData = file_get_contents('ptt_veriler.json');
                $data = json_decode($jsonData, true);
                
                if (!$data) {
                    throw new Exception("JSON verisi okunamadı!");
                }
                
                echo "<div class='progress-container'><div id='progress' class='progress-bar'></div></div>";
                echo "<div id='status' class='status'>İşlem başlıyor...</div>";
                
                // İlleri ekle
                $stmt = $db->prepare("INSERT INTO iller (il_kodu, il_adi) VALUES (?, ?)");
                $ilCount = count($data);
                $current = 0;
                
                foreach ($data as $ilKodu => $il) {
                    $stmt->execute([intval($ilKodu), $il['il_adi']]);
                    $current++;
                    $percent = ($current / $ilCount) * 33; // İlk %33
                    echo "<script>
                        document.getElementById('progress').style.width = '{$percent}%';
                        document.getElementById('status').innerHTML = 'İl ekleniyor: {$il['il_adi']}';
                    </script>";
                    flush();
                    usleep(100000); // 0.1 saniye bekle
                }
                
                // İlçeleri ekle
                $stmt = $db->prepare("INSERT INTO ilceler (il_id, ilce_kodu, ilce_adi) VALUES ((SELECT id FROM iller WHERE il_kodu = ?), ?, ?)");
                $ilceCount = 0;
                foreach ($data as $il) {
                    $ilceCount += count($il['ilceler']);
                }
                $current = 0;
                
                foreach ($data as $ilKodu => $il) {
                    foreach ($il['ilceler'] as $ilceKodu => $ilce) {
                        $uniqueIlceKodu = sprintf("%02d_%s", intval($ilKodu), $ilceKodu);
                        $stmt->execute([intval($ilKodu), $uniqueIlceKodu, $ilce['ilce_adi']]);
                        $current++;
                        $percent = 33 + ($current / $ilceCount) * 33; // %33-%66 arası
                        echo "<script>
                            document.getElementById('progress').style.width = '{$percent}%';
                            document.getElementById('status').innerHTML = 'İlçe ekleniyor: {$ilce['ilce_adi']}';
                        </script>";
                        flush();
                        usleep(50000); // 0.05 saniye bekle
                    }
                }
                
                // Mahalleleri ekle
                $stmt = $db->prepare("INSERT INTO mahalleler (ilce_id, mahalle_kodu, mahalle_adi, posta_kodu) VALUES ((SELECT id FROM ilceler WHERE ilce_kodu = ?), ?, ?, ?)");
                $mahalleCount = 0;
                foreach ($data as $il) {
                    foreach ($il['ilceler'] as $ilce) {
                        $mahalleCount += count($ilce['mahalleler']);
                    }
                }
                $current = 0;
                
                foreach ($data as $ilKodu => $il) {
                    foreach ($il['ilceler'] as $ilceKodu => $ilce) {
                        $uniqueIlceKodu = sprintf("%02d_%s", intval($ilKodu), $ilceKodu);
                        foreach ($ilce['mahalleler'] as $mahalleKodu => $mahalle) {
                            $uniqueMahalleKodu = sprintf("%02d_%s_%s", intval($ilKodu), $ilceKodu, $mahalleKodu);
                            $stmt->execute([
                                $uniqueIlceKodu,
                                $uniqueMahalleKodu,
                                $mahalle['mahalle_adi'],
                                $mahalle['posta_kodu']
                            ]);
                            $current++;
                            $percent = 66 + ($current / $mahalleCount) * 34; // %66-%100 arası
                            if ($current % 100 == 0) { // Her 100 mahallede bir güncelle
                                echo "<script>
                                    document.getElementById('progress').style.width = '{$percent}%';
                                    document.getElementById('status').innerHTML = 'Mahalle ekleniyor: {$mahalle['mahalle_adi']}';
                                </script>";
                                flush();
                            }
                            usleep(10000); // 0.01 saniye bekle
                        }
                    }
                }
                
                echo "<script>
                    document.getElementById('progress').style.width = '100%';
                    document.getElementById('status').innerHTML = 'İşlem tamamlandı!';
                </script>";
                
            } catch (Exception $e) {
                echo "<div class='status error'>Hata: " . $e->getMessage() . "</div>";
            }
        } else {
        ?>
        <form method="post">
            <div>
                <label>Veritabanı Sunucusu:</label><br>
                <input type="text" name="host" value="localhost" required>
            </div>
            <div>
                <label>Veritabanı Adı:</label><br>
                <input type="text" name="database" value="ptt_db" required>
            </div>
            <div>
                <label>Kullanıcı Adı:</label><br>
                <input type="text" name="username" value="root" required>
            </div>
            <div>
                <label>Şifre:</label><br>
                <input type="password" name="password">
            </div>
            <button type="submit">Veri Aktarımını Başlat</button>
        </form>
        <?php } ?>
    </div>
</body>
</html>