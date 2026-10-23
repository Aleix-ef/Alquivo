"""Offline synthetic watchdog tests: no Docker, production files or real network calls."""
import contextlib
import importlib.util
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("alquivo_monitor", ROOT / "ops/server/alquivo-monitor.py")
monitor = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(monitor)


class MonitorTest(unittest.TestCase):
    def healthy(self):
        return {"version": 1, "checked_at": 1000, "database_ok": True, "queue_supported": True,
                "queued_ready": 0, "oldest_ready_age_seconds": 0, "failed_last_hour": 0,
                "scheduler_last_tick": 990, "scheduler_shared_cache": True, "production_ready": True}

    def test_healthy_metrics_are_accepted(self):
        self.assertEqual(set(), monitor.metric_problems(self.healthy(), 1000))

    def test_disk_thresholds_cover_percentage_bytes_and_inode_exhaustion(self):
        gib = 1024**3
        self.assertEqual(set(), monitor.disk_problems(80 * gib, 60 * gib, 100, 80))
        self.assertEqual({"disk_warning"}, monitor.disk_problems(80 * gib, 12 * gib, 100, 80))
        self.assertEqual({"disk_warning"}, monitor.disk_problems(30 * gib, 9 * gib, 100, 80))
        self.assertEqual({"disk_critical"}, monitor.disk_problems(80 * gib, 6 * gib, 100, 80))
        self.assertEqual({"disk_critical"}, monitor.disk_problems(30 * gib, 4 * gib, 100, 80))
        self.assertEqual({"inodes"}, monitor.disk_problems(80 * gib, 60 * gib, 100, 9))

    def test_stuck_queue_failed_jobs_and_scheduler_are_distinct(self):
        metrics = dict(self.healthy(), queued_ready=1, oldest_ready_age_seconds=301,
                       failed_last_hour=1, scheduler_last_tick=800)
        self.assertEqual({"queue_stuck", "queue_failed", "scheduler"}, monitor.metric_problems(metrics, 1000))

    def test_database_failure_does_not_fabricate_queue_or_scheduler_results(self):
        self.assertEqual({"database"}, monitor.metric_problems(dict(self.healthy(), database_ok=False), 1000))

    def test_future_or_missing_scheduler_ticks_are_unhealthy(self):
        for tick in (None, 1061):
            self.assertIn("scheduler", monitor.metric_problems(dict(self.healthy(), scheduler_last_tick=tick), 1000))

    def test_probe_rejects_unexpected_fields_stale_invalid_and_boolean_metrics(self):
        for metrics in ({}, dict(self.healthy(), private_data="SYNTHETIC_PRIVATE_DATA"),
                        dict(self.healthy(), version=True), dict(self.healthy(), checked_at=900),
                        dict(self.healthy(), queued_ready=True), dict(self.healthy(), failed_last_hour=-1),
                        dict(self.healthy(), oldest_ready_age_seconds="10")):
            self.assertEqual({"app_probe"}, monitor.metric_problems(metrics, 1000))

    def test_probe_fails_closed_for_unsupported_drivers_missing_counts_and_security_checks(self):
        self.assertIn("queue_config", monitor.metric_problems(dict(self.healthy(), queue_supported=False), 1000))
        self.assertIn("scheduler", monitor.metric_problems(dict(self.healthy(), scheduler_shared_cache=False), 1000))
        self.assertIn("app_probe", monitor.metric_problems(dict(self.healthy(), queued_ready=None), 1000))
        self.assertIn("readiness", monitor.metric_problems(dict(self.healthy(), production_ready=False), 1000))

    def test_warning_requires_two_consecutive_samples(self):
        state = monitor.transition({}, {"https"}, 1000)
        self.assertEqual([], state["active"])
        self.assertNotIn("pending", state)
        state = monitor.transition(state, {"https"}, 1120)
        self.assertEqual(["https"], state["active"])
        self.assertEqual(["https"], state["pending"]["active"])

    def test_single_transient_warning_does_not_notify(self):
        state = monitor.transition({}, {"https"}, 1000)
        state = monitor.transition(state, set(), 1120)
        state = monitor.transition(state, {"https"}, 1240)
        self.assertEqual([], state["active"])
        self.assertNotIn("pending", state)

    def test_critical_problem_is_immediate(self):
        for code in ("database", "disk_critical", "docker", "backup_resume", "readiness"):
            state = monitor.transition({}, {code}, 1000)
            self.assertEqual([code], state["active"])
            self.assertIn("pending", state)

    def test_retries_preserve_payload_and_idempotency_key(self):
        state = monitor.transition({}, {"database"}, 1000)
        notification = json.dumps(state["pending"], sort_keys=True)
        state = monitor.transition(state, {"database"}, 1120)
        self.assertEqual(notification, json.dumps(state["pending"], sort_keys=True))

    def test_no_repeat_until_six_hours_and_then_one_stable_reminder(self):
        state = {"active": ["database"], "notified": ["database"], "last_sent": 1000}
        state = monitor.transition(state, {"database"}, 1120)
        self.assertNotIn("pending", state)
        state = monitor.transition(state, {"database"}, 1000 + 6 * 3600)
        key = state["pending"]["key"]
        state = monitor.transition(state, {"database"}, 1000 + 6 * 3600 + 120)
        self.assertEqual(key, state["pending"]["key"])

    def test_recovery_requires_two_good_samples_and_names_only_notified_problems(self):
        state = {"active": ["database"], "notified": ["database"], "last_sent": 1000}
        state = monitor.transition(state, set(), 1120)
        self.assertNotIn("pending", state)
        state = monitor.transition(state, set(), 1240)
        self.assertEqual([], state["active"])
        self.assertEqual(["database"], state["pending"]["resolved"])

    def test_recovery_is_not_sent_for_an_incident_never_notified(self):
        state = monitor.transition({}, {"database"}, 1000)
        state = monitor.transition(state, set(), 1120)
        state = monitor.transition(state, set(), 1240)
        self.assertNotIn("pending", state)

    def test_unknown_problem_cannot_inject_logs_or_arbitrary_text(self):
        with self.assertRaises(ValueError):
            monitor.transition({}, {"PRIVATE_USER_LOG"}, 1000)

    def test_heartbeat_destination_is_fixed_https_no_query_redirect_or_extra_path(self):
        valid = "https://hc-ping.com/00000000-0000-0000-0000-000000000001"
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "heartbeat"
            self.assertEqual("", monitor.heartbeat_url(path))
            for value in ("", valid, valid + "\n"):
                path.write_text(value)
                self.assertEqual(value.strip(), monitor.heartbeat_url(path))
            for value in (valid.replace("https", "http"), valid + "?log=PRIVATE", valid + "/log",
                          valid.replace("hc-ping.com", "hc-ping.com.evil.example"), valid + "\nPRIVATE"):
                path.write_text(value)
                with self.assertRaises(ValueError):
                    monitor.heartbeat_url(path)

    def test_notification_reads_only_needed_settings_without_shell_expansion(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "production.env"
            path.write_text('APP_KEY=PRIVATE_SYNTHETIC_KEY\nRESEND_API_KEY="re_SYNTHETIC_ONLY"\nMAIL_FROM_ADDRESS=soporte@example.test\n')
            path.chmod(0o600)
            settings = monitor.mail_settings(path)
            self.assertEqual({"RESEND_API_KEY", "MAIL_FROM_ADDRESS"}, set(settings))
            self.assertEqual("re_SYNTHETIC_ONLY", settings["RESEND_API_KEY"])
            path.chmod(0o644)
            with self.assertRaises(ValueError):
                monitor.mail_settings(path)

    def test_notification_rejects_header_injection(self):
        for value in ("a@example.test\r\nBcc: other@example.test", "<a@example.test>", "a@example.test extra"):
            self.assertFalse(monitor.valid_email(value))
            with self.assertRaises(ValueError):
                monitor.notify(value, {})

    def test_notification_is_minimal_and_retry_body_is_identical(self):
        settings = {"MAIL_FROM_ADDRESS": "soporte@example.test", "RESEND_API_KEY": "SYNTHETIC_SECRET"}
        notification = {"active": ["database"], "resolved": [], "created": 1000, "key": "synthetic-event"}
        with patch.object(monitor, "mail_settings", return_value=settings), patch.object(monitor, "request", return_value=(200, b'{}')) as http:
            self.assertTrue(monitor.notify("owner@example.test", notification))
            self.assertTrue(monitor.notify("owner@example.test", notification))
            self.assertEqual(http.call_args_list[0], http.call_args_list[1])
            payload = json.loads(http.call_args.kwargs["body"])
            self.assertNotIn("SYNTHETIC_SECRET", json.dumps(payload))
            self.assertNotIn("PRIVATE", json.dumps(payload))
            self.assertEqual(["owner@example.test"], payload["to"])
            self.assertEqual("alquivo-monitor-synthetic-event", http.call_args.kwargs["headers"]["Idempotency-Key"])

    def invoke(self, directory, args, issues, external="", response=(200, b"OK")):
        config = Path(directory) / "config.json"
        config.write_text(json.dumps({"recipient": "owner@example.test"}))
        output = io.StringIO()
        with patch.object(monitor, "CONFIG", config), patch.object(monitor, "STATE", Path(directory) / "state.json"), \
                patch.object(monitor, "collect", return_value=issues), patch.object(monitor, "heartbeat_url", return_value=external), \
                patch.object(monitor, "notify", return_value=True) as mail, patch.object(monitor, "request", return_value=response) as http, \
                patch("sys.argv", ["monitor", *args]), contextlib.redirect_stdout(output):
            result = monitor.main()
        return result, json.loads(output.getvalue()), mail, http

    def test_check_mode_does_not_write_state_send_mail_or_ping_external(self):
        with tempfile.TemporaryDirectory() as directory:
            result, output, mail, http = self.invoke(directory, ["--check"], {"database"}, "https://hc-ping.com/synthetic")
            self.assertEqual(1, result)
            self.assertEqual(["database"], output["observed"])
            self.assertFalse((Path(directory) / "state.json").exists())
            mail.assert_not_called()
            http.assert_not_called()

    def test_real_critical_alert_and_recovery_flow_is_deduplicated_offline(self):
        with tempfile.TemporaryDirectory() as directory:
            result, output, mail, http = self.invoke(directory, [], {"database"}, "https://hc-ping.com/synthetic")
            self.assertEqual(1, result)
            self.assertTrue(output["email_accepted"])
            mail.assert_called_once()
            http.assert_called_once_with("https://hc-ping.com/synthetic/fail")
            _, _, mail, _ = self.invoke(directory, [], {"database"})
            mail.assert_not_called()
            self.invoke(directory, [], set())
            result, output, mail, _ = self.invoke(directory, [], set())
            self.assertEqual(0, result)
            self.assertEqual([], output["active"])
            self.assertEqual(["database"], mail.call_args.args[1]["resolved"])
            state = Path(directory) / "state.json"
            self.assertEqual(0o600, state.stat().st_mode & 0o777)

    def test_http_200_not_found_is_not_misreported_as_a_heartbeat_success(self):
        with tempfile.TemporaryDirectory() as directory:
            result, output, _, _ = self.invoke(directory, [], set(), "https://hc-ping.com/synthetic", (200, b"not found"))
            self.assertEqual(1, result)
            self.assertFalse(output["external_ping_accepted"])

    def test_test_email_does_not_change_state_or_ping_healthchecks(self):
        with tempfile.TemporaryDirectory() as directory:
            result, output, mail, http = self.invoke(directory, ["--test-email"], set())
            self.assertEqual(0, result)
            self.assertTrue(output["test_email_accepted"])
            self.assertTrue(mail.call_args.kwargs["test"])
            http.assert_not_called()
            self.assertFalse((Path(directory) / "state.json").exists())

    def test_transport_does_not_follow_redirects(self):
        self.assertIsNone(monitor.NoRedirect().redirect_request(None, None, 302, "", {}, "https://evil.example"))

    def test_systemd_monitor_does_not_depend_on_docker_or_expose_a_port(self):
        unit = (ROOT / "ops/server/alquivo-monitor.service").read_text()
        self.assertNotIn("\nRequires=docker", unit)
        self.assertIn("NoNewPrivileges=true", unit)
        self.assertIn("ProtectSystem=strict", unit)
        self.assertIn("/usr/bin/python3 /usr/local/lib/alquivo/monitor.py", unit)
        self.assertNotIn("--env-file", unit)
        timer = (ROOT / "ops/server/alquivo-monitor.timer").read_text()
        self.assertIn("OnUnitActiveSec=2min", timer)


if __name__ == "__main__":
    unittest.main()
