// ===============================
// 📦 Dependencies
// ===============================
const path = require('path');
require('dotenv').config({ quiet: true });
require('dotenv').config({ path: path.resolve(__dirname, '..', '.env'), quiet: true });
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const fs = require('fs');

// ===============================
// ⚙️ Express Setup
// ===============================
const app = express();
app.use(cors());
app.use(express.json());

// ===============================
// ⚙️ Configuration
// ===============================
const PORT = process.env.PORT || 6001;
const APP_URL = process.env.APP_URL || `http://localhost:${PORT}`;
const SOCKET_TOKEN_SOURCE = sanitizeToken(process.env.SOCKET_BEARER_TOKEN || '') !== ''
  ? 'SOCKET_BEARER_TOKEN'
  : (sanitizeToken(process.env.KODUS_SOCKET_BEARER_TOKEN || '') !== '' ? 'KODUS_SOCKET_BEARER_TOKEN' : 'none');
const SOCKET_BEARER_TOKEN = sanitizeToken(process.env.SOCKET_BEARER_TOKEN || process.env.KODUS_SOCKET_BEARER_TOKEN || '');
const SOCKET_TOKEN = SOCKET_BEARER_TOKEN;
const SOCKET_DEBUG = ['1', 'true', 'yes', 'on'].includes(String(process.env.KODUS_SOCKET_DEBUG || process.env.SOCKET_DEBUG || '').toLowerCase());

// ===============================
// 🔐 Token Validator
// ===============================
function sanitizeToken(token) {
  return String(token || '').trim().replace(/^['"]|['"]$/g, '');
}

function logTime() {
  return new Date().toISOString();
}

function inspectToken(token) {
  const expectedToken = sanitizeToken(SOCKET_BEARER_TOKEN);
  const receivedToken = sanitizeToken(token);

  return {
    expectedConfigured: expectedToken !== '',
    expectedLength: expectedToken.length,
    receivedLength: receivedToken.length,
    matches: expectedToken !== '' && receivedToken === expectedToken,
  };
}

function isValidToken(token) {
  const inspection = inspectToken(token);

  if (SOCKET_DEBUG || !inspection.matches) {
    console.log(
      `${logTime()} Broadcast auth token expected_configured=${inspection.expectedConfigured} expected_length=${inspection.expectedLength} received_length=${inspection.receivedLength} match=${inspection.matches}`
    );
  }

  return inspection.matches;
}

function extractBearerToken(req) {
  const authorization = String(req.headers.authorization || '').trim();
  const hasAuthorization = authorization !== '';
  const hasBearerPrefix = /^Bearer\s+/i.test(authorization);

  if (SOCKET_DEBUG || !hasBearerPrefix) {
    console.log(`${logTime()} Broadcast auth header present=${hasAuthorization} bearer_prefix=${hasBearerPrefix}`);
  }

  if (!hasBearerPrefix) {
    return '';
  }

  return sanitizeToken(authorization.replace(/^Bearer\s+/i, ''));
}

// ===============================
// 🌐 Create HTTP + Socket.IO Server
// ===============================
const server = http.createServer(app);

const io = new Server(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST']
  }
});

// ===============================
// 💬 Socket.IO Connection Handling
// ===============================
io.on('connection', (socket) => {
  console.log(`🟢 Client connected: ${socket.id}`);

  // ===============================
  // 📡 Subscribe to Channel (No Token Required)
  // ===============================
  socket.on('subscribe', (channel) => {
    if (!channel) {
      console.warn(`⚠️ Invalid subscribe attempt from ${socket.id} - no channel provided`);
      return;
    }

    socket.join(channel);
    console.log(`📡 ${socket.id} subscribed to "${channel}"`);
  });

  // ===============================
  // 🚪 Leave Channel (No Token Required)
  // ===============================
  socket.on('leave', (channel) => {
    if (!channel) {
      console.warn(`⚠️ Invalid leave attempt from ${socket.id} - no channel provided`);
      return;
    }

    socket.leave(channel);
    console.log(`🚪 ${socket.id} left "${channel}"`);
  });

  // ===============================
  // 🔐 Broadcast Event (Client → Channel)
  // ===============================
  socket.on('broadcast-event', (payload) => {
    if (
      !payload ||
      !payload.channel ||
      !payload.event ||
      !isValidToken(payload.socket_token)
    ) {
      console.warn(`⛔ Unauthorized broadcast attempt from ${socket.id}`);
      return;
    }

    const { channel, event, data } = payload;

    console.log(
      `📢 Secure client broadcast: "${event}" on "${channel}" from ${socket.id}`
    );

    socket.to(channel).emit(event, {
      ...data,
      from: socket.id
    });
  });

  // ===============================
  // 🔌 Disconnect
  // ===============================
  socket.on('disconnect', (reason) => {
    console.log(`🔴 Client disconnected: ${socket.id} (${reason})`);
  });
});

// ===============================
// 📨 Laravel Secure Broadcast Endpoint
// ===============================
app.post('/broadcast', (req, res) => {
  const { channel, event, data } = req.body;
  const bearerToken = extractBearerToken(req);
  const tokenInspection = inspectToken(bearerToken);

  if (!channel || !event || !tokenInspection.matches) {
    console.warn(
      `${logTime()} ⛔ Unauthorized Laravel broadcast attempt. channel_present=${!!channel} event_present=${!!event} expected_configured=${tokenInspection.expectedConfigured} expected_length=${tokenInspection.expectedLength} received_length=${tokenInspection.receivedLength} match=${tokenInspection.matches}`
    );
    return res.status(403).json({ error: 'Unauthorized' });
  }

  if (SOCKET_DEBUG || event !== 'mail.typing') {
    console.log(`${logTime()} ✅ Secure Laravel broadcast → [${channel}] Event: "${event}"`);
  }

  io.to(channel).emit(event, data);

  res.status(200).json({ success: true });
});

// ===============================
// 🧭 UI Routes
// ===============================
app.get('/socket-ui', (req, res) => {
  res.send(`
    <!DOCTYPE html>
    <html>
    <head>
      <title>Socket.IO Server Status</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <style>
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
        }
        body {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 20px;
        }
        .container {
          max-width: 900px;
          width: 100%;
        }
        .status-box {
          background: white;
          padding: 50px;
          border-radius: 20px;
          box-shadow: 0 20px 60px rgba(0,0,0,0.3);
          text-align: center;
        }
        .status-icon {
          font-size: 80px;
          color: #10b981;
          margin-bottom: 20px;
          animation: pulse 2s infinite;
        }
        @keyframes pulse {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.05); }
        }
        h1 {
          color: #333;
          margin-bottom: 10px;
          font-size: 32px;
        }
        .server-info {
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
          padding: 20px;
          border-radius: 10px;
          margin: 20px 0;
          font-size: 16px;
        }
        .server-info-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 15px;
          text-align: left;
        }
        .server-info-item {
          padding: 10px;
          background: rgba(255,255,255,0.1);
          border-radius: 8px;
        }
        .server-info strong {
          display: block;
          font-weight: 600;
          margin-bottom: 5px;
          opacity: 0.9;
        }
        .endpoint-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
          gap: 15px;
          margin: 30px 0;
        }
        .endpoint-card {
          background: #f8f9fa;
          padding: 20px;
          border-radius: 10px;
          text-align: left;
          border-left: 4px solid #667eea;
          transition: transform 0.3s, box-shadow 0.3s;
        }
        .endpoint-card:hover {
          transform: translateY(-5px);
          box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .endpoint-method {
          display: inline-block;
          background: #667eea;
          color: white;
          padding: 4px 10px;
          border-radius: 5px;
          font-size: 12px;
          font-weight: bold;
          margin-bottom: 10px;
        }
        .endpoint-method.post {
          background: #10b981;
        }
        .endpoint-path {
          font-family: monospace;
          font-size: 14px;
          color: #333;
          font-weight: 600;
        }
        .endpoint-desc {
          font-size: 13px;
          color: #666;
          margin-top: 8px;
        }
        .security-badge {
          display: inline-block;
          background: #fbbf24;
          color: #92400e;
          padding: 3px 8px;
          border-radius: 4px;
          font-size: 11px;
          font-weight: bold;
          margin-top: 8px;
        }
        .links {
          margin-top: 40px;
          display: flex;
          gap: 20px;
          justify-content: center;
          flex-wrap: wrap;
        }
        .btn {
          display: inline-flex;
          align-items: center;
          gap: 10px;
          padding: 15px 30px;
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
          text-decoration: none;
          border-radius: 10px;
          transition: transform 0.3s, box-shadow 0.3s;
          font-weight: 600;
          font-size: 16px;
        }
        .btn:hover {
          transform: translateY(-3px);
          box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .btn i {
          font-size: 20px;
        }
        .footer {
          margin-top: 30px;
          padding-top: 20px;
          border-top: 1px solid #e0e0e0;
          color: #666;
          font-size: 14px;
        }
      </style>
    </head>
    <body>
      <div class="container">
        <div class="status-box">
          <div class="status-icon"><i class="fas fa-check-circle"></i></div>
          <h1>✅ Socket.IO Server is Running</h1>
          
          <div class="server-info">
            <div class="server-info-grid">
              <div class="server-info-item">
                <strong><i class="fas fa-globe"></i> Server URL</strong>
                ${APP_URL}
              </div>
              <div class="server-info-item">
                <strong><i class="fas fa-network-wired"></i> Port</strong>
                ${PORT}
              </div>
              <div class="server-info-item">
                <strong><i class="fas fa-shield-alt"></i> Security</strong>
                <span style="color: #10b981;">●</span> Token Protected
              </div>
            </div>
          </div>
          
          <h3 style="color: #667eea; margin-top: 30px; margin-bottom: 15px;">
            <i class="fas fa-plug"></i> Available Endpoints
          </h3>
          <div class="endpoint-grid">
            <div class="endpoint-card">
              <span class="endpoint-method">GET</span>
              <div class="endpoint-path">/socket-ui/test</div>
              <div class="endpoint-desc">Interactive testing interface with message input and real-time logs</div>
            </div>
            <div class="endpoint-card">
              <span class="endpoint-method">GET</span>
              <div class="endpoint-path">/socket-ui/docs</div>
              <div class="endpoint-desc">Complete API documentation with examples and troubleshooting</div>
            </div>
            <div class="endpoint-card">
              <span class="endpoint-method post">POST</span>
              <div class="endpoint-path">/broadcast</div>
              <div class="endpoint-desc">Send broadcasts to subscribed channels</div>
              <span class="security-badge"><i class="fas fa-lock"></i> Requires socket_token</span>
            </div>
          </div>

          <div class="links">
            <a href="/socket-ui/test" class="btn">
              <i class="fas fa-flask"></i>
              Open Test Page
            </a>
            <a href="/socket-ui/docs" class="btn">
              <i class="fas fa-book"></i>
              View Documentation
            </a>
          </div>

          <div class="footer">
            <i class="fas fa-bolt"></i> <strong>Socket.IO Server</strong> v1.0.0<br>
            Real-time broadcasting with secure token authentication
          </div>
        </div>
      </div>
    </body>
    </html>
  `);
});

app.get('/socket-ui/test', (req, res) => {
  const html = fs.readFileSync(__dirname + '/views/test.html', 'utf-8');
  
  // Inject navigation bar and configuration
  const navigationBar = `
    <style>
      .top-nav {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 15px 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      .top-nav .brand {
        color: white;
        font-size: 20px;
        font-weight: bold;
        text-decoration: none;
      }
      .top-nav .nav-links {
        display: flex;
        gap: 15px;
      }
      .top-nav .nav-links a {
        color: white;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 5px;
        background: rgba(255,255,255,0.2);
        transition: background 0.3s;
        font-size: 14px;
      }
      .top-nav .nav-links a:hover {
        background: rgba(255,255,255,0.3);
      }
      .top-nav .nav-links a.active {
        background: rgba(255,255,255,0.4);
        font-weight: bold;
      }
      body {
        padding-top: 80px !important;
      }
      .server-info {
        color: white;
        font-size: 12px;
        opacity: 0.9;
      }
    </style>
    <div class="top-nav">
      <div>
        <a href="/socket-ui" class="brand"><i class="fas fa-plug"></i> Socket.IO Server</a>
        <div class="server-info">Port: ${PORT} | ${APP_URL}</div>
      </div>
      <div class="nav-links">
        <a href="/socket-ui"><i class="fas fa-home"></i> Home</a>
        <a href="/socket-ui/test" class="active"><i class="fas fa-flask"></i> Test Page</a>
        <a href="/socket-ui/docs"><i class="fas fa-book"></i> Documentation</a>
      </div>
    </div>
  `;
  
  const injectedHtml = html
    .replace('<body>', `<body>\n${navigationBar}`)
    .replace(
      '<script>',
      `<script>\n    window.APP_URL = '${APP_URL}';\n    window.SOCKET_TOKEN = '${SOCKET_TOKEN}';`
    );
  res.send(injectedHtml);
});

app.get('/socket-ui/docs', (req, res) => {
  const html = fs.readFileSync(__dirname + '/views/docs.html', 'utf-8');
  
  // Inject navigation bar and configuration
  const navigationBar = `
    <style>
      .top-nav {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 15px 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      .top-nav .brand {
        color: white;
        font-size: 20px;
        font-weight: bold;
        text-decoration: none;
      }
      .top-nav .nav-links {
        display: flex;
        gap: 15px;
      }
      .top-nav .nav-links a {
        color: white;
        text-decoration: none;
        padding: 8px 16px;
        border-radius: 5px;
        background: rgba(255,255,255,0.2);
        transition: background 0.3s;
        font-size: 14px;
      }
      .top-nav .nav-links a:hover {
        background: rgba(255,255,255,0.3);
      }
      .top-nav .nav-links a.active {
        background: rgba(255,255,255,0.4);
        font-weight: bold;
      }
      body {
        padding-top: 80px !important;
      }
      .server-info {
        color: white;
        font-size: 12px;
        opacity: 0.9;
      }
    </style>
    <div class="top-nav">
      <div>
        <a href="/socket-ui" class="brand"><i class="fas fa-plug"></i> Socket.IO Server</a>
        <div class="server-info">Port: ${PORT} | ${APP_URL}</div>
      </div>
      <div class="nav-links">
        <a href="/socket-ui"><i class="fas fa-home"></i> Home</a>
        <a href="/socket-ui/test"><i class="fas fa-flask"></i> Test Page</a>
        <a href="/socket-ui/docs" class="active"><i class="fas fa-book"></i> Documentation</a>
      </div>
    </div>
  `;
  
  const injectedHtml = html
    .replace('<body>', `<body>\n${navigationBar}`)
    .replace(
      '<script>',
      `<script>\n    window.APP_URL = '${APP_URL}';\n    window.SOCKET_TOKEN = '${SOCKET_TOKEN}';`
    );
  res.send(injectedHtml);
});

// ===============================
// 🚀 Start Server
// ===============================
server.listen(PORT, () => {
  console.log(`${logTime()} 🚀 Socket.IO server running on port: ${PORT}`);
  console.log(`${logTime()} 🔗 App URL: ${APP_URL}`);
  console.log(`${logTime()} 🔐 Token protection ${SOCKET_BEARER_TOKEN ? 'enabled' : 'disabled'} source=${SOCKET_TOKEN_SOURCE} length=${SOCKET_BEARER_TOKEN.length}`);
  console.log(`${logTime()} 🧾 Socket debug logging ${SOCKET_DEBUG ? 'enabled' : 'disabled'}`);
});
