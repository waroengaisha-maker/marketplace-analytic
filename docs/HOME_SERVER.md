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

## 4. Adminer Access

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

## 5. Repository

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

## 6. Deployment

### Workflow normal

Dari PC, commit dan push perubahan:

```bash
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

## 7. Docker Operations

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

## 8. MySQL Backup

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

## 9. Automatic Backup

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

## 10. MySQL Restore

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

## 11. Firewall

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

## 12. Reboot / Recovery

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

## 13. Server-Specific Files

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

## 14. Quick Reference

### SSH

```bash
ssh warungaisha@192.168.100.48
```

### Application

```text
http://192.168.100.48:8080
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

## 15. Operational Principles

1. Akses server menggunakan user `warungaisha`, bukan `root`.
2. Gunakan `sudo` hanya ketika membutuhkan privilege administrator.
3. Jangan menghapus Docker volumes tanpa memastikan backup tersedia.
4. Jangan menjalankan `docker compose down -v` pada server tanpa alasan dan backup yang valid.
5. Gunakan `./deploy.sh` untuk deployment normal.
6. Jangan melakukan force reset Git pada server tanpa memahami dampaknya.
7. Gunakan SSH tunnel untuk Adminer.
8. Pastikan backup database tersedia sebelum operasi database yang berisiko.
9. Jangan mengekspos MySQL, Redis, atau Adminer langsung ke LAN.
10. Jika server dipindahkan ke hardware baru, gunakan dokumen ini sebagai checklist migrasi.
