import json
import os
import subprocess
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

TOKEN = os.environ.get("SERVICES_MANAGER_TOKEN", "")
PROJECT = os.environ.get("COMPOSE_PROJECT", "marketplace-analytic")
ALLOWED = {"netdata", "portainer", "adminer", "redisinsight"}
ACTIONS = {"start", "stop", "restart"}

def find_containers(service):
    result = subprocess.run(
        ["docker", "ps", "-a", "--filter", f"label=com.docker.compose.project={PROJECT}",
         "--filter", f"label=com.docker.compose.service={service}", "--format", "{{json .}}"],
        capture_output=True, text=True, check=False)
    return [json.loads(line) for line in result.stdout.splitlines() if line.strip()]

class Handler(BaseHTTPRequestHandler):
    def reply(self, code, payload):
        body = json.dumps(payload).encode()
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def authorized(self):
        return bool(TOKEN) and self.headers.get("Authorization") == f"Bearer {TOKEN}"

    def do_GET(self):
        if not self.authorized():
            return self.reply(401, {"message": "Unauthorized"})
        if self.path == "/health":
            return self.reply(200, {"status": "ok"})
        if self.path != "/status":
            return self.reply(404, {"message": "Not found"})
        services = {}
        for service in ALLOWED:
            containers = find_containers(service)
            if len(containers) == 1:
                item = containers[0]
                services[service] = {"status": item.get("State", "unknown"), "container": item.get("Names")}
            elif len(containers) > 1:
                services[service] = {"status": "ambiguous", "container_count": len(containers)}
            else:
                services[service] = {"status": "not-found"}
        return self.reply(200, {"services": services})

    def do_POST(self):
        if not self.authorized():
            return self.reply(401, {"message": "Unauthorized"})
        parts = self.path.strip("/").split("/")
        if len(parts) != 3 or parts[0] != "services":
            return self.reply(404, {"message": "Not found"})
        service, action = parts[1], parts[2]
        if service not in ALLOWED or action not in ACTIONS:
            return self.reply(404, {"message": "Not found"})
        containers = find_containers(service)
        if len(containers) != 1:
            return self.reply(409, {"message": "Service container is unavailable or ambiguous."})
        container = containers[0]["ID"]
        result = subprocess.run(["docker", action, container], capture_output=True, text=True, check=False)
        if result.returncode:
            return self.reply(500, {"message": result.stderr.strip() or "Docker operation failed"})
        return self.reply(200, {"service": service, "action": action, "status": "ok"})

    def log_message(self, fmt, *args):
        return

ThreadingHTTPServer(("0.0.0.0", 8080), Handler).serve_forever()
