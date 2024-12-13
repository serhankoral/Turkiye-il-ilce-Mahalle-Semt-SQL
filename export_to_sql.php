<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

try {
    // JSON dosyasını oku
    $jsonData = file_get_contents('ptt_veriler.json');
    $data = json_decode($jsonData, true);
    
    if (!$data) {
        throw new Exception("JSON verisi okunamadı!");
    }
    
    // Veritabanı bağlantısı
    $db = new PDO("mysql:host=localhost;charset=utf8", "root", "");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("SET NAMES utf8");
    $db->exec("SET CHARACTER SET utf8");
    $db->exec("SET COLLATION_CONNECTION = 'utf8_turkish_ci'");
    
    // SQL dosyasını oku ve çalıştır
    $sql = file_get_contents('create_tables.sql');
    $db->exec($sql);
    
    // Veritabanını seç
    $db->exec("USE ptt_db");
    
    // İşlem sayacı
    $toplamKayit = 0;
    $islemSayaci = 0;
    
    // Toplam kayıt sayısını hesapla
    foreach ($data as $il) {
        $toplamKayit++; // il
        foreach ($il['ilceler'] as $ilce) {
            $toplamKayit++; // ilçe
            foreach ($ilce['mahalleler'] as $mahalle) {
                $toplamKayit++; // mahalle
            }
        }
    }
    
    // İlleri ekle
    $ilStmt = $db->prepare("INSERT IGNORE INTO iller (il_id, il_adi) VALUES (:il_id, :il_adi)");
    
    // İlçeleri ekle
    $ilceStmt = $db->prepare("INSERT IGNORE INTO ilceler (ilce_id, il_id, ilce_adi) VALUES (:ilce_id, :il_id, :ilce_adi)");
    
    // Mahalleleri ekle
    $mahalleStmt = $db->prepare("INSERT IGNORE INTO mahalleler (mahalle_id, ilce_id, mahalle_adi, posta_kodu) VALUES (:mahalle_id, :ilce_id, :mahalle_adi, :posta_kodu)");
    
    foreach ($data as $ilKodu => $il) {
        // İl ekle
        $ilStmt->execute([
            ':il_id' => $ilKodu,
            ':il_adi' => $il['il_adi']
        ]);
        
        $islemSayaci++;
        $yuzde = round(($islemSayaci / $toplamKayit) * 100, 1);
        echo "<script>
            document.getElementById('progress').style.width = '$yuzde%';
            document.getElementById('progress').innerText = '$yuzde%';
            document.getElementById('status').innerText = 'SQL: {$il['il_adi']} ili işleniyor...';
        </script>\n";
        flush();
        
        foreach ($il['ilceler'] as $ilceKodu => $ilce) {
            // İlçe ekle
            $ilceStmt->execute([
                ':ilce_id' => $ilceKodu,
                ':il_id' => $ilKodu,
                ':ilce_adi' => $ilce['ilce_adi']
            ]);
            
            $islemSayaci++;
            $yuzde = round(($islemSayaci / $toplamKayit) * 100, 1);
            echo "<script>
                document.getElementById('progress').style.width = '$yuzde%';
                document.getElementById('progress').innerText = '$yuzde%';
                document.getElementById('status').innerText = 'SQL: {$il['il_adi']} - {$ilce['ilce_adi']} ilçesi işleniyor...';
            </script>\n";
            flush();
            
            foreach ($ilce['mahalleler'] as $mahalleKodu => $mahalle) {
                // Mahalle ekle
                $mahalleStmt->execute([
                    ':mahalle_id' => $mahalleKodu,
                    ':ilce_id' => $ilceKodu,
                    ':mahalle_adi' => $mahalle['mahalle_adi'],
                    ':posta_kodu' => $mahalle['posta_kodu']
                ]);
                
                $islemSayaci++;
                $yuzde = round(($islemSayaci / $toplamKayit) * 100, 1);
                echo "<script>
                    document.getElementById('progress').style.width = '$yuzde%';
                    document.getElementById('progress').innerText = '$yuzde%';
                    document.getElementById('status').innerText = 'SQL: {$il['il_adi']} - {$ilce['ilce_adi']} - {$mahalle['mahalle_adi']} mahallesi işleniyor...';
                </script>\n";
                flush();
            }
        }
    }
    
    echo "<script>
        document.getElementById('status').innerText = 'SQL aktarımı tamamlandı!';
        document.getElementById('progress').style.width = '100%';
        document.getElementById('progress').innerText = '100%';
    </script>\n";
    flush();
    
    echo "\n✅ Veriler başarıyla SQL'e aktarıldı!\n";
    
} catch (Exception $e) {
    echo "\n❌ SQL Hatası: " . $e->getMessage() . "\n";
}
?>