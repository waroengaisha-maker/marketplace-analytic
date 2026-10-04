# Service Hub

**Status: Production verified**

Service Hub menyediakan akses terpusat ke service administrasi dan monitoring server melalui Laravel dan Caddy.

## Scope

Service Hub hanya dapat diakses oleh **Super Admin** melalui:

`/admin/services`

Service yang tersedia:

| Service | Hostname | Fungsi | Risk |
|---|---|---|---|
| Netdata | `netdata.marketplace-analytics.my.id` | Host dan Docker monitoring | Medium |
| Portainer | `portainer.marketplace-analytics.my.id` | Docker/container management | Critical |
| Adminer | `adminer.marketplace-analytics.my.id` | MySQL administration | High |
| Redis Insight | `redisinsight.marketplace-analytics.my.id` | Redis inspection and administration | High |

## Architecture

```text
Internet
   │
   ▼
Cloudflare
   │
   ▼
Cloudflare Tunnel
   │
   ▼
Caddy :8080
   │
   ├── Basic Authentication
   │
   ├── netdata.marketplace-analytics.my.id
   │       └── netdata:19999
   │
   ├── portainer.marketplace-analytics.my.id
   │       └── https://portainer:9443
   │
   ├── adminer.marketplace-analytics.my.id
   │       └── adminer:8080
   │
   └── redisinsight.marketplace-analytics.my.id
           └── redisinsight:5540
```

Laravel Service Hub dan infrastructure authentication memiliki tanggung jawab berbeda:

- Laravel `/admin/services` memastikan hanya **Super Admin** yang dapat melihat Service Hub.
- Caddy Basic Auth melindungi service subdomain secara langsung.
- Service subdomain tidak bergantung pada authorization Laravel.

## Security Model

### Laravel

Route Service Hub:

```text
/admin/services
```

Authorization:

```text
Super Admin → allowed
Admin       → 403
User        → 403
Guest       → login
```

### Caddy

Seluruh service management hostname menggunakan Caddy Basic Authentication.

Unauthenticated request harus menghasilkan:

```text
401 Unauthorized
```

Authenticated request diteruskan ke service internal.

### Network exposure

Management service tidak dipublish langsung ke internet.

Host hanya mengekspos:

```text
0.0.0.0:8080 → Caddy
127.0.0.1:18081 → Adminer
```

Port berikut tidak dipublish ke host:

```text
Netdata       19999
Portainer     9443
Redis Insight 5540
MySQL         3306
Redis         6379
```

Dengan demikian, akses publik management service harus melewati:

```text
Cloudflare Tunnel → Caddy → Authentication → Service
```

## Service-specific Notes

### Netdata

Netdata digunakan untuk monitoring host dan container.

Target internal:

```text
netdata:19999
```

Netdata memiliki akses monitoring terhadap host dan Docker socket read-only.

### Portainer

Portainer digunakan untuk administrasi Docker.

Target internal:

```text
https://portainer:9443
```

Caddy menggunakan HTTPS upstream dengan certificate verification disabled karena Portainer menggunakan certificate internal.

Portainer adalah service dengan **risk tertinggi** karena memiliki akses ke Docker socket.

### Adminer

Adminer digunakan untuk administrasi database.

Target internal:

```text
adminer:8080
```

Adminer tidak lagi menggunakan akses publik langsung.

Host binding:

```text
127.0.0.1:18081
```

Akses publik menggunakan hostname Caddy:

```text
adminer.marketplace-analytics.my.id
```

### Redis Insight

Redis Insight digunakan untuk inspection dan administration Redis.

Target internal:

```text
redisinsight:5540
```

Tidak ada management port Redis Insight yang dipublish langsung ke internet.

## Cloudflare Tunnel

Published hostnames:

```text
marketplace-analytics.my.id
netdata.marketplace-analytics.my.id
portainer.marketplace-analytics.my.id
adminer.marketplace-analytics.my.id
redisinsight.marketplace-analytics.my.id
```

Semua hostname diarahkan ke:

```text
http://caddy:8080
```

Caddy kemudian menentukan upstream berdasarkan hostname.

## Deployment

Infrastructure Service Hub dijalankan menggunakan compose override khusus server:

```text
compose.yaml
compose.server.yaml
```

`compose.server.yaml` adalah konfigurasi server-only dan tidak menjadi bagian dari source deployment utama.

Deployment normal menggunakan:

```bash
./deploy.sh
```

Jangan mengandalkan manual `docker compose` sebagai prosedur deployment normal.

## Verification

### Container status

```bash
docker compose -f compose.yaml -f compose.server.yaml ps
```

Service berikut harus running:

```text
caddy
cloudflared
netdata
portainer
adminer
redisinsight
```

### Compose configuration

```bash
docker compose -f compose.yaml -f compose.server.yaml config >/dev/null
echo $?
```

Expected:

```text
0
```

Tidak boleh terdapat warning terkait interpolasi credential.

### Unauthenticated access

Setiap service harus menolak request tanpa credentials:

```bash
for host in \
  netdata.marketplace-analytics.my.id \
  portainer.marketplace-analytics.my.id \
  adminer.marketplace-analytics.my.id \
  redisinsight.marketplace-analytics.my.id
do
  curl -s -o /dev/null -w "$host %{http_code}\n" \
    "https://$host/"
done
```

Expected:

```text
401
401
401
401
```

### Authenticated access

Dengan credential yang valid:

```text
Netdata       → 200
Portainer     → 307
Adminer       → 200
Redis Insight → 200
```

Portainer `307` merupakan redirect normal dari Portainer dan bukan indikasi kegagalan routing.

### Host port exposure

Periksa:

```bash
sudo ss -lntup
```

Management service tidak boleh memiliki public listener langsung untuk:

```text
19999
5540
8000
9000
9443
3306
6379
```

## Security Checklist

- [x] Service Hub hanya Super Admin
- [x] Service subdomains menggunakan Caddy authentication
- [x] Cloudflare Tunnel digunakan sebagai public ingress
- [x] Tidak ada direct public management ports
- [x] Adminer tidak lagi menggunakan public management port
- [x] Portainer berada di belakang authentication
- [x] Redis Insight berada di belakang authentication
- [x] Netdata berada di belakang authentication
- [x] Caddy menjadi single management ingress
- [x] Docker Compose credential interpolation telah diperbaiki
- [x] Public unauthenticated requests menghasilkan `401`
- [x] Public authenticated requests berhasil
- [x] End-to-end routing telah diverifikasi

## Current Status

| Area | Status |
|---|---|
| Services UI | Ready |
| Super Admin authorization | Verified |
| Service catalog | Ready |
| Netdata Docker service | Verified |
| Portainer Docker service | Verified |
| Redis Insight Docker service | Verified |
| Adminer Caddy migration | Verified |
| Caddy authentication | Verified |
| Cloudflare routing | Verified |
| Network port exposure | Verified |
| End-to-end verification | Verified |

## Security Considerations

Portainer memiliki tingkat risiko tertinggi karena akses Docker socket pada dasarnya memberikan kemampuan administrasi host/container.

Adminer dan Redis Insight juga harus diperlakukan sebagai privileged administration tools karena dapat mengubah data aplikasi secara langsung.

Karena itu, credentials Caddy harus dianggap sebagai **server administration credentials** dan tidak boleh disimpan di repository atau dibagikan melalui chat/log.

Cloudflare Tunnel credentials dan Caddy authentication credentials harus disimpan hanya pada environment server.
