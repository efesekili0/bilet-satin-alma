
# VatanBilet 🚌

Otobüs bileti satış ve yönetim sistemi. PHP, SQLite ve Docker ile geliştirilmiştir.

## 🚀 Hızlı Başlangıç

### Gereksinimler
- Docker
- Docker Compose

### Kurulum

```bash
# 1. Repoyu klonlayın
git clone https://github.com/efesekili0/vatanbilet.git
cd vatanbilet

# 2. Docker Compose ile başlatın
docker-compose up -d --build

# 3. Tarayıcıdan açın
http://localhost:8080
```

## 👤 Varsayılan Admin Hesabı

Sistem hazır veritabanı ile gelir. İlk giriş için:

- **İsim:** vatanadmin
- **Email:** admin@info.com
- **Şifre:** 12341234

> ⚠️ **Not:** İlk girişten sonra şifrenizi değiştirmeniz önerilir.

## ✨ Özellikler

- ✅ Admin paneli
- ✅ Firma yönetimi
- ✅ Sefer ekleme/düzenleme/silme
- ✅ Bilet satın alma ve iptal
- ✅ Kupon sistemi
- ✅ Kullanıcı hesap yönetimi
- ✅ Hazır SQLite veritabanı

## 📁 Proje Yapısı

```
vatanbilet/
├── docker-compose.yml      # Docker yapılandırması
├── Dockerfile              # Container tanımı
├── data/
│   └── vatanbilet.db      # SQLite veritabanı (hazır verilerle)
└── public/
    ├── admin/             # Admin paneli
    ├── firma/             # Firma yönetimi
    ├── sefer/             # Sefer işlemleri
    ├── bilet/             # Bilet işlemleri
    └── kupon/             # Kupon sistemi
```

## 🛠️ Teknolojiler

- **Backend:** PHP 8.2
- **Web Server:** Apache
- **Veritabanı:** SQLite
- **Container:** Docker & Docker Compose


## 🔄 Veritabanını Sıfırlama

Eğer veritabanını başlangıç haline döndürmek isterseniz:

```bash
docker-compose down -v
docker-compose up -d --build
```

## 🛑 Durdurma

```bash
# Container'ları durdur
docker-compose down

# Container'ları durdur ve verileri sil
docker-compose down -v
```

## 📋 Notlar

- Proje geliştirme amaçlıdır
- Veritabanı dosyası (`data/vatanbilet.db`) proje ile birlikte gelir
- Port 8080 kullanılır, değiştirmek için `docker-compose.yml` düzenleyin

## 📄 Lisans

Bu proje eğitim amaçlı geliştirilmiştir.
