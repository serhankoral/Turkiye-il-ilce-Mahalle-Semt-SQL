<?php
if (!isset($data)) { // Eğer ptt_scraper.php'den çağrılmadıysa
    // JSON dosyasını oku
    $jsonData = file_get_contents('ptt_veriler.json');
    $data = json_decode($jsonData, true);

    if ($data === null) {
        die("JSON verisi okunamadı!");
    }
    
    // HTML başlığı
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>PTT Veri SQL Dönüşümü</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background-color: #f0f0f0; }
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
        </style>
    </head>
    <body>
        <h2>PTT Veri SQL Dönüşümü</h2>
        <div class="progress-container">
            <div class="progress-bar" id="progress">0%</div>
        </div>
        <div class="status" id="status">SQL dönüşümü başlatılıyor...</div>
    ';
}

// SQL dosyası oluştur
$sqlFile = fopen('insert_data.sql', 'w');

// Karakter seti tanımla
fwrite($sqlFile, "SET NAMES utf8mb4;\n");
fwrite($sqlFile, "SET FOREIGN_KEY_CHECKS=0;\n\n");

// Tabloları temizle
fwrite($sqlFile, "-- Tabloları temizle\n");
fwrite($sqlFile, "DELETE FROM mahalleler;\n");
fwrite($sqlFile, "DELETE FROM ilceler;\n");
fwrite($sqlFile, "DELETE FROM iller;\n");
fwrite($sqlFile, "ALTER TABLE mahalleler AUTO_INCREMENT = 1;\n");
fwrite($sqlFile, "ALTER TABLE ilceler AUTO_INCREMENT = 1;\n");
fwrite($sqlFile, "ALTER TABLE iller AUTO_INCREMENT = 1;\n\n");

// İlleri ekle
$toplamIslem = count($data) * 3; // İl, ilçe ve mahalle işlemleri
$islemSayaci = 0;

echo "<script>document.getElementById('status').innerText = 'İller ekleniyor...';</script>";
flush();

fwrite($sqlFile, "-- İlleri ekle\n");
foreach ($data as $ilKodu => $il) {
    $ilAdi = addslashes($il['il_adi']);
    fwrite($sqlFile, "INSERT INTO iller (il_kodu, il_adi) VALUES ('$ilKodu', '$ilAdi');\n");
    $islemSayaci++;
    $yuzde = round(($islemSayaci / $toplamIslem) * 100, 1);
    echo "<script>
        document.getElementById('progress').style.width = '$yuzde%';
        document.getElementById('progress').innerText = '$yuzde%';
    </script>";
    flush();
}
fwrite($sqlFile, "\n");

// İlçeleri ekle
echo "<script>document.getElementById('status').innerText = 'İlçeler ekleniyor...';</script>";
flush();

fwrite($sqlFile, "-- İlçeleri ekle\n");
foreach ($data as $ilKodu => $il) {
    foreach ($il['ilceler'] as $ilceKodu => $ilce) {
        $ilceAdi = addslashes($ilce['ilce_adi']);
        fwrite($sqlFile, "INSERT INTO ilceler (ilce_kodu, il_kodu, ilce_adi) VALUES ('$ilceKodu', '$ilKodu', '$ilceAdi');\n");
    }
    $islemSayaci++;
    $yuzde = round(($islemSayaci / $toplamIslem) * 100, 1);
    echo "<script>
        document.getElementById('progress').style.width = '$yuzde%';
        document.getElementById('progress').innerText = '$yuzde%';
    </script>";
    flush();
}
fwrite($sqlFile, "\n");

// Mahalleleri ekle
echo "<script>document.getElementById('status').innerText = 'Mahalleler ekleniyor...';</script>";
flush();

fwrite($sqlFile, "-- Mahalleleri ekle\n");
foreach ($data as $ilKodu => $il) {
    foreach ($il['ilceler'] as $ilceKodu => $ilce) {
        foreach ($ilce['mahalleler'] as $mahalleKodu => $mahalle) {
            $mahalleAdi = addslashes($mahalle['mahalle_adi']);
            $postaKodu = $mahalle['posta_kodu'];
            fwrite($sqlFile, "INSERT INTO mahalleler (mahalle_kodu, ilce_kodu, mahalle_adi, posta_kodu) VALUES ('$mahalleKodu', '$ilceKodu', '$mahalleAdi', '$postaKodu');\n");
        }
    }
    $islemSayaci++;
    $yuzde = round(($islemSayaci / $toplamIslem) * 100, 1);
    echo "<script>
        document.getElementById('progress').style.width = '$yuzde%';
        document.getElementById('progress').innerText = '$yuzde%';
    </script>";
    flush();
}

// Foreign key kontrollerini geri aç
fwrite($sqlFile, "\nSET FOREIGN_KEY_CHECKS=1;\n");

fclose($sqlFile);

// İstatistikler
$ilSayisi = count($data);
$ilceSayisi = 0;
$mahalleSayisi = 0;

foreach ($data as $il) {
    $ilceSayisi += count($il['ilceler']);
    foreach ($il['ilceler'] as $ilce) {
        $mahalleSayisi += count($ilce['mahalleler']);
    }
}

echo "<script>document.getElementById('status').innerText = 'SQL dönüşümü tamamlandı!';</script>";
echo "<p>SQL dosyası başarıyla oluşturuldu: insert_data.sql</p>";
echo "<p>Toplam $ilSayisi il, $ilceSayisi ilçe ve $mahalleSayisi mahalle verisi SQL dosyasına yazıldı.</p>";

if (!isset($data)) { // Eğer ptt_scraper.php'den çağrılmadıysa
    echo '</body></html>';
}
