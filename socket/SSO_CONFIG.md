# SSO Configuration Guide

This document explains how to configure Single Sign-On (SSO) authentication for the Socket.IO server.

## Overview

The `/test` route is now protected with SSO authentication. Users must authenticate via the SSO provider before accessing the test page.

## Configuration

SSO settings can be configured via environment variables or by modifying the `SSO_CONFIG` object in `server-api.js`.

### Environment Variables

Create a `.env` file in the project root (or set environment variables) with the following:

```bash
# SSO Provider Configuration
SSO_BASE_URL=https://caraga-connect.dswd.gov.ph
SSO_LOGIN_URL=https://caraga-connect.dswd.gov.ph/sso/login
SSO_CALLBACK_URL=http://localhost:6002/sso/callback
SSO_VALIDATE_URL=https://caraga-connect.dswd.gov.ph/sso/validate
SSO_LOGOUT_URL=https://caraga-connect.dswd.gov.ph/sso/logout

# OAuth Parameters (REQUIRED)
SSO_CLIENT_ID=your-client-id-here
SSO_CLIENT_SECRET=your-client-secret-here  # Optional, if required by provider
SSO_RESPONSE_TYPE=code  # Usually 'code' for authorization code flow
SSO_SCOPE=openid profile email  # Adjust based on provider requirements

# Session Configuration
SESSION_SECRET=your-strong-random-secret-key-here
SESSION_NAME=sso.session

# Environment
NODE_ENV=production
```

### SSO Endpoints

1. **SSO Login** (`/sso/login`)
   - Redirects users to the SSO provider's login page
   - Stores the original destination URL for redirect after login

2. **SSO Callback** (`/sso/callback`)
   - Handles the SSO provider's response
   - Validates the token/code with the SSO provider
   - Creates a session for authenticated users
   - Redirects to the original destination

3. **SSO Logout** (`/sso/logout`)
   - Destroys the local session
   - Redirects to SSO provider's logout (if configured)
   - Falls back to home page

## OAuth Parameters

The SSO provider requires the following OAuth 2.0 parameters:

### Required Parameters
- **client_id** - Your application's client ID (obtain from SSO provider)
- **redirect_uri** - Where to redirect after authentication (must match registered callback URL)

### Optional Parameters
- **response_type** - Usually `code` for authorization code flow (default: `code`)
- **scope** - Requested permissions (default: `openid profile email`)
- **state** - CSRF protection token (automatically generated)

## SSO Provider Integration

The implementation supports OAuth 2.0 / OpenID Connect style SSO flows. The callback handler expects:

### Query Parameters
- `code` - Authorization code from SSO provider (for authorization code flow)
- `token` - Direct token (if provider uses implicit flow)
- `state` - State parameter for CSRF protection
- `error` - Error code if authentication failed
- `error_description` - Error description if authentication failed

### Token Validation

The server validates tokens by calling the `SSO_VALIDATE_URL` endpoint with the token as a query parameter:

```
GET {SSO_VALIDATE_URL}?token={token}
```

### Expected Response Format

The validation endpoint should return JSON in this format:

```json
{
  "user": {
    "id": "user-id",
    "email": "user@example.com",
    "name": "User Name",
    "display_name": "Display Name"
  }
}
```

## Customization

If your SSO provider uses a different format, modify the callback handler in `server-api.js`:

1. Update the `SSO_CONFIG` object with your provider's endpoints
2. Adjust the token validation logic in `/sso/callback` route
3. Modify the user data extraction based on your provider's response format

## Testing

### Development Mode

In development mode (`NODE_ENV !== 'production'`), if SSO validation fails, the server will create a fallback session for testing purposes. **Remove this in production!**

### Production

1. Ensure all SSO endpoints are correctly configured
2. Set `NODE_ENV=production`
3. Use a strong `SESSION_SECRET`
4. Enable HTTPS and set `secure: true` in session cookies

## Troubleshooting

### Users can't access /test

- Check that SSO endpoints are correctly configured
- Verify the SSO provider is accessible
- Check server logs for SSO validation errors
- Ensure cookies are enabled in the browser

### SSO validation fails

- Verify the `SSO_VALIDATE_URL` endpoint exists and is accessible
- Check the response format matches expected structure
- Review server logs for detailed error messages
- Test the validation endpoint directly with a valid token

### Session not persisting

- Check cookie settings (secure flag, domain, path)
- Verify `SESSION_SECRET` is set and consistent
- Ensure browser allows cookies for the domain
- Check session store configuration

## Security Notes

1. **Session Secret**: Use a strong, random secret (at least 32 characters)
2. **HTTPS**: Always use HTTPS in production
3. **Cookie Security**: Enable `secure` flag in production
4. **Token Validation**: Always validate tokens with the SSO provider
5. **Session Timeout**: Sessions expire after 24 hours by default

## References

- SSO Documentation: https://caraga-connect.dswd.gov.ph/docs/sso
- Express Session: https://github.com/expressjs/session
- OAuth 2.0: https://oauth.net/2/

