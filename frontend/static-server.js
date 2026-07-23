const http = require('node:http')
const fs = require('node:fs')
const path = require('node:path')

const root = path.join(__dirname, 'dist')
const port = 5173
const apiTarget = { hostname: '127.0.0.1', port: 8033 }

const types = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
}

function send(res, status, file) {
  res.writeHead(status, {
    'Content-Type': types[path.extname(file).toLowerCase()] || 'application/octet-stream',
  })
  fs.createReadStream(file).pipe(res)
}

const server = http.createServer((req, res) => {
  const urlPath = decodeURIComponent(new URL(req.url, `http://localhost:${port}`).pathname)

  if (urlPath.startsWith('/api/')) {
    const proxyReq = http.request({
      hostname: apiTarget.hostname,
      port: apiTarget.port,
      path: req.url,
      method: req.method,
      headers: {
        ...req.headers,
        host: `${apiTarget.hostname}:${apiTarget.port}`,
      },
    }, proxyRes => {
      res.writeHead(proxyRes.statusCode || 502, proxyRes.headers)
      proxyRes.pipe(res)
    })

    proxyReq.on('error', () => {
      res.writeHead(502, { 'Content-Type': 'application/json; charset=utf-8' })
      res.end(JSON.stringify({ message: 'Backend indisponível na porta 8033.' }))
    })

    req.pipe(proxyReq)
    return
  }

  const requested = path.normalize(path.join(root, urlPath))

  if (!requested.startsWith(root)) {
    res.writeHead(403)
    res.end('Forbidden')
    return
  }

  const file = fs.existsSync(requested) && fs.statSync(requested).isFile()
    ? requested
    : path.join(root, 'index.html')

  send(res, 200, file)
})

server.on('error', error => {
  console.error(error)
})

server.listen(port)
