# Socket.IO Server - User Guide

Complete guide for using the Socket.IO server with token authentication.

## Table of Contents

1. [Quick Start](#quick-start)
2. [User Registration](#user-registration)
3. [Socket.IO Connection](#socketio-connection)
4. [Broadcasting Messages](#broadcasting-messages)
5. [Channel Management](#channel-management)
6. [Complete Examples](#complete-examples)
7. [Troubleshooting](#troubleshooting)

## Quick Start

### Step 1: Start the Server

```bash
node server-api.js
```

The server will start on port **6002**.

### Step 2: Register a User

```bash
curl -X POST http://localhost:6002/register \
  -H "Content-Type: application/json" \
  -d '{"name":"My Name","purpose":"Testing"}'
```

Save the token from the response!

### Step 3: Connect to Socket.IO

```javascript
const socket = io('http://localhost:6002', {
  auth: { token: 'YOUR_TOKEN_HERE' }
});
```

### Step 4: Subscribe to a Channel

```javascript
socket.emit('subscribe', 'my-channel');
```

### Step 5: Send and Receive Messages

```javascript
// Send a broadcast
socket.emit('broadcast-event', {
  channel: 'my-channel',
  event: 'message',
  data: { text: 'Hello!' }
});

// Listen for messages
socket.on('message', (data) => {
  console.log('Received:', data);
});
```

## User Registration

### API Endpoint

**POST** `/register`

### Request

```bash
curl -X POST http://localhost:6002/register \
  -H "Content-Type: application/json" \
  -d '{"name":"User Name","purpose":"Purpose"}'
```

### Response

```json
{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "User Name",
    "purpose": "Purpose",
    "token": "64-character-hexadecimal-token"
  }
}
```

### Important Notes

- **Save your token immediately** - You'll need it for all future requests
- Each registration creates a unique token
- Tokens are 64-character hexadecimal strings
- Tokens are stored in SQLite database

## Socket.IO Connection

### Authentication Required

All Socket.IO connections **require** a valid token. Without a token, the connection will be rejected.

### Method 1: Using auth Object (Recommended)

```javascript
const socket = io('http://localhost:6002', {
  auth: {
    token: 'YOUR_TOKEN_HERE'
  }
});
```

### Method 2: Using Query Parameter

```javascript
const socket = io('http://localhost:6002?token=YOUR_TOKEN_HERE');
```

### Connection Events

```javascript
// Connection successful
socket.on('connect', () => {
  console.log('Connected! Socket ID:', socket.id);
});

// Connection error (invalid token, etc.)
socket.on('connect_error', (error) => {
  console.error('Connection error:', error.message);
});

// Disconnected
socket.on('disconnect', (reason) => {
  console.log('Disconnected:', reason);
});
```

## Broadcasting Messages

### Method 1: Via API Endpoint

Send broadcasts using the REST API endpoint.

**Endpoint:** `POST /broadcast`

**Authentication:** Required (token)

**Token Methods:**
- Authorization header: `Authorization: Bearer <token>`
- Request body: `{ "token": "<token>", ... }`
- Query parameter: `?token=<token>`

**Example:**

```bash
curl -X POST http://localhost:6002/broadcast \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "channel": "test-channel",
    "event": "message",
    "data": {
      "text": "Hello World",
      "timestamp": "2024-01-01T12:00:00Z"
    }
  }'
```

### Method 2: Via Socket.IO

Send broadcasts directly from a Socket.IO client.

```javascript
socket.emit('broadcast-event', {
  channel: 'test-channel',
  event: 'message',
  data: {
    text: 'Hello from Socket.IO!',
    timestamp: new Date().toISOString()
  }
});
```

### Receiving Broadcasts

```javascript
// Listen for specific event
socket.on('message', (data) => {
  console.log('Message received:', data);
  // data will include: { text, timestamp, fromUser, ... }
});

// Listen for any event
socket.onAny((eventName, ...args) => {
  console.log('Event received:', eventName, args);
});
```

## Channel Management

### Subscribe to Channel

Join a channel to receive broadcasts sent to that channel.

```javascript
socket.emit('subscribe', 'channel-name');
```

**Note:** You must be subscribed to a channel to receive broadcasts sent to that channel.

### Leave Channel

Leave a channel to stop receiving broadcasts.

```javascript
socket.emit('leave', 'channel-name');
```

### Multiple Channels

You can subscribe to multiple channels:

```javascript
socket.emit('subscribe', 'channel-1');
socket.emit('subscribe', 'channel-2');
socket.emit('subscribe', 'channel-3');
```

## Complete Examples

### Example 1: Basic Chat Application

```javascript
// 1. Connect with token
const socket = io('http://localhost:6002', {
  auth: { token: 'YOUR_TOKEN' }
});

// 2. Wait for connection
socket.on('connect', () => {
  console.log('Connected!');
  
  // 3. Subscribe to chat channel
  socket.emit('subscribe', 'chat-room');
});

// 4. Send message
function sendMessage(text) {
  socket.emit('broadcast-event', {
    channel: 'chat-room',
    event: 'chat-message',
    data: { text, user: 'MyName' }
  });
}

// 5. Receive messages
socket.on('chat-message', (data) => {
  console.log(`${data.fromUser}: ${data.text}`);
});
```

### Example 2: Real-time Notifications

```javascript
const socket = io('http://localhost:6002', {
  auth: { token: 'YOUR_TOKEN' }
});

socket.on('connect', () => {
  // Subscribe to user-specific notification channel
  socket.emit('subscribe', `notifications-user-123`);
});

// Listen for notifications
socket.on('notification', (data) => {
  showNotification(data.message);
});
```

### Example 3: Using API to Send Broadcasts

```javascript
// Send broadcast via API (e.g., from backend server)
async function sendBroadcast(channel, event, data) {
  const response = await fetch('http://localhost:6002/broadcast', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${YOUR_TOKEN}`
    },
    body: JSON.stringify({
      channel,
      event,
      data
    })
  });
  
  return await response.json();
}

// Usage
sendBroadcast('notifications', 'alert', {
  message: 'New update available!',
  type: 'info'
});
```

## Troubleshooting

### Connection Fails with "Token required"

**Problem:** Socket.IO connection is rejected.

**Solution:**
- Make sure you're providing the token
- Check that the token is correct (64 characters)
- Verify the token exists in the database (register again if needed)

### Not Receiving Broadcasts

**Problem:** Broadcasts are sent but not received.

**Solution:**
1. Verify you're subscribed to the correct channel:
   ```javascript
   socket.emit('subscribe', 'channel-name');
   ```
2. Check that the channel name matches exactly
3. Verify your Socket.IO connection is active:
   ```javascript
   console.log('Connected:', socket.connected);
   ```

### "Invalid or expired token" Error

**Problem:** API requests return 403 error.

**Solution:**
- Token might be incorrect
- Register a new user to get a fresh token
- Check that you're sending the token correctly (header, body, or query)

### Broadcast API Returns 401

**Problem:** Broadcast endpoint returns "Token required".

**Solution:**
- Include token in Authorization header: `Authorization: Bearer <token>`
- Or include in request body: `{ "token": "<token>", ... }`
- Or include in query: `?token=<token>`

## Best Practices

1. **Store tokens securely** - Don't expose tokens in client-side code in production
2. **Use environment variables** - Store tokens in environment variables for backend services
3. **Handle reconnection** - Implement reconnection logic for Socket.IO:
   ```javascript
   socket.on('disconnect', () => {
     // Reconnect logic
     socket.connect();
   });
   ```
4. **Validate data** - Always validate data before sending broadcasts
5. **Use meaningful channel names** - Use descriptive channel names (e.g., `user-123-notifications`)

## Interactive Testing

Use the built-in test page at **http://localhost:6002/test** to:
- Register new users
- Test Socket.IO connections
- Subscribe to channels
- Send and receive broadcasts
- See real-time logs

## Support

For issues or questions, refer to:
- API Documentation: http://localhost:6002/
- Test Page: http://localhost:6002/test
- Server logs for debugging

---

**Author:** ITO I Julieto Ompad  
**Version:** 1.0.0

