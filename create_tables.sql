-- PTT veritabanını oluştur
CREATE DATABASE IF NOT EXISTS ptt_db CHARACTER SET utf8 COLLATE utf8_turkish_ci;
USE ptt_db;

-- Mevcut tabloları temizle
DROP TABLE IF EXISTS mahalleler;
DROP TABLE IF EXISTS ilceler;
DROP TABLE IF EXISTS iller;

-- İller tablosu
CREATE TABLE IF NOT EXISTS iller (
    il_kodu VARCHAR(10) PRIMARY KEY,
    il_adi VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- İlçeler tablosu
CREATE TABLE IF NOT EXISTS ilceler (
    ilce_kodu VARCHAR(10) PRIMARY KEY,
    il_kodu VARCHAR(10) NOT NULL,
    ilce_adi VARCHAR(100) NOT NULL,
    FOREIGN KEY (il_kodu) REFERENCES iller(il_kodu) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- Mahalleler tablosu
CREATE TABLE IF NOT EXISTS mahalleler (
    mahalle_kodu VARCHAR(10) PRIMARY KEY,
    ilce_kodu VARCHAR(10) NOT NULL,
    mahalle_adi VARCHAR(100) NOT NULL,
    posta_kodu VARCHAR(5) NOT NULL,
    FOREIGN KEY (ilce_kodu) REFERENCES ilceler(ilce_kodu) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_turkish_ci;

-- İndeksler
CREATE INDEX IF NOT EXISTS idx_il_kodu ON ilceler(il_kodu);
CREATE INDEX IF NOT EXISTS idx_ilce_kodu ON mahalleler(ilce_kodu);
