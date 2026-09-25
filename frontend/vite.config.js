import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { spawn } from 'node:child_process'
import os from 'node:os'
import { fileURLToPath, URL } from 'node:url'

const BACKEND_DIR = fileURLToPath(new URL('../backend', import.meta.url))
const PORTAL_DIR = fileURLToPath(new URL('../mock-portal', import.meta.url))
// 127.0.0.1, not "localhost": on Windows, Node resolves localhost to IPv6 (::1)
// while php -S listens on IPv4 only, so the proxy can't reach PHP.
const PHP_HOST = '127.0.0.1'
const PHP_PORT = Number(process.env.PHP_PORT) || 8000
// Staging only: the mock employee portal. In production, PORTAL_URL points at the real portal
// (see backend/config.php) and this is never started.
const PORTAL_PORT = Number(process.env.PORTAL_PORT) || 8100
// The mock portal's admin screen is opened in a browser, so it listens on the LAN by default.
// Set PORTAL_BIND=127.0.0.1 to keep it on this computer only.
const PORTAL_BIND = process.env.PORTAL_BIND || '0.0.0.0'

/** First non-internal IPv4 address of this machine, for the printed LAN link. */
function lanAddress() {
  for (const list of Object.values(os.networkInterfaces())) {
    for (const net of list ?? []) {
      if (net.family === 'IPv4' && !net.internal) return net.address
    }
  }
  return PHP_HOST
}

/**
 * Starts the PHP API and the mock employee portal together with the Vite dev server,
 * so `npm run dev` is the only command needed. Skipped when VITE_API_TARGET points
 * somewhere else (e.g. XAMPP/Laragon already serving the backend).
 */
function phpServer(enabled) {
  return {
    name: 'php-dev-server',
    apply: 'serve',
    configureServer(server) {
      if (!enabled) return
      const start = (dir, port, bind = PHP_HOST) => spawn(process.env.PHP_BIN || 'php', ['-S', `${bind}:${port}`, '-t', dir], {
        stdio: ['ignore', 'ignore', 'pipe'],
      })
      // The API is only ever called by Vite on this machine, so it stays on 127.0.0.1.
      const php = start(BACKEND_DIR, PHP_PORT)
      const portal = process.env.PORTAL_URL ? null : start(PORTAL_DIR, PORTAL_PORT, PORTAL_BIND)
      php.on('error', (err) => {
        server.config.logger.error(
          `\n  PHP could not start (${err.code}). Install PHP 8.1+ and make sure "php" works in your terminal,\n` +
          `  or set PHP_BIN to the full path, e.g. PHP_BIN=C:\\xampp\\php\\php.exe\n`)
      })
      php.stderr.on('data', (d) => {
        const line = d.toString()
        if (/Address already in use|Failed to listen/i.test(line)) {
          server.config.logger.warn(
            `\n  Port ${PHP_PORT} is already used by another program, so the API can't start there.\n` +
            `  Close that program, or pick another port, e.g.  PowerShell: $env:PHP_PORT=8010   cmd: set PHP_PORT=8010\n`)
        } else if (/PHP (Fatal|Parse|Warning)/.test(line)) {
          server.config.logger.error(line.trim())
        }
      })
      php.on('exit', (code) => {
        if (code) server.config.logger.error(`  PHP stopped unexpectedly (exit code ${code}). The app will show "API not responding".`)
      })
      server.config.logger.info(`  ➜  PHP API:  http://${PHP_HOST}:${PHP_PORT}/api/ (started automatically)`)
      if (portal) {
        const portalHost = PORTAL_BIND === '0.0.0.0' ? lanAddress() : PHP_HOST
        server.config.logger.info(`  ➜  Portal:   http://${portalHost}:${PORTAL_PORT}/  (mock employee portal admin, staging only)`)
      }
      const stop = () => { php.kill(); portal?.kill() }
      server.httpServer?.once('close', stop)
      process.once('exit', stop)
      process.once('SIGINT', () => { stop(); process.exit() })
      process.once('SIGTERM', () => { stop(); process.exit() })
    },
  }
}

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const apiTarget = env.VITE_API_TARGET || `http://${PHP_HOST}:${PHP_PORT}`

  return {
    plugins: [vue(), tailwindcss(), phpServer(!env.VITE_API_TARGET)],
    // Build stamp shown in the sidebar (date + time), to tell versions apart at a glance
    define: { __BUILD_DATE__: JSON.stringify(new Date(Date.now() + 8 * 3600e3).toISOString().slice(0, 16).replace('T', ' ')) },
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      port: 5177,        // app URL: http://<this-pc-ip>:5177
      strictPort: true,  // fail loudly instead of silently moving to another port
      host: true,        // listen on the LAN, not just this computer
      open: true,
      proxy: {
        '/api': { target: apiTarget, changeOrigin: true },
      },
    },
  }
})
