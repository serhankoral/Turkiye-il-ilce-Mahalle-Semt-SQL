-- PTT veritabanını oluştur
CREATE DATABASE IF NOT EXISTS ptt_db CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE ptt_db;

-- Mevcut tabloları temizle
DROP TABLE IF EXISTS mahalleler;
DROP TABLE IF EXISTS ilceler;
DROP TABLE IF EXISTS iller;

-- İller tablosu
CREATE TABLE iller (
    id INT NOT NULL AUTO_INCREMENT,
    il_kodu INT NOT NULL,
    il_adi VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_il_kodu (il_kodu),
    UNIQUE KEY uk_il_adi (il_adi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- İlçeler tablosu
CREATE TABLE ilceler (
    id INT NOT NULL AUTO_INCREMENT,
    il_id INT NOT NULL,
    ilce_kodu VARCHAR(10) NOT NULL,
    ilce_adi VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_il_ilce (il_id, ilce_kodu),
    KEY fk_il_id (il_id),
    CONSTRAINT fk_ilce_il FOREIGN KEY (il_id) REFERENCES iller (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Mahalleler tablosu
CREATE TABLE mahalleler (
    id INT NOT NULL AUTO_INCREMENT,
    ilce_id INT NOT NULL,
    mahalle_kodu VARCHAR(20) NOT NULL,
    mahalle_adi VARCHAR(100) NOT NULL,
    posta_kodu VARCHAR(5),
    PRIMARY KEY (id),
    UNIQUE KEY uk_mahalle_kodu (mahalle_kodu),
    KEY fk_ilce_id (ilce_id),
    KEY idx_posta_kodu (posta_kodu),
    CONSTRAINT fk_mahalle_ilce FOREIGN KEY (ilce_id) REFERENCES ilceler (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
