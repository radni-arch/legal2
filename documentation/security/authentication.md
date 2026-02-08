# Authentication Documentation

This document describes the authentication system implemented for the AI Legal War Machine application.

## Overview

The application now has a comprehensive authentication system with:
- **Web Authentication**: Session-based authentication for web routes (login/register/logout)
- **API Token Authentication**: Bearer token authentication for API routes
- **MCP Authentication**: Separate authentication system for MCP (Model Context Protocol) routes

## Web Authentication

### Routes

#### Public Routes (Guest Only)
- `GET /login` - Login form
- `POST /login` - Handle login
- `GET /register` - Registration form
- `POST /register` - Handle registration

#### Authenticated Routes (Login Required)
All the following routes require authentication:
- `/dashboard` - Main dashboard
- `/profile` - User profile and API token management
- `/uploader` - File uploader
- `/timeline` - Timeline views
- `/search` - Search interface
- `/openai/logs` - OpenAI logs viewer
- `/ingested-laws` - Ingested laws manager
- `/textract` - Textract pipeline manager
- `/eoglasna` - e-Oglasna monitoring
- `/agent/*` - Agent dashboard and runs

#### Authentication Actions
- `POST /logout` - Logout (requires authentication)

### Features
- Session-based authentication
- Remember me functionality
- Password hashing
- CSRF protection
- Redirect to intended page after login

## API Token Authentication

### Overview
API routes use Bearer token authentication. Users can generate API tokens from their profile page.

### Protected API Routes
The following API endpoints require Bearer token authentication:

#### Search API
```bash
POST /api/search/*
```
- `/api/search` - Unified search
- `/api/search/laws` - Search laws
- `/api/search/decisions` - Search decisions
- `/api/search/cases` - Search cases
- `/api/search/hybrid` - Hybrid search
- `/api/search/with-citations` - Citation-aware search

#### OpenAI Proxy
```bash
POST /api/openai/*
```
All OpenAI proxy endpoints including chat, embeddings, images, TTS, transcription, assistants, and vector stores.

#### Content Ingestion
```bash
POST /api/ingest/*
```
- `/api/ingest/text` - Ingest text
- `/api/ingest/file` - Ingest files
- `/api/ingest/laws` - Ingest legal documents
- `/api/ingest/search` - Search ingested content

#### File Uploads
```bash
POST /api/uploads/*
```
Direct and chunked file upload endpoints.

#### Autonomous Agents
```bash
POST /api/agent/*
```
Agent research run management endpoints.

### Generating an API Token

1. Log in to the web interface
2. Navigate to `/profile`
3. Click "Generate API Token"
4. Copy the token immediately (it won't be shown again)
5. Store the token securely

### Using an API Token

Include the token in the `Authorization` header with the `Bearer` scheme:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -X POST https://your-domain.com/api/search \
  -H "Content-Type: application/json" \
  -d '{"query": "contract law"}'
```

### Token Management

- **Generate New Token**: Generates a new token (replaces the old one if it exists)
- **Revoke Token**: Removes the current token (all API requests using it will fail)
- Each user can have only one active token at a time
- Tokens are 80-character hexadecimal strings
- Tokens are stored hashed in the database

## MCP Authentication

MCP (Model Context Protocol) routes use a separate authentication system with the `X-MCP-Token` header.

### MCP Routes

#### Public (Rate Limited)
- `GET /mcp/info` - MCP server information (60 requests/minute)
- `GET /api/mcp-openai/info` - MCP-OpenAI bridge info

#### Protected (MCP Token Required)
- `POST /mcp/message` - MCP protocol endpoint
- `POST /api/mcp/*` - MCP tool endpoints
  - `law.search` - Search laws
  - `law.get_article` - Get law article
  - `decision.search` - Search decisions
  - `decision.get` - Get decision
  - `case.search` - Search cases (private)
- `POST /api/mcp-openai/*` - MCP-OpenAI bridge endpoints

### Using MCP Authentication

MCP routes accept the token in either format:

```bash
# Using X-MCP-Token header
curl -H "X-MCP-Token: YOUR_MCP_TOKEN" \
  -X POST https://your-domain.com/mcp/message \
  -H "Content-Type: application/json" \
  -d '{"method": "tools/list"}'

# Using Authorization Bearer header
curl -H "Authorization: Bearer YOUR_MCP_TOKEN" \
  -X POST https://your-domain.com/api/mcp/law.search \
  -H "Content-Type: application/json" \
  -d '{"query": "contract law"}'
```

### Configuration

MCP authentication is configured via environment variables:

```env
MCP_API_TOKEN=your_mcp_token_here
MCP_AUTH_ENABLED=true
MCP_RATE_LIMIT_ENABLED=true
```

## Database Schema

### Users Table
```sql
- id: bigint (primary key)
- name: string
- email: string (unique)
- email_verified_at: timestamp (nullable)
- password: string (hashed)
- api_token: string(80) (unique, nullable, indexed)
- remember_token: string (nullable)
- created_at: timestamp
- updated_at: timestamp
```

## Middleware

### Web Middleware
- `auth` - Ensures user is authenticated (redirects to login)
- `guest` - Ensures user is NOT authenticated (redirects to dashboard)

### API Middleware
- `api.token` - Validates Bearer token for API routes
- `mcp.auth` - Validates MCP token (X-MCP-Token or Bearer)
- `throttle` - Rate limiting

## Security Best Practices

1. **Token Security**
   - Store API tokens securely (environment variables, secret managers)
   - Never commit tokens to version control
   - Rotate tokens regularly
   - Revoke compromised tokens immediately

2. **Password Security**
   - Minimum 8 characters required
   - Passwords are hashed using Laravel's default hasher (bcrypt)
   - Remember tokens are used for "remember me" functionality

3. **Rate Limiting**
   - Search API: 60 requests/minute
   - MCP info endpoint: 60 requests/minute
   - MCP tools: Per-tool rate limits configured in `config/services.php`

4. **CSRF Protection**
   - All POST requests from web forms include CSRF tokens
   - API routes are exempt from CSRF (use token authentication instead)

## Testing Authentication

### Test Web Login
1. Visit `/login`
2. Enter credentials
3. Should redirect to `/dashboard`

### Test API Token
```bash
# Generate token from /profile page, then test:
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://your-domain.com/api/search \
  -H "Content-Type: application/json" \
  -d '{"query": "test"}'
```

### Test MCP Authentication
```bash
# Set MCP_API_TOKEN in .env, then test:
curl -H "X-MCP-Token: $MCP_API_TOKEN" \
  https://your-domain.com/mcp/info
```

## Troubleshooting

### "Unauthenticated" Error
- Ensure you're logged in (web routes)
- Verify Bearer token is included (API routes)
- Check token is valid and not revoked
- Verify token matches the one in database

### "Invalid API Token" Error
- Token may have been revoked
- Generate a new token from `/profile`
- Ensure no typos in token
- Check using correct authentication method for endpoint

### MCP Routes Not Working
- Verify `MCP_API_TOKEN` is set in `.env`
- Check `MCP_AUTH_ENABLED=true`
- Use correct header (`X-MCP-Token` or `Authorization: Bearer`)
- Review MCP-specific documentation

## Migration Instructions

### Before Running the Application

1. **Run the migration** to add the `api_token` column:
```bash
php artisan migrate
```

2. **Create a user account**:
   - Visit `/register` in your browser
   - Complete the registration form
   - You'll be automatically logged in

3. **Generate an API token** (if using API routes):
   - Visit `/profile`
   - Click "Generate API Token"
   - Copy and save the token securely

4. **Set MCP token** (if using MCP routes):
```bash
# Add to .env file
MCP_API_TOKEN=your_random_secure_token_here
```

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php        # Web authentication
│   │   └── ProfileController.php     # API token management
│   └── Middleware/
│       ├── ApiTokenAuth.php          # API token authentication
│       ├── McpAuth.php               # MCP authentication with rate limiting
│       └── McpApiTokenAuth.php       # Simple MCP token validation

resources/
└── views/
    ├── auth/
    │   ├── login.blade.php           # Login page
    │   └── register.blade.php        # Registration page
    └── profile/
        └── show.blade.php            # Profile & API token management

routes/
├── web.php                           # Web routes with auth middleware
└── api.php                           # API routes with token auth

database/
└── migrations/
    └── 2025_10_28_000001_add_api_token_to_users_table.php

bootstrap/
└── app.php                           # Middleware registration
```

## Support

For issues or questions:
1. Check this documentation
2. Review Laravel authentication documentation
3. Check application logs in `storage/logs/`
4. Verify environment configuration in `.env`
