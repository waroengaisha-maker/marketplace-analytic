# Marketplace Analytics — Telegram Home Server Control

Dokumentasi proposal untuk remote control Fedora home server melalui Telegram.

> **Status: Proposal / Review Required**
>
> Dokumen ini hanya mendokumentasikan rancangan. Belum ada implementasi Telegram bot.

## 1. Tujuan

Menyediakan control plane ringan melalui Telegram untuk operasi home server yang aman dan terkontrol, termasuk:

- melihat status dan health server;
- membangunkan Fedora melalui Wake-on-LAN (WoL);
- melakukan suspend;
- reboot dengan konfirmasi;
- memantau Docker dan service Marketplace Analytics;
- mengakses operasi terbatas Plane dan Marketplace Analytics;
- menerima notifikasi health/backup bila nanti diperlukan.

Telegram menjadi **control interface**, bukan pengganti SSH untuk administrasi penuh.

## 2. Kondisi Server Saat Ini

Fedora home server telah berhasil diuji dengan:

- Ethernet: `enp5s0`
- Ethernet IP: `192.168.100.49`
- Ethernet MAC: `f0:79:59:29:10:a0`
- Broadcast: `192.168.100.255`
- Wake-on-LAN: enabled (`Wake-on: g`)
- NetworkManager WoL: persistent (`wake-on-lan: magic`)
- `systemctl suspend` berhasil membiarkan server dibangunkan kembali melalui Magic Packet.
- Windows sudah memiliki workflow WoL dan SSH sebagai fallback/manual controller.

Wi-Fi tidak digunakan untuk WoWLAN karena hardware sebelumnya melaporkan `Operation not supported (-95)`.

## 3. Arsitektur yang Diusulkan

Telegram bot tidak boleh berjalan hanya di Fedora apabila Fedora dapat berada dalam kondisi suspend.

Arsitektur:

```text
                         Telegram
                            │
                            ▼
                  ┌──────────────────┐
                  │ Telegram Bot     │
                  │ Controller       │
                  └────────┬─────────┘
                           │
                 ┌─────────┴─────────┐
                 │                   │
           Fedora online       Fedora suspended
                 │                   │
              SSH/API              WoL
                 │                   │
                 └─────────┬─────────┘
                           ▼
                    Fedora Server
```

Controller harus berada pada perangkat yang tetap online ketika Fedora suspend.

### Kandidat controller

Prioritas evaluasi:

1. router/home gateway jika mendukung bot/WoL;
2. perangkat always-on ringan di LAN;
3. Windows PC jika memang selalu hidup;
4. service lain yang memang dirancang selalu online.

**Catatan:** Telegram sendiri tidak dapat membangunkan Fedora yang sedang suspend. Magic Packet harus dikirim oleh perangkat yang masih hidup di jaringan lokal.

## 4. Prinsip Keamanan

Bot **tidak boleh** menyediakan arbitrary shell execution seperti:

```text
/exec <command>
/shell <command>
```

Pesan Telegram tidak boleh diteruskan langsung ke shell atau `sudo`.

Sebagai gantinya:

```text
Telegram command
      ↓
Telegram user allowlist
      ↓
Command parser
      ↓
Predefined action
      ↓
Limited privilege
      ↓
Audit log
```

### Identity / allowlist

Bot harus menerima command hanya dari Telegram user/chat ID yang secara eksplisit dikonfigurasi.

Semua user lain harus ditolak.

Bot token disimpan sebagai secret/environment variable dan **tidak boleh** masuk Git.

## 5. Command Surface Awal

### Read-only

```text
/status
/health
/uptime
/disk
/memory
/docker
/containers
```

Contoh:

```text
🟢 Fedora Server

Uptime: 2h 31m
CPU: 8%
RAM: 6.2 / 16 GB
Disk: 42%
Docker: 8 containers
Network: Ethernet
IP: 192.168.100.49
```

### Power

```text
/wake
/suspend
/reboot
```

`/wake` mengirim WoL dan menunggu server kembali reachable.

`/suspend` menjalankan predefined suspend action.

`/reboot` sebaiknya memerlukan confirmation.

Contoh:

```text
⚠️ Reboot Fedora?

[ Confirm ] [ Cancel ]
```

### Docker

Tahap awal sebaiknya menggunakan command terstruktur, bukan arbitrary Docker CLI:

```text
/docker status
/docker restart <allowed-service>
/docker logs <allowed-service>
```

Service yang boleh dioperasikan harus berupa allowlist.

### Application

Operasi spesifik dapat ditambahkan setelah control plane dasar stabil:

```text
/marketplace status
/marketplace health
/plane status
```

## 6. Permission Model

| Operation | Policy |
|---|---|
| Status/health | Allowed |
| Wake | Allowed |
| Suspend | Allowed |
| Docker status | Allowed |
| Read logs | Allowed |
| Restart allowlisted service | Allowed dengan guard |
| Reboot | Confirmation required |
| Backup | Confirmation/guard |
| Restore database | Explicit confirmation |
| Delete data | Not exposed |
| Arbitrary shell | Not exposed |
| Finalize/post accounting action | Not exposed |

Untuk operasi privileged, gunakan sudoers yang sangat spesifik seperti konfigurasi suspend yang sudah dibuat, bukan memberikan passwordless sudo umum.

## 7. Relationship dengan SSH dan Tailscale

Telegram bot bukan pengganti SSH.

Gunakan:

- **Telegram** — quick operational control;
- **SSH** — full server administration;
- **Tailscale** — private remote access ketika berada di luar LAN;
- **WoL** — power-on/resume dari suspend.

Model akses:

```text
Quick action       → Telegram
Normal admin       → Tailscale + SSH
Server suspended   → Telegram → WoL
Complex diagnosis  → Tailscale + SSH
```

## 8. Auditability

Setiap command yang mengubah state server sebaiknya dicatat:

```text
timestamp
telegram_user_id
command
target
result
duration
```

Contoh:

```text
2026-10-05 14:20:11
user=123456789
command=/suspend
target=fedora
result=success
```

Log tidak boleh menyimpan bot token, SSH private key, password, atau secret lain.

## 9. Failure Handling

Bot harus membedakan:

- Magic Packet terkirim tetapi server tidak bangun;
- server reachable tetapi SSH gagal;
- SSH berhasil tetapi service unhealthy;
- command ditolak karena authorization;
- action gagal;
- timeout.

Contoh `/wake`:

```text
⚡ Sending Wake-on-LAN...

Waiting for Fedora...
✓ Network reachable
✓ SSH reachable
✓ Docker healthy

🟢 Fedora is online.
```

Jika gagal:

```text
🔴 Fedora did not become reachable within 60 seconds.

Check:
- Ethernet link
- Wake-on-LAN
- server power state
```

## 10. Controller Placement

Ini merupakan keputusan arsitektur yang masih perlu direview.

### Option A — Windows PC

**Kelebihan**
- sudah tersedia;
- sudah terbukti dapat mengirim WoL;
- SSH client tersedia.

**Kekurangan**
- tidak selalu hidup;
- Telegram control tidak tersedia ketika Windows mati.

### Option B — Always-on LAN controller

Contoh: Raspberry Pi, mini PC, router, atau perangkat Linux kecil.

**Kelebihan**
- cocok untuk always-on;
- WoL selalu tersedia;
- tidak tergantung workstation.

**Kekurangan**
- menambah perangkat/service yang harus dipelihara.

### Option C — Router

Jika router menyediakan WoL/API/script capability yang sesuai.

**Kelebihan**
- sudah selalu hidup;
- berada langsung di LAN.

**Kekurangan**
- kemampuan bergantung firmware/router;
- security model perlu diperiksa.

**Rekomendasi awal:** gunakan perangkat always-on yang ringan jika tersedia. Jangan menjadikan workstation sebagai dependency jangka panjang.

## 11. Deployment Model

Bot sebaiknya dijalankan sebagai service terisolasi, misalnya:

```text
telegram-controller
    │
    ├── Telegram API
    ├── authorization
    ├── predefined actions
    ├── WoL
    ├── SSH
    └── audit log
```

Implementasi dapat menggunakan container atau systemd service, tergantung lokasi controller.

Secret:

```text
TELEGRAM_BOT_TOKEN
TELEGRAM_ALLOWED_CHAT_IDS
```

harus berada di environment/secret store dan tidak di-commit.

## 12. Integrasi dengan Existing Server Operations

Bot sebaiknya memanggil **existing operational interfaces** daripada menduplikasi logic.

Contoh:

- server health → `./deploy/server-status.sh`
- deployment → `./deploy.sh`
- backup → `./deploy/backup-mysql.sh`
- restore → `./deploy/restore-mysql.sh` dengan confirmation
- Docker → predefined Compose actions
- power → systemd/NetworkManager/WoL

Prinsipnya:

> Telegram adalah interface tambahan untuk operational workflows yang sudah ada, bukan sumber logic kedua.

Ini mengurangi risiko perbedaan perilaku antara operasi manual dan Telegram.

## 13. Proposed Implementation Phases

### Phase 0 — Review

Belum melakukan implementasi.

Review:

- controller placement;
- command surface;
- security model;
- confirmation policy;
- logging;
- secrets management.

### Phase 1 — Read-only Bot

Implement:

```text
/status
/health
/uptime
```

### Phase 2 — Power Control

Implement:

```text
/wake
/suspend
```

Validasi end-to-end:

```text
Telegram → WoL → Fedora
Telegram → SSH → suspend
```

### Phase 3 — Docker / Application

Implement allowlisted operational actions:

```text
/docker status
/docker restart <service>
/marketplace health
/plane status
```

### Phase 4 — Notifications

Tambahkan notifikasi:

- server down;
- server recovered;
- backup failure;
- disk warning;
- Docker service unhealthy;
- deployment failure.

### Phase 5 — Advanced Operations

Hanya setelah security model matang:

- backup trigger;
- controlled deployment;
- maintenance mode;
- selected service restart;
- recovery workflows.

Database restore dan destructive operations tetap membutuhkan explicit confirmation dan sebaiknya tidak menjadi one-click operation.

## 14. Open Questions

Sebelum implementasi, review:

1. Perangkat mana yang akan menjadi controller always-on?
2. Apakah bot hanya untuk satu Telegram account?
3. Apakah command `/reboot` memerlukan confirmation?
4. Apakah Docker restart perlu tersedia?
5. Apakah backup trigger perlu tersedia?
6. Apakah notifikasi otomatis diperlukan?
7. Apakah controller perlu dapat diakses dari luar LAN melalui Telegram saja?
8. Apakah Tailscale tetap menjadi jalur administrasi utama?
9. Apakah Plane dan Marketplace Analytics perlu command khusus?

## 15. Non-Goals

Proposal ini **tidak** bertujuan:

- menggantikan SSH;
- menyediakan remote shell;
- menyediakan arbitrary Docker CLI;
- mengelola credential/secret melalui Telegram;
- menjalankan accounting mutation dari Telegram;
- membuka port inbound baru ke internet.

## 16. Status

**Proposal — awaiting review.**

Tidak ada perubahan implementasi yang diperlukan sampai arsitektur dan security model disetujui.
