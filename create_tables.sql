-- PTT veritabanını oluştur
CREATE DATABASE IF NOT EXISTS ptt_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ptt_db;

-- Mevcut tabloları temizle
DROP TABLE IF EXISTS mahalleler;
DROP TABLE IF EXISTS ilceler;
DROP TABLE IF EXISTS iller;

-- İller tablosu
CREATE TABLE IF NOT EXISTS iller (
    il_id INT AUTO_INCREMENT PRIMARY KEY,
    il_adi VARCHAR(50) NOT NULL,
    UNIQUE KEY unique_il (il_adi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İlçeler tablosu
CREATE TABLE IF NOT EXISTS ilceler (
    ilce_id INT AUTO_INCREMENT PRIMARY KEY,
    il_id INT NOT NULL,
    ilce_adi VARCHAR(50) NOT NULL,
    FOREIGN KEY (il_id) REFERENCES iller(il_id) ON DELETE CASCADE,
    UNIQUE KEY unique_ilce (il_id, ilce_adi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mahalleler tablosu
CREATE TABLE IF NOT EXISTS mahalleler (
    mahalle_id INT AUTO_INCREMENT PRIMARY KEY,
    ilce_id INT NOT NULL,
    mahalle_adi VARCHAR(100) NOT NULL,
    posta_kodu VARCHAR(5) NOT NULL,
    FOREIGN KEY (ilce_id) REFERENCES ilceler(ilce_id) ON DELETE CASCADE,
    UNIQUE KEY unique_mahalle (ilce_id, mahalle_adi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İndeksler
CREATE INDEX IF NOT EXISTS idx_il_id ON ilceler(il_id);
CREATE INDEX IF NOT EXISTS idx_ilce_id ON mahalleler(ilce_id);
