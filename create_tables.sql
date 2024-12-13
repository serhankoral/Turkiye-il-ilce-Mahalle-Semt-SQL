-- PTT veritabanını oluştur
CREATE DATABASE IF NOT EXISTS ptt_db CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE ptt_db;

-- İller tablosu
CREATE TABLE IF NOT EXISTS iller (
    il_kodu VARCHAR(10) PRIMARY KEY,
    il_adi VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- İlçeler tablosu
CREATE TABLE IF NOT EXISTS ilceler (
    ilce_kodu VARCHAR(10) PRIMARY KEY,
    il_kodu VARCHAR(10) NOT NULL,
    ilce_adi VARCHAR(100) NOT NULL,
    FOREIGN KEY (il_kodu) REFERENCES iller(il_kodu) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Mahalleler tablosu
CREATE TABLE IF NOT EXISTS mahalleler (
    mahalle_kodu VARCHAR(10) PRIMARY KEY,
    ilce_kodu VARCHAR(10) NOT NULL,
    mahalle_adi VARCHAR(100) NOT NULL,
    posta_kodu VARCHAR(5) NOT NULL,
    FOREIGN KEY (ilce_kodu) REFERENCES ilceler(ilce_kodu) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- İndeksler
CREATE INDEX idx_il_kodu ON iller(il_kodu);
CREATE INDEX idx_ilce_kodu ON ilceler(ilce_kodu);
CREATE INDEX idx_mahalle_kodu ON mahalleler(mahalle_kodu);
CREATE INDEX idx_posta_kodu ON mahalleler(posta_kodu);
