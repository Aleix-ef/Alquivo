#!/usr/bin/env python3
"""Host watchdog. No user payloads, logs, secrets or exception text leave this process."""

import argparse
import datetime as dt
import fcntl
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import socket
import ssl
import subprocess
import sys
import time
import urllib.request
import uuid

SERVICES = ("backend", "worker", "scheduler", "web", "postgres", "clamav", "proxy")
PROBLEMS = {
    **{"container_" + name: ("Servicio no disponible: " + name + ".", False) for name in SERVICES},
    "docker": ("Docker no responde; no se puede comprobar la aplicación.", True),
    "disk_warning": ("Queda menos del 20 % del disco o menos de 10 GiB libres.", False),
    "disk_critical": ("Queda menos del 10 % del disco o menos de 5 GiB libres.", True),
    "inodes": ("Queda menos del 10 % de identificadores de archivo libres.", True),
    "https": ("La comprobación HTTPS local de aplicación/API no responde como se espera.", False),
    "certificate": ("El certificado TLS no se puede validar o caduca en menos de 14 días.", False),
    "app_probe": ("No se puede obtener el control operativo privado de Laravel.", False),
    "database": ("La consulta operativa de base de datos no se ha completado.", True),
    "queue_config": ("El monitor no cubre el controlador de cola configurado.", False),
    "queue_stuck": ("Hay trabajos disponibles sin completar desde hace más de 5 minutos.", False),
    "queue_failed": ("Se han registrado trabajos fallidos durante la última hora. Revisar sin reintentarlos automáticamente.", False),
    "scheduler": ("No consta ejecución del planificador durante los últimos 3 minutos.", False),
    "readiness": ("El control de preparación/seguridad de producción requiere revisión.", True),
    "antivirus": ("El antivirus no responde o sus firmas no se pueden comprobar.", False),
    "signatures": ("Las firmas del antivirus tienen más de 3 días o una fecha inválida.", False),
    "backup_timer": ("El temporizador de copia o el de vigilancia no está activo.", False),
    "backup_stale": ("No consta copia externa verificada durante las últimas 30 horas.", False),
    "backup_resume": ("La pausa de servicios para copia no se ha resuelto en 5 minutos.", True),
    "external_config": ("El destino privado de la señal externa no es válido.", False),
}
CONFIG = Path("/etc/alquivo/monitor.json")
STATE = Path("/var/lib/alquivo-monitor/state.json")
HEARTBEAT_FILE = Path("/opt/alquivo/shared/.monitor-heartbeat-url")
ENV_FILE = Path("/opt/alquivo/shared/.env.production")
HOST = "app.alquivo.com"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def request(url, *, body=None, headers=None):
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), NoRedirect())
    req = urllib.request.Request(url, data=body, headers=headers or {})
    with opener.open(req, timeout=12) as response:
        return response.status, response.read(1024)


def run(args, timeout=15):
    result = subprocess.run(args, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                            stderr=subprocess.DEVNULL, timeout=timeout, check=True)
    if len(result.stdout) > 32768:
        raise ValueError("Oversized probe")
    return result.stdout.decode("utf-8", errors="strict").strip()


def heartbeat_url(path=HEARTBEAT_FILE):
    value = path.read_text().strip() if path.exists() else ""
    if value and not re.fullmatch(r"https://hc-ping\.com/[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}", value):
        raise ValueError("Invalid heartbeat destination")
    return value


def mail_settings(path=ENV_FILE):
    if path.stat().st_mode & 0o077:
        raise ValueError("Insecure notification environment")
    settings = {}
    with path.open() as source:
        for line in source:
            name, separator, value = line.strip().partition("=")
            if separator and name in ("RESEND_API_KEY", "MAIL_FROM_ADDRESS"):
                parsed = shlex.split(value, comments=True)
                if len(parsed) != 1 or name in settings:
                    raise ValueError("Invalid notification setting")
                settings[name] = parsed[0]
    if not re.fullmatch(r"[A-Za-z0-9_.=-]{10,256}", settings.get("RESEND_API_KEY", "")):
        raise ValueError("Missing notification key")
    if not valid_email(settings.get("MAIL_FROM_ADDRESS", "")):
        raise ValueError("Invalid sender")
    return settings


def valid_email(value):
    return isinstance(value, str) and len(value) <= 254 and re.fullmatch(r"[A-Za-z0-9._+%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}", value)


def notify(recipient, notification, test=False):
    if not valid_email(recipient):
        raise ValueError("Invalid recipient")
    settings = mail_settings()
    active, resolved = notification["active"], notification["resolved"]
    subject = "Prueba de avisos operativos de Alquivo" if test else (
        "Alquivo: requiere atención" if active else "Alquivo: servicio recuperado")
    lines = ["Prueba técnica: no existe una avería simulada en producción."] if test else [
        "Comprobación: " + dt.datetime.fromtimestamp(notification["created"], dt.timezone.utc).isoformat(),
        "", "Requiere atención:" if active else "Se han recuperado los controles notificados.",
        *("- " + PROBLEMS[code][0] for code in active),
        *("- Recuperado: " + PROBLEMS[code][0] for code in resolved),
    ]
    lines += ["", "Revisa el VPS y el procedimiento de avisos operativos. No se han reiniciado servicios ni reintentado trabajos automáticamente.",
              "Este correo no incluye datos de clientes, documentos, conversaciones, contraseñas ni registros de errores."]
    body = json.dumps({"from": "Alquivo <" + settings["MAIL_FROM_ADDRESS"] + ">", "to": [recipient],
                       "subject": subject, "text": "\n".join(lines)}).encode()
    status, _ = request("https://api.resend.com/emails", body=body, headers={
        "Authorization": "Bearer " + settings["RESEND_API_KEY"], "Content-Type": "application/json",
        "Idempotency-Key": "alquivo-monitor-" + notification["key"],
    })
    return 200 <= status < 300


def metric_problems(metrics, now):
    expected = {"version", "checked_at", "database_ok", "queue_supported", "queued_ready", "oldest_ready_age_seconds",
                "failed_last_hour", "scheduler_last_tick", "scheduler_shared_cache", "production_ready"}
    if not isinstance(metrics, dict) or set(metrics) != expected or type(metrics["version"]) is not int or metrics["version"] != 1:
        return {"app_probe"}
    for key in ("database_ok", "queue_supported", "scheduler_shared_cache", "production_ready"):
        if type(metrics[key]) is not bool:
            return {"app_probe"}
    for key in ("checked_at", "queued_ready", "oldest_ready_age_seconds", "failed_last_hour", "scheduler_last_tick"):
        if metrics[key] is not None and (type(metrics[key]) is not int or not 0 <= metrics[key] < 2**53):
            return {"app_probe"}
    if metrics["checked_at"] is None or abs(metrics["checked_at"] - now) > 60:
        return {"app_probe"}
    issues = set()
    if not metrics["database_ok"]:
        issues.add("database")
    else:
        if not metrics["queue_supported"]:
            issues.add("queue_config")
        elif any(metrics[key] is None for key in ("queued_ready", "oldest_ready_age_seconds", "failed_last_hour")):
            issues.add("app_probe")
        else:
            if metrics["oldest_ready_age_seconds"] > 300:
                issues.add("queue_stuck")
            if metrics["failed_last_hour"] > 0:
                issues.add("queue_failed")
        tick = metrics["scheduler_last_tick"]
        if not metrics["scheduler_shared_cache"] or tick is None or now - tick > 180 or tick > now + 60:
            issues.add("scheduler")
    if not metrics["production_ready"]:
        issues.add("readiness")
    return issues


def disk_problems(total, free, inodes, inodes_free):
    issues = set()
    percent = free / total
    if percent < .10 or free < 5 * 1024**3:
        issues.add("disk_critical")
    elif percent < .20 or free < 10 * 1024**3:
        issues.add("disk_warning")
    if inodes and inodes_free / inodes < .10:
        issues.add("inodes")
    return issues


def collect(now):
    usage = shutil.disk_usage("/")
    fs = os.statvfs("/")
    issues = disk_problems(usage.total, usage.free, fs.f_files, fs.f_favail)
    template = '{"running":{{.State.Running}},"health":{{if .State.Health}}{{json .State.Health.Status}}{{else}}null{{end}}}'
    for service in SERVICES:
        try:
            state = json.loads(run(["/usr/bin/docker", "inspect", "--format", template, "alquivo-production-" + service + "-1"], timeout=4))
            if not state["running"] or (state["health"] is not None and state["health"] != "healthy"):
                issues.add("container_" + service)
        except Exception:
            issues.add("container_" + service)
    try:
        run(["/usr/bin/docker", "info", "--format", "{{.ServerVersion}}"], timeout=4)
    except Exception:
        issues.add("docker")
    for path, expected in (("/up", "200"), ("/login", "200"), ("/api/v1/auth/me", "401")):
        try:
            status = run(["/usr/bin/curl", "--silent", "--noproxy", "*", "--max-time", "6", "--connect-timeout", "3",
                          "--resolve", HOST + ":443:127.0.0.1", "--output", "/dev/null", "--write-out", "%{http_code}",
                          "https://" + HOST + path], timeout=8)
            if status != expected:
                issues.add("https")
        except Exception:
            issues.add("https")
    try:
        with socket.create_connection(("127.0.0.1", 443), timeout=4) as raw:
            with ssl.create_default_context().wrap_socket(raw, server_hostname=HOST) as tls:
                expiry = ssl.cert_time_to_seconds(tls.getpeercert()["notAfter"])
        if expiry - now < 14 * 86400:
            issues.add("certificate")
    except Exception:
        issues.add("certificate")
    try:
        metrics = json.loads(run(["/usr/bin/docker", "exec", "alquivo-production-backend-1", "timeout", "12", "php", "artisan", "ops:health", "--no-ansi"], timeout=15))
        issues.update(metric_problems(metrics, now))
    except Exception:
        issues.add("app_probe")
    try:
        version = run(["/usr/bin/docker", "exec", "alquivo-production-clamav-1", "timeout", "4", "clamdscan", "--version"], timeout=6)
        date = version.split("/", 2)[2]
        epoch = dt.datetime.strptime(date, "%a %b %d %H:%M:%S %Y").replace(tzinfo=dt.timezone.utc).timestamp()
        if now - epoch > 3 * 86400 or epoch > now + 3600:
            issues.add("signatures")
    except Exception:
        issues.add("antivirus")
    for unit in ("alquivo-backup.timer", "alquivo-backup-watch.timer"):
        try:
            run(["/usr/bin/systemctl", "is-active", "--quiet", unit], timeout=3)
        except Exception:
            issues.add("backup_timer")
    base = Path("/opt/alquivo/shared/backups")
    try:
        age = now - (base / "last-success").stat().st_mtime
        if not 0 <= age <= 30 * 3600:
            issues.add("backup_stale")
    except OSError:
        issues.add("backup_stale")
    journal = base / "paused-services"
    if journal.exists() and now - journal.stat().st_mtime > 300:
        issues.add("backup_resume")
    return issues


def transition(state, observed, now):
    """Two bad samples (one for critical), two good samples; no per-poll emails."""
    if set(observed) - set(PROBLEMS):
        raise ValueError("Unknown operational code")
    counts = state.setdefault("counts", {})
    clears = state.setdefault("clears", {})
    active = set(state.get("active", []))
    for code in PROBLEMS:
        if code in observed:
            counts[code] = min(2, counts.get(code, 0) + 1)
            clears[code] = 0
            if counts[code] >= (1 if PROBLEMS[code][1] else 2):
                active.add(code)
        else:
            counts.pop(code, None)
            clears[code] = min(2, clears.get(code, 0) + 1)
            if clears[code] >= 2:
                active.discard(code)
    state["active"] = sorted(active)
    notified = state.get("notified", [])
    changed = state["active"] != notified
    reminder = bool(active) and now - state.get("last_sent", 0) >= 6 * 3600
    pending = state.get("pending")
    if changed or reminder:
        if not pending or pending["active"] != state["active"]:
            state["pending"] = {"key": uuid.uuid4().hex, "created": now, "active": state["active"],
                                "resolved": sorted(set(notified) - active)}
    else:
        state.pop("pending", None)
    return state


def save_state(state, path=None):
    path = STATE if path is None else path
    temp = path.with_suffix(".tmp")
    fd = os.open(temp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC | os.O_NOFOLLOW, 0o600)
    with os.fdopen(fd, "w") as target:
        json.dump(state, target)
        target.flush()
        os.fsync(target.fileno())
    os.replace(temp, path)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--check", action="store_true", help="Read-only checks: no mail, state changes or external pings")
    parser.add_argument("--test-email", action="store_true", help="One labelled technical email, no simulated outage")
    args = parser.parse_args()
    if args.check and args.test_email:
        raise ValueError("Conflicting modes")
    config = json.loads(CONFIG.read_text())
    if set(config) != {"recipient"} or not valid_email(config["recipient"]):
        raise ValueError("Invalid monitor configuration")
    now = int(time.time())
    if args.test_email:
        sent = notify(config["recipient"], {"active": [], "resolved": [], "created": now, "key": uuid.uuid4().hex}, test=True)
        print(json.dumps({"test_email_accepted": sent}))
        return 0 if sent else 1
    issues = collect(now)
    external = ""
    try:
        external = heartbeat_url()
    except Exception:
        issues.add("external_config")
    if args.check:
        print(json.dumps({"observed": sorted(issues), "external_configured": bool(external)}))
        return 0 if not issues else 1
    STATE.parent.mkdir(mode=0o700, exist_ok=True)
    with (STATE.parent / "monitor.lock").open("a") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        state = json.loads(STATE.read_text()) if STATE.exists() else {}
        state = transition(state, issues, now)
        # Persist the idempotency key before sending; retries have an identical payload.
        save_state(state)
        sent = None
        if state.get("pending"):
            try:
                sent = notify(config["recipient"], state["pending"])
            except Exception:
                sent = False
            if sent:
                state["notified"] = state["active"]
                state["last_sent"] = now
                state.pop("pending", None)
                save_state(state)
        external_ok = None
        if external:
            try:
                status, body = request(external + ("/fail" if state["active"] or sent is False else ""))
                external_ok = status == 200 and body.strip() == b"OK"
            except Exception:
                external_ok = False
        print(json.dumps({"observed": sorted(issues), "active": state["active"], "email_accepted": sent,
                          "external_configured": bool(external), "external_ping_accepted": external_ok}))
        return 1 if state["active"] or sent is False or external_ok is False else 0


if __name__ == "__main__":
    os.umask(0o077)
    try:
        sys.exit(main())
    except Exception:
        # Includes notification/network exceptions: never print their URLs, headers or bodies.
        print('{"monitor_error":true}', file=sys.stderr)
        sys.exit(1)
