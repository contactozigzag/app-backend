# Waydroid + Cloudflare Tunnel → Local Dev Server

Use this when you need to test the Android app (running in Waydroid) against your local
FrankenPHP dev server. The tunnel gives Waydroid a real `https://` URL backed by a valid
Cloudflare cert, so you never have to deal with trusting Caddy's internal CA on Android.

## How it works

```
Waydroid (Android app)
  → https://random-name.trycloudflare.com   (Cloudflare edge, valid TLS)
    → cloudflared daemon on your host
      → https://localhost (FrankenPHP, tls internal)
```

Cloudflare terminates TLS publicly; `cloudflared` connects to your local server over the
loopback interface, bypassing the self-signed cert issue entirely.

## Prerequisites

- Docker dev stack running (`make up dev`)
- `cloudflared` installed on the host:

```bash
# Arch / Manjaro
yay -S cloudflared

# Debian / Ubuntu
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb -o cloudflared.deb
sudo dpkg -i cloudflared.deb
```

## Start a quick tunnel (no login required)

```bash
cloudflared tunnel --url https://localhost --no-tls-verify
```

- `--no-tls-verify` tells `cloudflared` to skip verification of Caddy's internal cert on
  the loopback connection. Traffic from the internet to Cloudflare is still fully encrypted.
- After a few seconds you'll see a line like:

```
Your quick Tunnel has been created! Visit it at (it may take some time to be reachable):
https://abc-def-123.trycloudflare.com
```

Copy that URL — it's your API base URL for this session.

## Configure the Android app in Waydroid

Set the API base URL in the app's dev/debug settings to the tunnel URL, e.g.:

```
https://abc-def-123.trycloudflare.com
```

The URL changes every time you restart `cloudflared` (quick tunnels are ephemeral).
See the named tunnel section below if you need a stable URL.

## Optional: named tunnel with a stable subdomain

If you have a Cloudflare account with a zone you control, you can create a persistent tunnel
with a fixed hostname (e.g., `dev.zigzaguealo.com`).

```bash
# One-time setup
cloudflared tunnel login                         # opens browser, picks your zone
cloudflared tunnel create zigzag-dev             # creates tunnel, writes credentials JSON
cloudflared tunnel route dns zigzag-dev dev.zigzaguealo.com

# Start it
cloudflared tunnel run --url https://localhost --no-tls-verify zigzag-dev
```

Or persist it in a config file (`~/.cloudflared/config.yml`):

```yaml
tunnel: zigzag-dev
credentials-file: /home/<you>/.cloudflared/<tunnel-id>.json

ingress:
  - hostname: dev.zigzaguealo.com
    service: https://localhost
    originRequest:
      noTLSVerify: true
  - service: http_status:404
```

Then just run:

```bash
cloudflared tunnel run zigzag-dev
```

## Stopping the tunnel

`Ctrl+C` in the terminal running `cloudflared`. The public URL immediately stops working.

## Notes

- Quick tunnels are rate-limited and intended for dev use only.
- The tunnel only exposes your local server for as long as `cloudflared` is running.
- If Caddy is not yet listening on 443 (containers still starting), `cloudflared` will keep
  retrying and connect once the server is ready.
- Waydroid must have network access to the internet (not isolated). Check with:
  `waydroid shell ping 1.1.1.1`
