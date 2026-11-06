#!/usr/bin/env python3
"""Convert Telethon .session (+ seller .json) files into TelegramInspector session JSON.

Usage: python3 telethon_to_go.py <input_dir> <output_dir>

For every <phone>.session in input_dir, reads dc/address/port/auth_key from the
SQLite `sessions` table and, if present, api_id/api_hash/two_fa/first_name/id
from <phone>.json next to it. Writes <output_dir>/<phone>.json in the format
loaded by user_pool.go (see SessionJSON in session_storage.go).

Never prints keys or passwords.
"""
import json
import os
import sqlite3
import sys


def convert(session_path, meta):
    con = sqlite3.connect(f"file:{session_path}?mode=ro", uri=True)
    try:
        rows = con.execute(
            "SELECT dc_id, server_address, port, auth_key FROM sessions WHERE auth_key IS NOT NULL"
        ).fetchall()
    finally:
        con.close()
    if len(rows) != 1:
        raise ValueError(f"expected 1 session row, got {len(rows)}")
    dc_id, addr, port, auth_key = rows[0]
    if not auth_key or len(auth_key) != 256:
        raise ValueError(f"bad auth_key length {len(auth_key or b'')}")

    phone = str(meta.get("phone") or os.path.basename(session_path)[: -len(".session")]).lstrip("+")
    return phone, {
        "phone": phone,
        "dc_id": dc_id,
        "server_address": addr,
        "port": port,
        "auth_key_hex": auth_key.hex(),
        "api_id": int(meta.get("api_id") or meta.get("app_id") or 0),
        "api_hash": meta.get("api_hash") or meta.get("app_hash") or "",
        "two_fa": meta.get("two_fa") or meta.get("twoFA") or "",
        "first_name": meta.get("first_name") or "",
        "id": int(meta.get("id") or 0),
    }


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    src, dst = sys.argv[1], sys.argv[2]
    os.makedirs(dst, exist_ok=True)

    ok = failed = 0
    for name in sorted(os.listdir(src)):
        if not name.endswith(".session"):
            continue
        session_path = os.path.join(src, name)
        meta_path = session_path[: -len(".session")] + ".json"
        meta = {}
        if os.path.exists(meta_path):
            with open(meta_path) as f:
                meta = json.load(f)
        try:
            phone, data = convert(session_path, meta)
        except Exception as e:
            print(f"FAIL {name}: {e}")
            failed += 1
            continue
        out = os.path.join(dst, f"{phone}.json")
        with open(out, "w") as f:
            json.dump(data, f, indent=2)
        os.chmod(out, 0o600)
        print(f"OK   {phone}  dc={data['dc_id']} api_id={data['api_id']} 2fa={'yes' if data['two_fa'] else 'no'}")
        ok += 1

    print(f"\n{ok} converted, {failed} failed -> {dst}")


if __name__ == "__main__":
    main()
