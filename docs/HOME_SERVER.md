# Marketplace Analytics — Home Server Operations

Dokumentasi operasional untuk menjalankan Marketplace Analytics pada Fedora home server.

## 1. Server

| Item | Value |
|---|---|
| OS | Fedora 44 Workstation |
| Hostname | `fedora` |
| LAN IP | `192.168.100.48` |
| SSH user | `warungaisha` |
| SSH port | `22` |
| Application | `http://192.168.100.48:8080` |
| Adminer | `127.0.0.1:18081` via SSH tunnel |

Docker dan container Marketplace Analytics dikonfigurasi untuk kembali berjalan setelah reboot.

## 2. SSH Access

Dari PC/WSL:

```bash
ssh warungaisha@192.168.100.48
```

SSH menggunakan public-key authentication. Password SSH dan root SSH login dinonaktifkan.

Konfigurasi SSH:

```text
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
PubkeyAuthentication yes
```

Untuk hak administrator, tetap login sebagai `warungaisha` lalu gunakan `sudo`:

```bash
sudo dnf update
sudo dnf install <package>
```

## 3. Application Access

Marketplace Analytics dapat diakses dari LAN:

```text
http://192.168.100.48:8080
```

Port `8080/tcp` dibuka oleh firewall Fedora untuk akses LAN.

## 4. Public HTTPS / Cloudflare Tunnel

Production public access menggunakan Cloudflare Tunnel, sehingga server tidak memerlukan port forwarding inbound dari internet.

| Item | Value |
|---|---|
| Public hostname | `marketplace-analytics.my.id` |
| Tunnel | `marketplace-analytics-home` |
| Service target | `http://caddy:8080` |
| Cloudflare connector | `cloudflared` container |
| Application URL | `https://marketplace-analytics.my.id` |

Alur request production:

```text
Internet
   │
   ▼
Cloudflare
   │ HTTPS
   ▼
cloudflared
   │
   ▼
Caddy :8080
   │
   ▼
Laravel
```

Cloudflare Tunnel dipilih karena WAN router berada di belakang CGNAT/private WAN, sehingga inbound port forwarding tidak digunakan.

### Production environment

Server `.env` harus memiliki:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://marketplace-analytics.my.id
TRUSTED_HOSTS=marketplace-analytics.my.id
```

Jangan commit file `.env` atau token Cloudflare Tunnel ke repository.

### Trusted proxy / host

Laravel mempercayai Docker network internal yang menjadi jalur proxy:

```php
$middleware->trustProxies(at: ['172.30.0.0/16']);

$middleware->trustHosts(at: [
    'marketplace-analytics.my.id',
]);
```

Konfigurasi ini berada di `bootstrap/app.php`.

`TRUSTED_PROXIES` tidak lagi dibaca dari server `.env`; proxy trust ditentukan secara eksplisit berdasarkan network Docker. `TRUSTED_HOSTS` tetap diperlukan di server karena production configuration memvalidasi bahwa trusted host telah dikonfigurasi.

### Health check deployment

Health check lokal pada `deploy.sh` tetap menggunakan:

```text
http://127.0.0.1:8080
```

tetapi mengirim:

```http
Host: marketplace-analytics.my.id
```

Hal ini penting karena Laravel hanya mempercayai hostname production tersebut. Request dengan `Host: 127.0.0.1:8080` sengaja menghasilkan `400 Bad Request` dan bukan indikasi aplikasi rusak.

Public verification:

```bash
curl -i https://marketplace-analytics.my.id/
```

Expected:

```text
HTTP/2 302
location: https://marketplace-analytics.my.id/login
```

Cookie session/XSRF production harus memiliki atribut `Secure`.

## 5. Adminer Access

Adminer tidak diekspos langsung ke LAN. Adminer hanya dipublish pada:

```text
127.0.0.1:18081
```

Untuk mengakses dari PC, buat SSH tunnel:

```bash
ssh -L 18081:127.0.0.1:18081 warungaisha@192.168.100.48
```

Biarkan terminal tersebut tetap terbuka, lalu buka:

```text
http://127.0.0.1:18081
```

Adminer login:

```text
System   : MySQL
Server   : mysql
Username : sail
Password : password
Database : laravel
```

Gunakan `mysql` sebagai Server, bukan `localhost`, karena Adminer berjalan sebagai container pada Docker network.

## 6. Repository

Project berada di server pada:

```text
/home/warungaisha/Dev/Projects/marketplace-analytic
```

Branch deployment:

```text
development
```

Server menggunakan:

```text
compose.yaml
compose.server.yaml
```

`compose.server.yaml` adalah konfigurasi khusus server dan tidak digunakan sebagai konfigurasi development utama.

### Source-of-truth dan pembagian peran

Workflow repository yang digunakan:

```text
WSL / PC
   │
   │ edit, test, commit, push
   ▼
GitHub (origin/development)
   │
   │ fetch + fast-forward melalui deploy.sh
   ▼
Fedora Home Server
   │
   └── runtime / Docker / Laravel / MySQL / Redis / Caddy
```

Prinsipnya:

- **WSL/PC adalah environment development dan tempat utama operasi Git.**
- **GitHub adalah source of truth untuk branch `development`.**
- **Fedora adalah deployment/runtime server, bukan tempat development utama.**
- Deployment normal ke Fedora dilakukan melalui SSH dari WSL/PC.
- Jangan melakukan `git pull` manual sebagai workflow deployment normal di Fedora; gunakan `./deploy.sh`.
- Perubahan source code sebaiknya dibuat dan diuji di WSL/PC, lalu di-commit dan push ke GitHub.
- Perubahan server-only seperti `compose.server.yaml` dan konfigurasi lokal server tetap berada di server dan dikelola sebagai konfigurasi runtime lokal.

## 7. Deployment

### Workflow normal

Dari WSL/PC, setelah perubahan siap:

```bash
git status
git add <files>
git commit -m "<message>"
git push origin development
```

Kemudian SSH ke server:

```bash
ssh warungaisha@192.168.100.48
cd ~/Dev/Projects/marketplace-analytic
./deploy.sh
```

Script deployment akan:

1. Memastikan branch adalah `development`.
2. Memastikan working tree server bersih.
3. Fetch `origin/development`.
4. Hanya melakukan fast-forward update.
5. Memvalidasi Docker Compose.
6. Menjalankan/update container.
7. Menginstal Composer dependencies jika `composer.lock` berubah.
8. Menginstal Node dependencies jika `package-lock.json` berubah.
9. Build frontend.
10. Menjalankan migration.
11. Menjalankan Laravel optimization.
12. Melakukan health check aplikasi.

Deployment menggunakan:

```bash
docker compose -f compose.yaml -f compose.server.yaml
```

Jika deployment ditolak, periksa `git status` dan jangan melakukan `git reset --hard` tanpa memahami dampaknya.

## 8. Server Status Script

Gunakan script ini sebagai **single-command operational health check** untuk server Marketplace Analytics:

```bash
cd ~/Dev/Projects/marketplace-analytic
./deploy/server-status.sh
```

Script memeriksa:

- informasi sistem: uptime, load, memory, dan penggunaan disk root;
- Docker Compose configuration;
- status container `caddy`, `laravel.test`, `mysql`, `redis`, dan `adminer`;
- health status MySQL dan Redis;
- HTTP application health check;
- backup timer systemd;
- backup MySQL terbaru dan integritas gzip;
- SMART health `/dev/sda`.

### Exit status

Script menggunakan exit code untuk automation:

| Exit code | Status | Arti |
|---|---|---|
| `0` | OK/WARN | Tidak ada failure. Warning masih memungkinkan. |
| `1` | FAIL | Ada minimal satu failure. |

Contoh penggunaan:

```bash
./deploy/server-status.sh
echo $?
```

Untuk diagnosis, jalankan langsung dari project directory agar konfigurasi server Compose dan backup path ditemukan dengan benar.

> Catatan: health check aplikasi pada script menggunakan `http://127.0.0.1:8080`. Untuk pengecekan public HTTPS/Cloudflare, gunakan `curl -i https://marketplace-analytics.my.id/` secara terpisah.

### Server Dashboard snapshot

Super Admin memiliki read-only **Server Dashboard** di `/admin/server`.

Dashboard tidak menjalankan arbitrary shell command dari browser. Laravel hanya membaca snapshot JSON `storage/app/server-status.json` yang dibuat oleh host.

Snapshot menggunakan schema version `1` dan dianggap stale setelah 120 detik secara default. Browser melakukan refresh data setiap 30 detik.

Pasang systemd timer pada server:

```bash
cd ~/Dev/Projects/marketplace-analytic
bash ./deploy/install-server-status-timer.sh
```

Verifikasi:

```bash
systemctl status marketplace-analytics-server-status.timer
systemctl status marketplace-analytics-server-status.service
systemctl list-timers marketplace-analytics-server-status.timer
```

Jalankan snapshot manual:

```bash
cd ~/Dev/Projects/marketplace-analytic
./deploy/server-status.sh
cat storage/app/server-status.json
```

Konfigurasi opsional: `SERVER_STATUS_SNAPSHOT_PATH` dan `SERVER_STATUS_STALE_AFTER`.

Dashboard hanya tersedia untuk `super_admin`; `admin` biasa tidak memiliki akses server management.

## 9. Docker Operations

Masuk ke project:

```bash
cd ~/Dev/Projects/marketplace-analytic
```

Status:

```bash
docker compose -f compose.yaml -f compose.server.yaml ps
```

Log seluruh stack:

```bash
docker compose -f compose.yaml -f compose.server.yaml logs -f
```

Log service tertentu:

```bash
docker compose -f compose.yaml -f compose.server.yaml logs -f laravel.test
```

Restart service:

```bash
docker compose -f compose.yaml -f compose.server.yaml restart laravel.test
```

Jangan menjalankan perintah destruktif seperti berikut tanpa memastikan backup:

```bash
docker compose down -v
docker volume prune
```

Database MySQL berada pada Docker named volume dan berisi data aplikasi.

## 10. MySQL Backup

Backup database disimpan di:

```text
~/Backups/marketplace-analytic/
```

Format:

```text
mysql-YYYYMMDD-HHMMSS.sql.gz
```

### Backup manual

```bash
cd ~/Dev/Projects/marketplace-analytic
./deploy/backup-mysql.sh
```

Atau melalui systemd:

```bash
sudo systemctl start marketplace-analytics-backup.service
```

Cek hasil:

```bash
ls -lh ~/Backups/marketplace-analytic/
```

Validasi gzip:

```bash
gzip -t ~/Backups/marketplace-analytic/mysql-*.sql.gz
```

## 11. Automatic Backup

Backup otomatis menggunakan:

```text
marketplace-analytics-backup.timer
```

Cek status:

```bash
systemctl status marketplace-analytics-backup.timer
```

Lihat jadwal:

```bash
systemctl list-timers marketplace-analytics-backup.timer
```

Jadwal target sekitar pukul 03:00 dengan random delay maksimal 5 menit.

Backup lama dibersihkan dan sekitar 14 backup terbaru dipertahankan.

## 12. MySQL Restore

Restore akan mengubah database saat ini dan memerlukan konfirmasi eksplisit.

Contoh:

```bash
cd ~/Dev/Projects/marketplace-analytic

./deploy/restore-mysql.sh \
    ~/Backups/marketplace-analytic/mysql-20261004-190738.sql.gz
```

Script meminta:

```text
Type RESTORE to continue:
```

Ketik `RESTORE` hanya jika memang ingin melakukan restore.

Sebelum restore, validasi file:

```bash
gzip -t ~/Backups/marketplace-analytic/mysql-20261004-190738.sql.gz
```

## 13. Firewall

Firewall Fedora menggunakan `firewalld`.

Interface LAN:

```text
wlp4s0
```

Port yang tersedia dari LAN:

```text
22/tcp    SSH
8080/tcp  Marketplace Analytics
```

Port internal tidak diekspos:

```text
3306/tcp  MySQL
6379/tcp  Redis
18081/tcp Adminer
```

Verifikasi firewall:

```bash
sudo firewall-cmd --zone=FedoraWorkstation --list-all
```

Verifikasi dari PC:

```bash
nmap -Pn 192.168.100.48 -p 22,8080,18081,3306,6379
```

Expected:

```text
22/tcp      open
8080/tcp    open
18081/tcp   filtered
3306/tcp    filtered
6379/tcp    filtered
```

## 14. Reboot / Recovery

Docker harus enabled:

```bash
systemctl is-enabled docker
```

Expected:

```text
enabled
```

Setelah reboot, verifikasi:

```bash
docker ps

cd ~/Dev/Projects/marketplace-analytic
docker compose -f compose.yaml -f compose.server.yaml ps
```

SSH seharusnya kembali tersedia setelah Fedora boot. Laptop dikonfigurasi agar menutup lid tidak menyebabkan server suspend.

## 15. Server-Specific Files

Konfigurasi server lokal:

```text
compose.server.yaml
deploy.sh
```

Keduanya berada di `.git/info/exclude` pada server dan tidak dimaksudkan untuk menjadi konfigurasi development utama.

Backup scripts:

```text
deploy/backup-mysql.sh
deploy/restore-mysql.sh
```

Systemd:

```text
/etc/systemd/system/marketplace-analytics-backup.service
/etc/systemd/system/marketplace-analytics-backup.timer
```

SSH hardening:

```text
/etc/ssh/sshd_config.d/90-home-server-hardening.conf
```

## 16. Quick Reference

### SSH

```bash
ssh warungaisha@192.168.100.48
```

### Application

LAN:

```text
http://192.168.100.48:8080
```

Public production:

```text
https://marketplace-analytics.my.id
```

### Adminer

```bash
ssh -L 18081:127.0.0.1:18081 warungaisha@192.168.100.48
```

Browser:

```text
http://127.0.0.1:18081
```

### Deploy

```bash
ssh warungaisha@192.168.100.48
cd ~/Dev/Projects/marketplace-analytic
./deploy.sh
```

### Manual backup

```bash
cd ~/Dev/Projects/marketplace-analytic
./deploy/backup-mysql.sh
```

### List backups

```bash
ls -lh ~/Backups/marketplace-analytic/
```

### Restore

```bash
cd ~/Dev/Projects/marketplace-analytic
./deploy/restore-mysql.sh ~/Backups/marketplace-analytic/<backup>.sql.gz
```

### Docker status

```bash
cd ~/Dev/Projects/marketplace-analytic
docker compose -f compose.yaml -f compose.server.yaml ps
```

### Firewall

```bash
sudo firewall-cmd --zone=FedoraWorkstation --list-all
```

## 17. Operational Principles

1. Akses server menggunakan user `warungaisha`, bukan `root`.
2. Gunakan `sudo` hanya ketika membutuhkan privilege administrator.
3. Source code development dikerjakan di WSL/PC.
4. Commit dan push perubahan melalui WSL/PC ke `origin/development`.
5. Akses dan operasi server dilakukan melalui SSH dari WSL/PC.
6. Gunakan `./deploy.sh` untuk deployment normal; jangan `git pull` manual di server sebagai workflow deployment.
7. GitHub branch `development` adalah source of truth untuk source code yang dideploy.
8. Jangan menghapus Docker volumes tanpa memastikan backup tersedia.
9. Jangan menjalankan `docker compose down -v` pada server tanpa alasan dan backup yang valid.
10. Gunakan SSH tunnel untuk Adminer.
11. Pastikan backup database tersedia sebelum operasi database yang berisiko.
12. Jangan mengekspos MySQL, Redis, atau Adminer langsung ke LAN.
13. Jika server dipindahkan ke hardware baru, gunakan dokumen ini sebagai checklist migrasi.

## 18. Deployment Baseline — 2026-10-04

Production deployment dan Cloudflare HTTPS/proxy configuration diverifikasi berhasil pada commit:

```text
46e1234 fix: trust cloudflare proxy in production
```

Pada baseline ini:

- WSL, GitHub `origin/development`, dan Fedora server berada pada commit yang sama.
- `./deploy.sh` menyelesaikan deployment tanpa error.
- Docker services berjalan, MySQL berstatus healthy, dan Redis berjalan.
- Frontend production build berhasil.
- Laravel migration dan optimization berhasil.
- Local deployment health check berhasil setelah menggunakan trusted `Host` header.
- Public HTTPS berhasil melalui Cloudflare Tunnel dan mengarahkan unauthenticated request dari `/` ke `/login` dengan HTTPS.

Commit ini menjadi baseline operasional setelah penyelesaian konfigurasi production proxy/HTTPS.
