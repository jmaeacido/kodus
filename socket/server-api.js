/**
 * ============================================
 * Socket.IO Server with Token Authentication & SSO
 * ============================================
 * 
 * This server provides:
 * - User registration with token-based authentication
 * - Socket.IO real-time communication with token validation
 * - Broadcast API endpoint for sending messages to channels
 * - SQLite database for user management
 * - SSO (Single Sign-On) authentication for protected routes
 * 
 * API Endpoints:
 * - POST /register - Register a new user and get authentication token
 * - POST /broadcast - Broadcast message to a channel (requires token)
 * - GET / - Homepage with API documentation
 * - GET /test - Interactive test page for Socket.IO (Protected with SSO)
 * - GET /docs - API documentation page
 * 
 * SSO Endpoints:
 * - GET /sso/login - Redirect to SSO provider login
 * - GET /sso/callback - Handle SSO authentication callback
 * - GET /sso/logout - Logout and destroy session
 * 
 * Socket.IO Events:
 * - subscribe - Join a channel (room)
 * - leave - Leave a channel (room)
 * - broadcast-event - Broadcast event to other clients in a channel
 * 
 * Authentication:
 * - All Socket.IO connections require a valid token
 * - Token can be provided via auth.token or query.token
 * - API endpoints (except /register, /, /test, /docs) require token in:
 *   - Authorization header: "Bearer <token>"
 *   - Request body: { "token": "<token>" }
 *   - Query parameter: ?token=<token>
 * - /test route requires SSO authentication via session
 * 
 * SSO Configuration:
 * - Configure SSO settings via environment variables or SSO_CONFIG object
 * - SSO_BASE_URL - SSO provider base URL
 * - SSO_LOGIN_URL - SSO login endpoint
 * - SSO_CALLBACK_URL - Callback URL for SSO response
 * - SSO_VALIDATE_URL - Token validation endpoint
 * - SSO_LOGOUT_URL - SSO logout endpoint
 * - SSO_CLIENT_ID - OAuth client ID (REQUIRED)
 * - SSO_CLIENT_SECRET - OAuth client secret (if required)
 * - SSO_RESPONSE_TYPE - OAuth response type (default: 'code')
 * - SSO_SCOPE - OAuth scope (default: 'openid profile email')
 * - SESSION_SECRET - Session secret for cookie encryption
 * 
 * @author ITO I Julieto Ompad
 * @version 2.0.0
 */

// ===============================
// 📦 Dependencies
// ===============================
// Load environment variables from .env file
require('dotenv').config();

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const path = require('path');
const fs = require('fs');
const Database = require('better-sqlite3');
const crypto = require('crypto');
const session = require('express-session');
const axios = require('axios');

// ===============================
// 🗄️ SQLite Database Setup
// ===============================
const db = new Database('users.db');
console.log('📦 Connected to SQLite database');

// Create users table if it doesn't exist
db.exec(`
  CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    purpose TEXT NOT NULL,
    token TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  )
`);

// Helper function to generate secure token
function generateToken() {
  return crypto.randomBytes(32).toString('hex');
}

// Helper function to validate token
function validateToken(token) {
  if (!token) return null;
  const user = db.prepare('SELECT * FROM users WHERE token = ?').get(token);
  return user || null;
}

// ===============================
// 🔐 SSO Configuration
// ===============================
// Configure SSO settings - Update these with actual SSO provider details
const SSO_CONFIG = {
  // SSO Provider Base URL (e.g., https://caraga-connect.dswd.gov.ph)
  baseUrl: process.env.SSO_BASE_URL || 'https://caraga-connect.dswd.gov.ph',
  // SSO Login Endpoint
  loginUrl: process.env.SSO_LOGIN_URL || 'https://caraga-connect.dswd.gov.ph/sso/login',
  // SSO Callback URL (this server's callback endpoint)
  callbackUrl: process.env.SSO_CALLBACK_URL || 'http://localhost:6002/sso/callback',
  // SSO Token Validation Endpoint
  validateUrl: process.env.SSO_VALIDATE_URL || 'https://caraga-connect.dswd.gov.ph/sso/validate',
  // SSO Logout URL
  logoutUrl: process.env.SSO_LOGOUT_URL || 'https://caraga-connect.dswd.gov.ph/sso/logout',
  // OAuth Client ID (REQUIRED - Get this from SSO provider)
  clientId: process.env.SSO_CLIENT_ID || '',
  // OAuth Client Secret (if required for token exchange)
  clientSecret: process.env.SSO_CLIENT_SECRET || '',
  // OAuth Response Type (usually 'code' for authorization code flow)
  responseType: process.env.SSO_RESPONSE_TYPE || 'code',
  // OAuth Scope (optional, adjust based on SSO provider requirements)
  scope: process.env.SSO_SCOPE || 'openid profile email',
  // Session secret (use a strong secret in production)
  sessionSecret: process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex'),
  // Session cookie name
  sessionName: process.env.SESSION_NAME || 'sso.session'
};

// Application URL from environment
const APP_URL = process.env.APP_URL || 'http://localhost:6002';

// ===============================
// ⚙️ Express Setup
// ===============================
const app = express();

// Session configuration
app.use(session({
  name: SSO_CONFIG.sessionName,
  secret: SSO_CONFIG.sessionSecret,
  resave: false,
  saveUninitialized: false,
  cookie: {
    secure: process.env.NODE_ENV === 'production', // Use secure cookies in production (HTTPS)
    httpOnly: true,
    maxAge: 24 * 60 * 60 * 1000 // 24 hours
  }
}));

app.use(cors({
  origin: true,
  credentials: true // Allow cookies
}));
app.use(express.json());   // Parse JSON body from Laravel
app.use(express.urlencoded({ extended: true })); // Parse URL-encoded bodies

// ===============================
// 🔐 SSO Authentication Middleware
// ===============================
const requireSSO = async (req, res, next) => {
  // Check if user is authenticated via SSO session
  if (req.session && req.session.ssoUser) {
    req.user = req.session.ssoUser;
    return next();
  }

  // If not authenticated, redirect to SSO login
  // Store the original URL to redirect back after login
  req.session.returnTo = req.originalUrl || req.url;
  return res.redirect('/sso/login');
};

// ===============================
// 🔐 Token Authentication Middleware (for API endpoints)
// ===============================
const authenticateToken = (req, res, next) => {
  // Skip authentication for registration, root route, SSO routes, test page, and docs page
  if (req.path === '/register' || req.path === '/' || req.path.startsWith('/sso/') || req.path === '/test' || req.path === '/docs') {
    return next();
  }

  // Safely extract token from headers, body, or query
  const authHeader = req.headers?.authorization;
  const tokenFromHeader = authHeader ? authHeader.replace('Bearer ', '') : null;
  const tokenFromBody = req.body?.token || null;
  const tokenFromQuery = req.query?.token || null;
  
  const token = tokenFromHeader || tokenFromBody || tokenFromQuery;

  if (!token) {
    return res.status(401).json({ error: 'Token required. Provide token in Authorization header, body, or query parameter.' });
  }

  const user = validateToken(token);
  if (!user) {
    return res.status(403).json({ error: 'Invalid or expired token' });
  }

  req.user = user;
  next();
};

// Apply token authentication middleware to API routes (not SSO routes)
app.use((req, res, next) => {
  if (req.path.startsWith('/sso/')) {
    return next();
  }
  if (req.path === '/register' || req.path === '/' || req.path === '/test' || req.path === '/docs') {
    return next();
  }
  authenticateToken(req, res, next);
});

// ===============================
// 🌐 Create HTTP + Socket.IO Server
// ===============================
const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: '*', // Allow any frontend URL
    methods: ['GET', 'POST']
  }
});

// ===============================
// 💬 Socket.IO Connection Handling
// ===============================
io.use((socket, next) => {
  // Validate token from handshake auth or query
  const token = socket.handshake.auth?.token || socket.handshake.query?.token;
  
  if (!token) {
    return next(new Error('Token required. Provide token in auth.token or query.token'));
  }

  const user = validateToken(token);
  if (!user) {
    return next(new Error('Invalid or expired token'));
  }

  socket.user = user;
  next();
});

io.on('connection', (socket) => {
  console.log(`🟢 Client connected: ${socket.id} (User: ${socket.user.name})`);

  // Subscribe to a channel (room)
  socket.on('subscribe', (channel) => {
    if (!channel) return;
    socket.join(channel);
    console.log(`📡 ${socket.id} (${socket.user.name}) subscribed to "${channel}"`);
  });

  // Leave a channel (room)
  socket.on('leave', (channel) => {
    if (!channel) return;
    socket.leave(channel);
    console.log(`🚪 ${socket.id} (${socket.user.name}) left "${channel}"`);
  });

  // Broadcast event from one client to others in the same channel
  socket.on('broadcast-event', (payload) => {
    if (!payload || !payload.channel) return;
    const { channel, event, data } = payload;
    console.log(`📢 Client broadcast: ${event} on "${channel}" from ${socket.id} (${socket.user.name}):`, data);
    socket.to(channel).emit(event, { ...data, from: socket.id, fromUser: socket.user.name });
  });

  // Handle disconnect
  socket.on('disconnect', (reason) => {
    console.log(`🔴 Client disconnected: ${socket.id} (${socket.user.name}) - ${reason}`);
  });
});

// ===============================
// 👤 User Registration Endpoint
// ===============================
app.post('/register', (req, res) => {
  console.log('📝 Registration request received');
  console.log('   Request body:', JSON.stringify(req.body));
  console.log('   Request headers:', JSON.stringify(req.headers));
  
  const { name, purpose } = req.body;

  // Validate input
  if (!name || !purpose) {
    console.warn('⚠️ Registration failed: Missing required fields');
    console.log('   Name provided:', name ? `"${name}"` : 'MISSING');
    console.log('   Purpose provided:', purpose ? `"${purpose}"` : 'MISSING');
    return res.status(400).json({ 
      error: 'Name and purpose are required',
      details: {
        name: name ? 'provided' : 'missing',
        purpose: purpose ? 'provided' : 'missing'
      }
    });
  }

  // Validate input types and lengths
  if (typeof name !== 'string' || typeof purpose !== 'string') {
    console.warn('⚠️ Registration failed: Invalid input types');
    console.log('   Name type:', typeof name);
    console.log('   Purpose type:', typeof purpose);
    return res.status(400).json({ 
      error: 'Name and purpose must be strings',
      details: {
        nameType: typeof name,
        purposeType: typeof purpose
      }
    });
  }

  if (name.trim().length === 0 || purpose.trim().length === 0) {
    console.warn('⚠️ Registration failed: Empty strings after trim');
    console.log('   Name length:', name.length, 'Trimmed length:', name.trim().length);
    console.log('   Purpose length:', purpose.length, 'Trimmed length:', purpose.trim().length);
    return res.status(400).json({ 
      error: 'Name and purpose cannot be empty',
      details: {
        nameLength: name.length,
        purposeLength: purpose.length
      }
    });
  }

  console.log(`🔄 Starting registration for: "${name}" with purpose: "${purpose}"`);

  // Check database connection
  try {
    db.prepare('SELECT 1').get();
    console.log('✅ Database connection verified');
  } catch (dbError) {
    console.error('❌ Database connection check failed:', dbError);
    console.error('   Error name:', dbError.name);
    console.error('   Error message:', dbError.message);
    console.error('   Error code:', dbError.code);
    if (dbError.stack) {
      console.error('   Stack trace:', dbError.stack);
    }
    return res.status(500).json({ 
      error: 'Database connection failed',
      details: {
        errorName: dbError.name,
        errorMessage: dbError.message,
        errorCode: dbError.code
      }
    });
  }

  // Generate unique token
  console.log('🔑 Generating unique token...');
  let token;
  let isUnique = false;
  let tokenAttempts = 0;
  const maxTokenAttempts = 10;
  
  while (!isUnique && tokenAttempts < maxTokenAttempts) {
    tokenAttempts++;
    token = generateToken();
    console.log(`   Attempt ${tokenAttempts}: Generated token (${token.length} chars)`);
    
    try {
      const existing = db.prepare('SELECT id FROM users WHERE token = ?').get(token);
      if (!existing) {
        isUnique = true;
        console.log(`✅ Unique token generated on attempt ${tokenAttempts}`);
      } else {
        console.log(`   Token collision detected, retrying...`);
      }
    } catch (checkError) {
      console.error('❌ Error checking token uniqueness:', checkError);
      console.error('   Error name:', checkError.name);
      console.error('   Error message:', checkError.message);
      return res.status(500).json({ 
        error: 'Failed to validate token uniqueness',
        details: {
          errorName: checkError.name,
          errorMessage: checkError.message,
          tokenAttempts: tokenAttempts
        }
      });
    }
  }

  if (!isUnique) {
    console.error(`❌ Failed to generate unique token after ${maxTokenAttempts} attempts`);
    return res.status(500).json({ 
      error: 'Failed to generate unique token',
      details: {
        attempts: tokenAttempts,
        maxAttempts: maxTokenAttempts
      }
    });
  }

  // Insert user into database
  console.log('💾 Inserting user into database...');
  console.log('   Name:', name);
  console.log('   Purpose:', purpose);
  console.log('   Token:', token.substring(0, 8) + '...' + token.substring(token.length - 8));
  
  try {
    const insertStmt = db.prepare('INSERT INTO users (name, purpose, token) VALUES (?, ?, ?)');
    const result = insertStmt.run(name, purpose, token);
    
    console.log(`✅ New user registered successfully`);
    console.log('   User ID:', result.lastInsertRowid);
    console.log('   Changes:', result.changes);
    console.log('   Name:', name);
    console.log('   Purpose:', purpose);
    
    res.status(201).json({
      success: true,
      message: 'User registered successfully',
      user: {
        id: result.lastInsertRowid,
        name,
        purpose,
        token
      }
    });
  } catch (error) {
    console.error('❌ Registration error occurred:');
    console.error('   Error name:', error.name);
    console.error('   Error message:', error.message);
    console.error('   Error code:', error.code);
    console.error('   Error errno:', error.errno);
    console.error('   Error syscall:', error.syscall);
    
    // Log SQLite specific errors
    if (error.code) {
      console.error('   SQLite error code:', error.code);
    }
    
    // Log the attempted values
    console.error('   Attempted values:');
    console.error('     Name:', name);
    console.error('     Purpose:', purpose);
    console.error('     Token length:', token ? token.length : 'N/A');
    
    // Log stack trace for debugging
    if (error.stack) {
      console.error('   Stack trace:', error.stack);
    }
    
    // Check for specific error types
    if (error.code === 'SQLITE_CONSTRAINT_UNIQUE') {
      console.error('   ⚠️ Unique constraint violation - token already exists');
      return res.status(409).json({ 
        error: 'Token collision detected (this should not happen)',
        details: {
          errorCode: error.code,
          errorMessage: error.message
        }
      });
    }
    
    if (error.code === 'SQLITE_CONSTRAINT_NOTNULL') {
      console.error('   ⚠️ NOT NULL constraint violation');
      return res.status(400).json({ 
        error: 'Required field is missing',
        details: {
          errorCode: error.code,
          errorMessage: error.message
        }
      });
    }
    
    if (error.code === 'SQLITE_READONLY') {
      console.error('   ⚠️ Database is read-only');
      return res.status(500).json({ 
        error: 'Database is read-only',
        details: {
          errorCode: error.code,
          errorMessage: error.message
        }
      });
    }
    
    if (error.code === 'SQLITE_BUSY') {
      console.error('   ⚠️ Database is locked/busy');
      return res.status(503).json({ 
        error: 'Database is temporarily unavailable',
        details: {
          errorCode: error.code,
          errorMessage: error.message
        }
      });
    }
    
    // Generic error response
    res.status(500).json({ 
      error: 'Failed to register user',
      details: {
        errorName: error.name,
        errorMessage: error.message,
        errorCode: error.code || 'UNKNOWN',
        timestamp: new Date().toISOString()
      }
    });
  }
});

// ===============================
// 📨 Laravel Broadcast Endpoint (Protected)
// ===============================
app.post('/broadcast', (req, res) => {
  const { channel, event, data } = req.body;

  if (!channel || !event) {
    console.warn('⚠️ Missing channel or event in broadcast payload.');
    return res.status(400).json({ error: 'Missing channel or event' });
  }

  console.log(`✅ Broadcast from ${req.user.name} → [${channel}] Event: "${event}"`, data);

  // Send to all connected clients in the specified channel
  io.to(channel).emit(event, { ...data, fromUser: req.user.name });

  res.status(200).json({ success: true });
});

// ===============================
// 🔧 Helper: Inject APP_URL into HTML
// ===============================
function injectAppUrl(html) {
  // Inject APP_URL as a JavaScript variable before closing </head> tag
  const scriptTag = `<script>window.APP_URL = '${APP_URL}';</script>`;
  return html.replace('</head>', `${scriptTag}\n</head>`);
}

// ===============================
// 🧭 Root Test Route
// ===============================
app.get('/', (req, res) => {
  const viewPath = path.join(__dirname, 'views', 'index.html');
  fs.readFile(viewPath, 'utf8', (err, data) => {
    if (err) {
      console.error('Error reading view file:', err);
      return res.status(500).send('Error loading page');
    }
    res.send(injectAppUrl(data));
  });
});

// ===============================
// 🔐 SSO Routes
// ===============================

// SSO Login - Redirect to SSO provider
app.get('/sso/login', (req, res) => {
  // Validate required OAuth parameters
  if (!SSO_CONFIG.clientId) {
    console.error('❌ SSO_CLIENT_ID is required. Please set it in environment variables or SSO_CONFIG.');
    return res.status(500).send(`
      <html>
        <head><title>SSO Configuration Error</title></head>
        <body style="font-family: Arial, sans-serif; padding: 40px; text-align: center;">
          <h1 style="color: #dc3545;">SSO Configuration Error</h1>
          <p>SSO_CLIENT_ID is required but not configured.</p>
          <p>Please set the <code>SSO_CLIENT_ID</code> environment variable or update <code>SSO_CONFIG.clientId</code> in server-api.js</p>
          <p><a href="/">Return to Home</a></p>
        </body>
      </html>
    `);
  }

  // Build SSO login URL with callback parameter
  const returnTo = req.query.returnTo || req.session.returnTo || '/test';
  req.session.returnTo = returnTo;
  
  // Generate state parameter for CSRF protection
  const state = crypto.randomBytes(16).toString('hex');
  req.session.ssoState = state;
  
  // Construct SSO login URL with required OAuth parameters
  const params = new URLSearchParams({
    client_id: SSO_CONFIG.clientId,
    redirect_uri: SSO_CONFIG.callbackUrl,
    response_type: SSO_CONFIG.responseType,
    scope: SSO_CONFIG.scope,
    state: state
  });
  
  const ssoLoginUrl = `${SSO_CONFIG.loginUrl}?${params.toString()}`;
  
  console.log(`🔐 Redirecting to SSO login: ${SSO_CONFIG.loginUrl}`);
  console.log(`   Client ID: ${SSO_CONFIG.clientId}`);
  console.log(`   Redirect URI: ${SSO_CONFIG.callbackUrl}`);
  res.redirect(ssoLoginUrl);
});

// SSO Callback - Handle SSO response
app.get('/sso/callback', async (req, res) => {
  try {
    const { token, code, state, error, error_description } = req.query;
    
    // Check for OAuth errors
    if (error) {
      console.error(`❌ SSO OAuth error: ${error} - ${error_description || 'No description'}`);
      return res.redirect(`/sso/login?error=${encodeURIComponent(error)}`);
    }
    
    // Validate state parameter (CSRF protection)
    if (state && req.session.ssoState !== state) {
      console.error('❌ SSO state mismatch - possible CSRF attack');
      return res.redirect('/sso/login?error=invalid_state');
    }
    delete req.session.ssoState;
    
    // Validate SSO token/code with SSO provider
    // This is a generic implementation - adjust based on actual SSO provider
    let ssoToken = token || code;
    
    if (!ssoToken) {
      console.error('❌ SSO callback missing token/code');
      return res.redirect('/sso/login?error=missing_token');
    }

    // Validate token with SSO provider
    try {
      const validateResponse = await axios.get(SSO_CONFIG.validateUrl, {
        params: { token: ssoToken },
        headers: {
          'Accept': 'application/json'
        },
        timeout: 10000
      });

      // Extract user information from SSO response
      // Adjust based on actual SSO provider response structure
      const userData = validateResponse.data;
      
      if (!userData || !userData.user) {
        throw new Error('Invalid SSO response');
      }

      // Store user in session
      req.session.ssoUser = {
        id: userData.user.id || userData.user.email,
        email: userData.user.email,
        name: userData.user.name || userData.user.display_name || userData.user.email,
        token: ssoToken,
        authenticatedAt: new Date().toISOString()
      };

      console.log(`✅ SSO authentication successful for: ${req.session.ssoUser.email}`);

      // Redirect to original destination or test page
      const returnTo = req.session.returnTo || '/test';
      delete req.session.returnTo;
      res.redirect(returnTo);

    } catch (validateError) {
      console.error('❌ SSO token validation failed:', validateError.message);
      
      // If validation endpoint doesn't exist or returns error, 
      // you can implement a fallback validation method here
      // For now, we'll create a basic session for testing
      // REMOVE THIS IN PRODUCTION - Replace with actual SSO validation
      if (process.env.NODE_ENV !== 'production') {
        console.warn('⚠️ Using fallback SSO validation (development only)');
        req.session.ssoUser = {
          id: 'sso-user-' + Date.now(),
          email: 'user@dswd.gov.ph',
          name: 'SSO User',
          token: ssoToken,
          authenticatedAt: new Date().toISOString()
        };
        const returnTo = req.session.returnTo || '/test';
        delete req.session.returnTo;
        return res.redirect(returnTo);
      }
      
      return res.redirect('/sso/login?error=validation_failed');
    }

  } catch (error) {
    console.error('❌ SSO callback error:', error);
    res.redirect('/sso/login?error=callback_error');
  }
});

// SSO Logout
app.get('/sso/logout', (req, res) => {
  const ssoToken = req.session.ssoUser?.token;
  
  // Destroy session
  req.session.destroy((err) => {
    if (err) {
      console.error('❌ Session destroy error:', err);
    }
    
    // Redirect to SSO logout (if token exists) or home page
    if (ssoToken && SSO_CONFIG.logoutUrl) {
      const logoutUrl = `${SSO_CONFIG.logoutUrl}?token=${encodeURIComponent(ssoToken)}&redirect_uri=${encodeURIComponent(APP_URL + '/')}`;
      res.redirect(logoutUrl);
    } else {
      res.redirect('/');
    }
  });
});

// ===============================
// 🧪 Test Page Route (Protected with SSO)
// ===============================
app.get('/test', requireSSO, (req, res) => {
  const viewPath = path.join(__dirname, 'views', 'test.html');
  fs.readFile(viewPath, 'utf8', (err, data) => {
    if (err) {
      console.error('Error reading test file:', err);
      return res.status(500).send('Error loading test page');
    }
    res.send(injectAppUrl(data));
  });
});

// ===============================
// 📚 Documentation Page Route
// ===============================
app.get('/docs', (req, res) => {
  const viewPath = path.join(__dirname, 'views', 'docs.html');
  fs.readFile(viewPath, 'utf8', (err, data) => {
    if (err) {
      console.error('Error reading docs file:', err);
      return res.status(500).send('Error loading documentation page');
    }
    res.send(injectAppUrl(data));
  });
});

// ===============================
// 🚀 Start Server
// ===============================
const PORT = 6002;
server.listen(PORT, () => {
  console.log(`🚀 Socket.IO server running on port:${PORT}`);
  console.log(`🔗 Ready for requests at /broadcast`);
  console.log(`🏠 Homepage: http://localhost:${PORT}/`);
  console.log(`📚 Documentation: http://localhost:${PORT}/docs`);
  console.log(`🧪 Test Page: http://localhost:${PORT}/test`);
});
