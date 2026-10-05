# Service Hub

The Service Hub at `/admin/services` is restricted to Super Admin users.

## Managed services

- Netdata
- Portainer
- Adminer
- Redis Insight

The UI exposes status plus Start, Stop, and Restart operations.

## Security architecture

Laravel never receives the Docker socket. Service operations are delegated to an internal service-manager container. Only that container mounts `/var/run/docker.sock`.

The manager accepts only:
- the four allowlisted services;
- `start`, `stop`, and `restart`;
- requests carrying the configured bearer token.

The manager is not published on a host port.

## Deployment

The service manager is intended for the server Compose stack only. Keep its overlay server-only if the production deployment uses a separate server Compose file.

Required server environment:

```env
SERVICES_MANAGER_URL=http://service-manager:8080
SERVICES_MANAGER_TOKEN=<long-random-secret>
```

The same token must be supplied to the manager container and Laravel.

After deployment, verify from the Services page that all four services report a status and that Start/Stop/Restart actions work.

Never expose the manager port publicly and never mount the Docker socket into Laravel.
