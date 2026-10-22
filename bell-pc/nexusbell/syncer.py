"""Talks to Nexus: downloads the schedule and sounds, reports status and the
bell diary. Uses only the Python standard library. Nothing here is needed to
ring a bell: the app rings from its saved copy when Nexus cannot be reached."""
from __future__ import annotations

import hashlib
import json
import os
import socket
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any, Dict, List, Optional

from . import VERSION

TIMEOUT = 20


class SyncError(Exception):
    def __init__(self, message: str, rejected: bool = False):
        super().__init__(message)
        self.rejected = rejected


@dataclass
class SyncResult:
    version: str
    changed: bool
    data: Optional[Dict[str, Any]]


class NexusClient:
    def __init__(self, server_url: str, token: str):
        self.server_url = server_url.strip().rstrip("/")
        self.token = token.strip()

    def configured(self) -> bool:
        return bool(self.server_url and self.token)

    def sync(self, version: str, status: Dict[str, Any], events: List[Dict[str, Any]]) -> SyncResult:
        fields = {
            "version": version,
            "pc_name": socket.gethostname()[:80],
            "app_version": VERSION,
            "status": json.dumps(status, ensure_ascii=False),
            "events": json.dumps(events, ensure_ascii=False),
        }
        body = self._request("/bell/api/sync", urllib.parse.urlencode(fields).encode("utf-8"))
        try:
            payload = json.loads(body.decode("utf-8"))
        except ValueError:
            raise SyncError("jawaban Nexus tidak bisa dibaca")
        if not isinstance(payload, dict) or not payload.get("ok"):
            raise SyncError("jawaban Nexus tidak sesuai")
        data = payload.get("data") if payload.get("changed") else None
        return SyncResult(str(payload.get("version", "")), bool(payload.get("changed")), data)

    def download_sound(self, sound_id: int, sha256: str, destination: str) -> None:
        """Downloads one sound and checks it is the file Nexus announced."""
        body = self._request("/bell/api/sounds/%d" % sound_id, None)
        if hashlib.sha256(body).hexdigest() != sha256:
            raise SyncError("file suara %d rusak saat diunduh" % sound_id)
        os.makedirs(os.path.dirname(destination), exist_ok=True)
        partial = destination + ".part"
        with open(partial, "wb") as out:
            out.write(body)
        os.replace(partial, destination)

    def _request(self, path: str, data: Optional[bytes]) -> bytes:
        if not self.configured():
            raise SyncError("alamat atau kunci belum diisi")
        request = urllib.request.Request(self.server_url + path, data=data, method="POST" if data is not None else "GET")
        request.add_header("X-Nexus-Bell-Token", self.token)
        request.add_header("User-Agent", "NexusBell/" + VERSION)
        if data is not None:
            request.add_header("Content-Type", "application/x-www-form-urlencoded")
        try:
            with urllib.request.urlopen(request, timeout=TIMEOUT) as response:
                return response.read()
        except urllib.error.HTTPError as error:
            if error.code in (401, 403):
                raise SyncError("kunci ditolak", rejected=True)
            raise SyncError("Nexus menjawab kode %d" % error.code)
        except (urllib.error.URLError, socket.timeout, ConnectionError, OSError) as error:
            reason = getattr(error, "reason", error)
            raise SyncError("tidak ada jaringan (%s)" % reason)
