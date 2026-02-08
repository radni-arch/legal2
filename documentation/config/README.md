# Configuration Guide

Complete reference for all environment variables in the AI Legal War Machine system. This guide documents all 325+ configuration variables organized by category.

## Table of Contents

- [Required Variables (Production)](#required-variables-production)
  - [Application Core](#application-core)
  - [Database Configuration](#database-configuration)
  - [Neo4j Graph Database](#neo4j-graph-database)
  - [OpenAI API](#openai-api)
  - [AWS Services](#aws-services)
  - [Google Drive Integration](#google-drive-integration)
- [Optional Variables](#optional-variables)
  - [Vizra ADK Configuration](#vizra-adk-configuration)
  - [Agent Framework](#agent-framework)
  - [MCP (Model Context Protocol)](#mcp-model-context-protocol)
  - [Feature Flags](#feature-flags)
  - [External APIs](#external-apis)
- [Environment Setup](#environment-setup)
  - [Development Setup](#development-setup)
  - [Production Setup](#production-setup)
  - [Docker Setup](#docker-setup)
- [Integration Guides](#integration-guides)
  - [Neo4j Setup](#neo4j-setup)
  - [OpenAI Setup](#openai-setup)
  - [AWS Textract Setup](#aws-textract-setup)
  - [Google Drive Setup](#google-drive-setup)

---

## Required Variables (Production)

These variables MUST be configured for production deployment.

### Application Core

#### APP_NAME
- **Description**: Application name used in UI elements and notifications
- **Type**: string
- **Required**: Yes (Production)
- **Default**: `Laravel`
- **Example**: `AI-Legal-War-Machine`
- **Environment**: dev/prod

#### APP_ENV
- **Description**: Application environment mode
- **Type**: string
- **Required**: Yes
- **Default**: `production`
- **Example**: `local`, `staging`, `production`
- **Environment**: dev/prod

#### APP_KEY
- **Description**: Application encryption key (32 character string)
- **Type**: string
- **Required**: Yes
- **Default**: None (must be generated)
- **Example**: `base64:GENERATED_KEY_HERE`
- **Environment**: dev/prod
- **Note**: Generate using `php artisan key:generate`

#### APP_DEBUG
- **Description**: Enable debug mode with detailed error messages
- **Type**: boolean
- **Required**: Yes
- **Default**: `false`
- **Example**: `true` (dev), `false` (prod)
- **Environment**: dev/prod
- **Warning**: MUST be `false` in production

#### APP_URL
- **Description**: Base URL of the application
- **Type**: string
- **Required**: Yes
- **Default**: `http://localhost`
- **Example**: `https://legal.example.com`
- **Environment**: dev/prod

#### APP_LOCALE
- **Description**: Default application locale
- **Type**: string
- **Required**: No
- **Default**: `en`
- **Example**: `en`, `hr`
- **Environment**: dev/prod

#### APP_FALLBACK_LOCALE
- **Description**: Fallback locale when translation missing
- **Type**: string
- **Required**: No
- **Default**: `en`
- **Example**: `en`
- **Environment**: dev/prod

#### APP_FAKER_LOCALE
- **Description**: Locale for Faker data generation
- **Type**: string
- **Required**: No
- **Default**: `en_US`
- **Example**: `en_US`, `hr_HR`
- **Environment**: dev

#### APP_MAINTENANCE_DRIVER
- **Description**: Maintenance mode storage driver
- **Type**: string
- **Required**: No
- **Default**: `file`
- **Example**: `file`, `cache`
- **Environment**: dev/prod

#### APP_MAINTENANCE_STORE
- **Description**: Cache store for maintenance mode (when driver=cache)
- **Type**: string
- **Required**: No
- **Default**: `database`
- **Example**: `redis`, `database`
- **Environment**: dev/prod

#### APP_PREVIOUS_KEYS
- **Description**: Comma-separated list of previous encryption keys for rotation
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `base64:OLD_KEY_1,base64:OLD_KEY_2`
- **Environment**: prod

#### PHP_CLI_SERVER_WORKERS
- **Description**: Number of PHP CLI server workers
- **Type**: integer
- **Required**: No
- **Default**: `4`
- **Example**: `4`, `8`
- **Environment**: dev

#### BCRYPT_ROUNDS
- **Description**: BCrypt hashing cost factor
- **Type**: integer
- **Required**: No
- **Default**: `12`
- **Example**: `12`, `14`
- **Environment**: dev/prod

---

### Database Configuration

#### DB_CONNECTION
- **Description**: Default database connection driver
- **Type**: string
- **Required**: Yes
- **Default**: `sqlite`
- **Example**: `pgsql`, `mysql`, `sqlite`
- **Environment**: dev/prod
- **Note**: Use `pgsql` for production with pgvector support

#### DB_HOST
- **Description**: Database server hostname
- **Type**: string
- **Required**: Yes (for mysql/pgsql)
- **Default**: `127.0.0.1`
- **Example**: `localhost`, `db.example.com`
- **Environment**: dev/prod

#### DB_PORT
- **Description**: Database server port
- **Type**: integer
- **Required**: Yes (for mysql/pgsql)
- **Default**: `5432` (pgsql), `3306` (mysql)
- **Example**: `5432`
- **Environment**: dev/prod

#### DB_DATABASE
- **Description**: Database name
- **Type**: string
- **Required**: Yes (for mysql/pgsql)
- **Default**: `laravel`
- **Example**: `ai_legal_war_machine`
- **Environment**: dev/prod

#### DB_USERNAME
- **Description**: Database username
- **Type**: string
- **Required**: Yes (for mysql/pgsql)
- **Default**: `root`
- **Example**: `postgres`, `app_user`
- **Environment**: dev/prod

#### DB_PASSWORD
- **Description**: Database password
- **Type**: string
- **Required**: Yes (Production)
- **Default**: None
- **Example**: `secure_password_here`
- **Environment**: dev/prod

#### DB_URL
- **Description**: Full database connection URL (alternative to individual params)
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `postgresql://user:pass@host:5432/dbname`
- **Environment**: dev/prod

#### DB_CHARSET
- **Description**: Database character set
- **Type**: string
- **Required**: No
- **Default**: `utf8` (pgsql), `utf8mb4` (mysql)
- **Example**: `utf8mb4`
- **Environment**: dev/prod

#### DB_COLLATION
- **Description**: Database collation (MySQL only)
- **Type**: string
- **Required**: No
- **Default**: `utf8mb4_unicode_ci`
- **Example**: `utf8mb4_unicode_ci`
- **Environment**: dev/prod

#### DB_FOREIGN_KEYS
- **Description**: Enable foreign key constraints (SQLite)
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev

#### DB_SOCKET
- **Description**: MySQL Unix socket path
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `/var/run/mysqld/mysqld.sock`
- **Environment**: dev/prod

#### MYSQL_ATTR_SSL_CA
- **Description**: MySQL SSL certificate authority path
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `/path/to/ca-cert.pem`
- **Environment**: prod

#### DB_CACHE_CONNECTION
- **Description**: Database connection for cache storage
- **Type**: string
- **Required**: No
- **Default**: None (uses default connection)
- **Example**: `pgsql`
- **Environment**: dev/prod

#### DB_CACHE_TABLE
- **Description**: Database table name for cache storage
- **Type**: string
- **Required**: No
- **Default**: `cache`
- **Example**: `cache`
- **Environment**: dev/prod

#### DB_CACHE_LOCK_CONNECTION
- **Description**: Database connection for cache locks
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `pgsql`
- **Environment**: dev/prod

#### DB_CACHE_LOCK_TABLE
- **Description**: Database table name for cache locks
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `cache_locks`
- **Environment**: dev/prod

#### DB_QUEUE_CONNECTION
- **Description**: Database connection for queue storage
- **Type**: string
- **Required**: No
- **Default**: None (uses default connection)
- **Example**: `pgsql`
- **Environment**: dev/prod

#### DB_QUEUE_TABLE
- **Description**: Database table name for queue jobs
- **Type**: string
- **Required**: No
- **Default**: `jobs`
- **Example**: `jobs`
- **Environment**: dev/prod

#### DB_QUEUE
- **Description**: Default queue name for database driver
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `default`, `high-priority`
- **Environment**: dev/prod

#### DB_QUEUE_RETRY_AFTER
- **Description**: Seconds before retrying failed database queue jobs
- **Type**: integer
- **Required**: No
- **Default**: `90`
- **Example**: `90`, `180`
- **Environment**: dev/prod

#### DATABASE_URL
- **Description**: Full database URL (used by some MCP servers)
- **Type**: string
- **Required**: No (Required for MCP Postgres server)
- **Default**: None
- **Example**: `postgresql://user:pass@localhost:5432/dbname`
- **Environment**: dev/prod

---

### Neo4j Graph Database

#### NEO4J_CONNECTION
- **Description**: Neo4j connection type
- **Type**: string
- **Required**: No
- **Default**: `bolt`
- **Example**: `bolt`, `http`
- **Environment**: dev/prod

#### NEO4J_URI
- **Description**: Full Neo4j connection URI
- **Type**: string
- **Required**: Yes (Production)
- **Default**: `bolt://localhost:7687`
- **Example**: `bolt://localhost:7687`, `neo4j://host:7687`
- **Environment**: dev/prod

#### NEO4J_HOST
- **Description**: Neo4j server hostname
- **Type**: string
- **Required**: Yes (Production)
- **Default**: `localhost`
- **Example**: `localhost`, `neo4j.example.com`
- **Environment**: dev/prod

#### NEO4J_PORT
- **Description**: Neo4j Bolt protocol port
- **Type**: integer
- **Required**: Yes (Production)
- **Default**: `7687`
- **Example**: `7687`
- **Environment**: dev/prod

#### NEO4J_HTTP_PORT
- **Description**: Neo4j HTTP API port
- **Type**: integer
- **Required**: No
- **Default**: `7474`
- **Example**: `7474`
- **Environment**: dev/prod

#### NEO4J_USER
- **Description**: Neo4j username (legacy, use NEO4J_USERNAME)
- **Type**: string
- **Required**: Yes (Production)
- **Default**: `neo4j`
- **Example**: `neo4j`, `app_user`
- **Environment**: dev/prod

#### NEO4J_USERNAME
- **Description**: Neo4j authentication username
- **Type**: string
- **Required**: Yes (Production)
- **Default**: `neo4j`
- **Example**: `neo4j`, `graph_user`
- **Environment**: dev/prod

#### NEO4J_PASSWORD
- **Description**: Neo4j authentication password
- **Type**: string
- **Required**: Yes (Production)
- **Default**: `secret`
- **Example**: `secure_password_here`
- **Environment**: dev/prod
- **Warning**: Change default password immediately

#### NEO4J_DATABASE
- **Description**: Neo4j database name (4.x+)
- **Type**: string
- **Required**: No
- **Default**: `neo4j`
- **Example**: `neo4j`, `legal_graph`
- **Environment**: dev/prod

#### NEO4J_SIMILARITY_THRESHOLD
- **Description**: Minimum similarity score for creating graph relationships
- **Type**: float
- **Required**: No
- **Default**: `0.85`
- **Example**: `0.85`, `0.90`
- **Environment**: dev/prod
- **Range**: `0.70` to `1.0`

#### NEO4J_SYNC_BATCH_SIZE
- **Description**: Number of records to sync per batch
- **Type**: integer
- **Required**: No
- **Default**: `100`
- **Example**: `50`, `200`
- **Environment**: dev/prod

#### NEO4J_AUTO_SYNC
- **Description**: Enable automatic sync from relational DB to graph DB
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### NEO4J_ENABLED
- **Description**: Master switch to enable Neo4j integration
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### NEO4J_AUTO_SYNC_ON_INGEST
- **Description**: Automatically sync to graph on document ingest
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### NEO4J_UPDATE_RELATIONSHIPS
- **Description**: Update existing relationships during sync
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

---

### OpenAI API

#### OPENAI_API_KEY
- **Description**: OpenAI API authentication key
- **Type**: string
- **Required**: Yes (Production)
- **Default**: None
- **Example**: `sk-proj-xxxxxxxxxxxxx`
- **Environment**: dev/prod
- **Note**: Get from https://platform.openai.com/api-keys

#### OPENAI_ORG
- **Description**: OpenAI organization ID
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `org-xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### OPENAI_PROJECT
- **Description**: OpenAI project ID
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `proj-xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### OPENAI_BASE_URL
- **Description**: OpenAI API base URL
- **Type**: string
- **Required**: No
- **Default**: `https://api.openai.com/v1`
- **Example**: `https://api.openai.com/v1`
- **Environment**: dev/prod
- **Note**: Override for custom endpoints or proxies

#### OPENAI_RESPONSES_MODEL
- **Description**: Default model for response generation
- **Type**: string
- **Required**: No
- **Default**: `gpt-4.1-mini`
- **Example**: `gpt-4o`, `gpt-4-turbo`
- **Environment**: dev/prod

#### OPENAI_CHAT_MODEL
- **Description**: Default model for chat completions
- **Type**: string
- **Required**: No
- **Default**: `gpt-4o-mini`
- **Example**: `gpt-4o`, `gpt-4-turbo`
- **Environment**: dev/prod

#### OPENAI_EMBEDDINGS_MODEL
- **Description**: Default model for embeddings generation
- **Type**: string
- **Required**: No
- **Default**: `text-embedding-3-small`
- **Example**: `text-embedding-3-small`, `text-embedding-3-large`
- **Environment**: dev/prod

#### OPENAI_IMAGE_MODEL
- **Description**: Default model for image generation
- **Type**: string
- **Required**: No
- **Default**: `gpt-image-1`
- **Example**: `dall-e-3`, `gpt-image-1`
- **Environment**: dev/prod

#### OPENAI_STT_MODEL
- **Description**: Default model for speech-to-text
- **Type**: string
- **Required**: No
- **Default**: `whisper-1`
- **Example**: `whisper-1`
- **Environment**: dev/prod

#### OPENAI_TTS_MODEL
- **Description**: Default model for text-to-speech
- **Type**: string
- **Required**: No
- **Default**: `gpt-4o-mini-tts`
- **Example**: `tts-1`, `tts-1-hd`
- **Environment**: dev/prod

#### OPENAI_TIMEOUT
- **Description**: HTTP request timeout in seconds
- **Type**: integer
- **Required**: No
- **Default**: `60`
- **Example**: `60`, `120`
- **Environment**: dev/prod

#### OPENAI_CONNECT_TIMEOUT
- **Description**: HTTP connection timeout in seconds
- **Type**: integer
- **Required**: No
- **Default**: `10`
- **Example**: `10`, `20`
- **Environment**: dev/prod

#### OPENAI_RETRY_TIMES
- **Description**: Number of retry attempts for failed requests
- **Type**: integer
- **Required**: No
- **Default**: `2`
- **Example**: `2`, `3`
- **Environment**: dev/prod

#### OPENAI_RETRY_SLEEP_MS
- **Description**: Milliseconds to wait between retries
- **Type**: integer
- **Required**: No
- **Default**: `200`
- **Example**: `200`, `500`
- **Environment**: dev/prod

---

### AWS Services

#### AWS_ACCESS_KEY_ID
- **Description**: AWS IAM access key ID
- **Type**: string
- **Required**: Yes (Production)
- **Default**: None
- **Example**: `AKIAIOSFODNN7EXAMPLE`
- **Environment**: dev/prod
- **Note**: Required for S3 and Textract

#### AWS_SECRET_ACCESS_KEY
- **Description**: AWS IAM secret access key
- **Type**: string
- **Required**: Yes (Production)
- **Default**: None
- **Example**: `wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY`
- **Environment**: dev/prod
- **Warning**: Never commit this to version control

#### AWS_DEFAULT_REGION
- **Description**: AWS region for services
- **Type**: string
- **Required**: Yes (Production)
- **Default**: `us-east-1`
- **Example**: `us-east-1`, `eu-west-1`
- **Environment**: dev/prod

#### AWS_BUCKET
- **Description**: S3 bucket name for file storage
- **Type**: string
- **Required**: Yes (Production)
- **Default**: None
- **Example**: `my-legal-documents`
- **Environment**: dev/prod

#### AWS_URL
- **Description**: Custom S3 endpoint URL
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `https://s3.amazonaws.com`
- **Environment**: dev/prod

#### AWS_ENDPOINT
- **Description**: Custom AWS endpoint (for S3-compatible services)
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `https://minio.example.com`
- **Environment**: dev/prod

#### AWS_USE_PATH_STYLE_ENDPOINT
- **Description**: Use path-style S3 URLs instead of virtual-hosted-style
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true` (for MinIO), `false` (for AWS S3)
- **Environment**: dev/prod

#### S3_INPUT_PREFIX
- **Description**: S3 prefix for Textract input documents
- **Type**: string
- **Required**: No
- **Default**: `textract/input`
- **Example**: `textract/input`, `documents/incoming`
- **Environment**: dev/prod

#### S3_OUTPUT_PREFIX
- **Description**: S3 prefix for Textract output results
- **Type**: string
- **Required**: No
- **Default**: `textract/output`
- **Example**: `textract/output`, `documents/processed`
- **Environment**: dev/prod

#### S3_JSON_PREFIX
- **Description**: S3 prefix for Textract JSON results
- **Type**: string
- **Required**: No
- **Default**: `textract/json`
- **Example**: `textract/json`, `documents/json`
- **Environment**: dev/prod

#### S3_TABLES_PREFIX
- **Description**: S3 prefix for extracted table data
- **Type**: string
- **Required**: No
- **Default**: `textract/tables`
- **Example**: `textract/tables`, `documents/tables`
- **Environment**: dev/prod

---

### Google Drive Integration

#### GOOGLE_APPLICATION_CREDENTIALS
- **Description**: Absolute path to Google service account JSON file
- **Type**: string
- **Required**: Yes (if using Google Drive)
- **Default**: None
- **Example**: `/app/config/service-account.json`
- **Environment**: dev/prod
- **Note**: Service account must have Drive API enabled

#### GOOGLE_DRIVE_FOLDER_ID
- **Description**: Google Drive folder ID for document storage
- **Type**: string
- **Required**: Yes (if using Google Drive)
- **Default**: None
- **Example**: `1a2b3c4d5e6f7g8h9i0j`
- **Environment**: dev/prod
- **Note**: Get from folder URL in Drive

#### GOOGLE_IMPERSONATE_USER
- **Description**: Email address to impersonate for domain-wide delegation
- **Type**: string
- **Required**: No (Required for domain-wide delegation)
- **Default**: None
- **Example**: `user@example.com`
- **Environment**: dev/prod

---

## Optional Variables

These variables are optional and have sensible defaults.

### Vizra ADK Configuration

Vizra ADK is the Agent Development Kit providing LLM integration, RAG, and agent capabilities.

#### VIZRA_ADK_ENABLED
- **Description**: Master switch to enable/disable Vizra ADK
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_LOGGING_ENABLED
- **Description**: Enable logging for Vizra ADK
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_LOGGING_LEVEL
- **Description**: Minimum log level for Vizra ADK
- **Type**: string
- **Required**: No
- **Default**: `warning`
- **Example**: `debug`, `info`, `warning`, `error`, `critical`, `none`
- **Environment**: dev/prod

#### VIZRA_ADK_LOG_VECTOR_MEMORY
- **Description**: Enable logging for vector memory operations
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_LOG_AGENTS
- **Description**: Enable logging for agent operations
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_LOG_TOOLS
- **Description**: Enable logging for tool executions
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_LOG_MCP
- **Description**: Enable logging for MCP operations
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_LOG_TRACES
- **Description**: Enable logging for execution traces
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_DEFAULT_PROVIDER
- **Description**: Default LLM provider for Vizra ADK
- **Type**: string
- **Required**: No
- **Default**: `google`
- **Example**: `openai`, `anthropic`, `google`, `gemini`, `deepseek`, `ollama`, `mistral`, `groq`, `xai`, `grok`, `voyageai`, `openrouter`
- **Environment**: dev/prod

#### VIZRA_ADK_DEFAULT_MODEL
- **Description**: Default LLM model name
- **Type**: string
- **Required**: No
- **Default**: `gemini-1.5-flash`
- **Example**: `gpt-4o`, `claude-3-opus-20240229`, `gemini-1.5-pro`
- **Environment**: dev/prod

#### VIZRA_ADK_DEFAULT_TEMPERATURE
- **Description**: Default temperature for LLM generation
- **Type**: float
- **Required**: No
- **Default**: None (provider default)
- **Example**: `0.7`, `1.0`
- **Environment**: dev/prod
- **Range**: `0.0` to `2.0`

#### VIZRA_ADK_DEFAULT_MAX_TOKENS
- **Description**: Default maximum tokens for LLM generation
- **Type**: integer
- **Required**: No
- **Default**: None (provider default)
- **Example**: `1000`, `4000`
- **Environment**: dev/prod

#### VIZRA_ADK_DEFAULT_TOP_P
- **Description**: Default top-p (nucleus sampling) for LLM generation
- **Type**: float
- **Required**: No
- **Default**: None (provider default)
- **Example**: `0.9`, `1.0`
- **Environment**: dev/prod
- **Range**: `0.0` to `1.0`

#### VIZRA_ADK_HTTP_TIMEOUT
- **Description**: Total timeout for LLM API calls in seconds
- **Type**: integer
- **Required**: No
- **Default**: `120`
- **Example**: `120`, `300`
- **Environment**: dev/prod

#### VIZRA_ADK_HTTP_CONNECT_TIMEOUT
- **Description**: Connection timeout for LLM API calls in seconds
- **Type**: integer
- **Required**: No
- **Default**: `10`
- **Example**: `10`, `30`
- **Environment**: dev/prod

#### VIZRA_ADK_MAX_DELEGATION_DEPTH
- **Description**: Maximum depth for nested sub-agent delegation
- **Type**: integer
- **Required**: No
- **Default**: `5`
- **Example**: `3`, `10`
- **Environment**: dev/prod

#### VIZRA_ADK_TRACING_ENABLED
- **Description**: Enable execution tracing for debugging
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_TRACING_CLEANUP_DAYS
- **Description**: Days to retain trace data before cleanup
- **Type**: integer
- **Required**: No
- **Default**: `30`
- **Example**: `7`, `90`
- **Environment**: dev/prod

#### VIZRA_ADK_WEB_ENABLED
- **Description**: Enable Vizra ADK web interface
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_OPENAI_GPT4_AGENT
- **Description**: Agent name to use for GPT-4 model mapping
- **Type**: string
- **Required**: No
- **Default**: `chat_agent`
- **Example**: `chat_agent`, `legal_assistant`
- **Environment**: dev/prod

#### VIZRA_ADK_OPENAI_GPT4_TURBO_AGENT
- **Description**: Agent name to use for GPT-4 Turbo model mapping
- **Type**: string
- **Required**: No
- **Default**: `chat_agent`
- **Example**: `chat_agent`
- **Environment**: dev/prod

#### VIZRA_ADK_OPENAI_GPT35_AGENT
- **Description**: Agent name to use for GPT-3.5 Turbo model mapping
- **Type**: string
- **Required**: No
- **Default**: `chat_agent`
- **Example**: `chat_agent`
- **Environment**: dev/prod

#### VIZRA_ADK_OPENAI_GPT4O_AGENT
- **Description**: Agent name to use for GPT-4o model mapping
- **Type**: string
- **Required**: No
- **Default**: `chat_agent`
- **Example**: `chat_agent`
- **Environment**: dev/prod

#### VIZRA_ADK_OPENAI_GPT4O_MINI_AGENT
- **Description**: Agent name to use for GPT-4o Mini model mapping
- **Type**: string
- **Required**: No
- **Default**: `chat_agent`
- **Example**: `chat_agent`
- **Environment**: dev/prod

#### VIZRA_ADK_DEFAULT_CHAT_AGENT
- **Description**: Default agent when no model mapping found
- **Type**: string
- **Required**: No
- **Default**: `chat_agent`
- **Example**: `chat_agent`
- **Environment**: dev/prod

#### VIZRA_ADK_PROMPTS_USE_DATABASE
- **Description**: Store prompts in database for dynamic updates
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_PROMPTS_PATH
- **Description**: Path for file-based prompt storage
- **Type**: string
- **Required**: No
- **Default**: `resources/prompts`
- **Example**: `resources/prompts`
- **Environment**: dev/prod

#### VIZRA_ADK_PROMPTS_TRACK_USAGE
- **Description**: Track which prompt versions are used
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_PROMPTS_CACHE_TTL
- **Description**: Cache TTL for database prompts in seconds
- **Type**: integer
- **Required**: No
- **Default**: `300`
- **Example**: `0` (disabled), `600`
- **Environment**: dev/prod

#### VIZRA_ADK_PROMPTS_DEFAULT_VERSION
- **Description**: Default prompt version when none specified
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `latest`, `default`, `v1.0`
- **Environment**: dev/prod

#### VIZRA_ADK_OCR_MIN_CONFIDENCE
- **Description**: Minimum OCR confidence score (0.0-1.0)
- **Type**: float
- **Required**: No
- **Default**: `0.82`
- **Example**: `0.80`, `0.90`
- **Environment**: dev/prod

#### VIZRA_ADK_OCR_MIN_COVERAGE
- **Description**: Minimum text coverage percentage (0.0-1.0)
- **Type**: float
- **Required**: No
- **Default**: `0.75`
- **Example**: `0.70`, `0.80`
- **Environment**: dev/prod

#### VIZRA_ADK_OCR_MAX_LOW_CONF_PAGES
- **Description**: Maximum pages with low confidence before flagging
- **Type**: integer
- **Required**: No
- **Default**: `3`
- **Example**: `3`, `5`
- **Environment**: dev/prod

#### VIZRA_ADK_OCR_SKIP_EMBEDDING_ON_LOW_QUALITY
- **Description**: Skip embedding when OCR quality is below threshold
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_VECTOR_ENABLED
- **Description**: Enable vector memory functionality
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### VIZRA_ADK_VECTOR_DRIVER
- **Description**: Vector storage driver
- **Type**: string
- **Required**: No
- **Default**: `pgvector`
- **Example**: `pgvector`, `meilisearch`
- **Environment**: dev/prod

#### VIZRA_ADK_EMBEDDING_PROVIDER
- **Description**: Embedding provider for vector generation
- **Type**: string
- **Required**: No
- **Default**: `openai`
- **Example**: `openai`, `cohere`, `ollama`, `gemini`
- **Environment**: dev/prod

#### VIZRA_ADK_OPENAI_EMBEDDING_MODEL
- **Description**: OpenAI embedding model name
- **Type**: string
- **Required**: No
- **Default**: `text-embedding-3-small`
- **Example**: `text-embedding-3-small`, `text-embedding-3-large`
- **Environment**: dev/prod

#### VIZRA_ADK_COHERE_EMBEDDING_MODEL
- **Description**: Cohere embedding model name
- **Type**: string
- **Required**: No
- **Default**: `embed-english-v3.0`
- **Example**: `embed-english-v3.0`, `embed-multilingual-v3.0`
- **Environment**: dev/prod

#### VIZRA_ADK_OLLAMA_EMBEDDING_MODEL
- **Description**: Ollama embedding model name
- **Type**: string
- **Required**: No
- **Default**: `nomic-embed-text`
- **Example**: `nomic-embed-text`, `mxbai-embed-large`
- **Environment**: dev/prod

#### VIZRA_ADK_GEMINI_EMBEDDING_MODEL
- **Description**: Gemini embedding model name
- **Type**: string
- **Required**: No
- **Default**: `text-embedding-004`
- **Example**: `text-embedding-004`
- **Environment**: dev/prod

#### VIZRA_ADK_PGVECTOR_CONNECTION
- **Description**: PostgreSQL connection name for pgvector
- **Type**: string
- **Required**: No
- **Default**: `pgsql`
- **Example**: `pgsql`
- **Environment**: dev/prod

#### VIZRA_ADK_CHUNK_STRATEGY
- **Description**: Text chunking strategy
- **Type**: string
- **Required**: No
- **Default**: `sentence`
- **Example**: `sentence`, `paragraph`
- **Environment**: dev/prod

#### VIZRA_ADK_CHUNK_SIZE
- **Description**: Characters per chunk
- **Type**: integer
- **Required**: No
- **Default**: `1000`
- **Example**: `500`, `2000`
- **Environment**: dev/prod

#### VIZRA_ADK_CHUNK_OVERLAP
- **Description**: Overlap between chunks in characters
- **Type**: integer
- **Required**: No
- **Default**: `200`
- **Example**: `100`, `300`
- **Environment**: dev/prod

#### VIZRA_ADK_RAG_MAX_CONTEXT
- **Description**: Maximum context length for RAG in characters
- **Type**: integer
- **Required**: No
- **Default**: `4000`
- **Example**: `2000`, `8000`
- **Environment**: dev/prod

#### VIZRA_ADK_RAG_INCLUDE_METADATA
- **Description**: Include metadata in RAG context
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

---

### Agent Framework

Configuration for autonomous research agents.

#### AGENT_MAX_ITERATIONS
- **Description**: Maximum iterations per research run
- **Type**: integer
- **Required**: No
- **Default**: `10`
- **Example**: `5`, `20`
- **Environment**: dev/prod

#### AGENT_TIME_LIMIT
- **Description**: Time limit in seconds for agent execution
- **Type**: integer
- **Required**: No
- **Default**: `600` (10 minutes)
- **Example**: `300`, `1800`
- **Environment**: dev/prod

#### AGENT_THRESHOLD
- **Description**: Quality threshold for evaluation (0-1)
- **Type**: float
- **Required**: No
- **Default**: `0.75`
- **Example**: `0.70`, `0.85`
- **Environment**: dev/prod

#### AGENT_TOKEN_BUDGET
- **Description**: Maximum tokens per run (null = unlimited)
- **Type**: integer
- **Required**: No
- **Default**: None
- **Example**: `10000`, `50000`
- **Environment**: dev/prod

#### AGENT_COST_BUDGET
- **Description**: Maximum cost in USD per run (null = unlimited)
- **Type**: float
- **Required**: No
- **Default**: None
- **Example**: `1.00`, `5.00`
- **Environment**: dev/prod

#### AGENT_SCHEDULE_CRON
- **Description**: Scheduled research frequency
- **Type**: string
- **Required**: No
- **Default**: `weekly`
- **Example**: `daily`, `weekly`, `monthly`
- **Environment**: dev/prod

#### AGENT_SCHEDULE_DAY
- **Description**: Day of week for scheduled runs (0-6, 0=Sunday)
- **Type**: integer
- **Required**: No
- **Default**: `0`
- **Example**: `0`, `1`, `6`
- **Environment**: dev/prod

#### AGENT_SCHEDULE_TIME
- **Description**: Time of day for scheduled runs (24-hour format)
- **Type**: string
- **Required**: No
- **Default**: `02:00`
- **Example**: `02:00`, `14:30`
- **Environment**: dev/prod

#### AGENT_ASYNC_EXECUTION
- **Description**: Enable async execution via jobs
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### AGENT_CACHE_ENABLED
- **Description**: Cache vector search results
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### AGENT_CACHE_TTL
- **Description**: Cache TTL in seconds
- **Type**: integer
- **Required**: No
- **Default**: `3600` (1 hour)
- **Example**: `1800`, `7200`
- **Environment**: dev/prod

#### AGENT_QUEUE
- **Description**: Queue name for agent jobs
- **Type**: string
- **Required**: No
- **Default**: `agents`
- **Example**: `agents`, `research`
- **Environment**: dev/prod

#### AGENT_QUEUE_CONNECTION
- **Description**: Queue connection for agent jobs
- **Type**: string
- **Required**: No
- **Default**: None (uses default)
- **Example**: `redis`, `database`
- **Environment**: dev/prod

#### AGENT_LOG_LEVEL
- **Description**: Log level for agent operations
- **Type**: string
- **Required**: No
- **Default**: `info`
- **Example**: `debug`, `info`, `warning`, `error`
- **Environment**: dev/prod

#### AGENT_LOG_CHANNEL
- **Description**: Log channel for agent operations
- **Type**: string
- **Required**: No
- **Default**: `stack`
- **Example**: `stack`, `single`, `daily`
- **Environment**: dev/prod

#### AGENT_LOG_ITERATIONS
- **Description**: Enable detailed iteration logging
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### AGENT_LOG_ACTIONS
- **Description**: Enable action result logging
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### AGENT_MAX_COST
- **Description**: Maximum allowed cost per run (safety limit in USD)
- **Type**: float
- **Required**: No
- **Default**: `5.00`
- **Example**: `2.00`, `10.00`
- **Environment**: dev/prod

#### AGENT_MAX_TIME
- **Description**: Maximum allowed time per run (safety limit in seconds)
- **Type**: integer
- **Required**: No
- **Default**: `3600` (1 hour)
- **Example**: `1800`, `7200`
- **Environment**: dev/prod

#### AGENT_MAX_CONCURRENT
- **Description**: Maximum concurrent agent runs
- **Type**: integer
- **Required**: No
- **Default**: `5`
- **Example**: `3`, `10`
- **Environment**: dev/prod

#### AGENT_REQUIRE_APPROVAL
- **Description**: Require approval for runs exceeding limits
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

---

### MCP (Model Context Protocol)

MCP provides access to legal data for external systems and AI agents.

#### MCP_API_TOKEN
- **Description**: API token for MCP endpoint authentication
- **Type**: string
- **Required**: No (Yes for production)
- **Default**: None
- **Example**: `mcp_secret_token_here`
- **Environment**: dev/prod
- **Note**: Generate secure random token for production

#### MCP_RATE_LIMIT
- **Description**: Rate limit format (max_attempts:decay_minutes)
- **Type**: string
- **Required**: No
- **Default**: `60:1`
- **Example**: `60:1`, `100:5`
- **Environment**: dev/prod

#### MCP_AUTH_ENABLED
- **Description**: Enable MCP authentication
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### MCP_TOKEN_HEADER
- **Description**: HTTP header name for MCP token
- **Type**: string
- **Required**: No
- **Default**: `X-MCP-Token`
- **Example**: `X-MCP-Token`, `Authorization`
- **Environment**: dev/prod

#### MCP_RATE_LIMIT_ENABLED
- **Description**: Enable rate limiting for MCP endpoints
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### MCP_RATE_LIMIT_PER_MINUTE
- **Description**: Default requests per minute limit
- **Type**: integer
- **Required**: No
- **Default**: `60`
- **Example**: `30`, `120`
- **Environment**: dev/prod

#### MCP_RATE_LIMIT_PER_HOUR
- **Description**: Default requests per hour limit
- **Type**: integer
- **Required**: No
- **Default**: `1000`
- **Example**: `500`, `2000`
- **Environment**: dev/prod

#### MCP_RATE_LAW_SEARCH
- **Description**: Rate limit for law.search tool (per minute)
- **Type**: integer
- **Required**: No
- **Default**: `30`
- **Example**: `20`, `50`
- **Environment**: dev/prod

#### MCP_RATE_LAW_GET
- **Description**: Rate limit for law.get_article tool (per minute)
- **Type**: integer
- **Required**: No
- **Default**: `60`
- **Example**: `40`, `100`
- **Environment**: dev/prod

#### MCP_RATE_DECISION_SEARCH
- **Description**: Rate limit for decision.search tool (per minute)
- **Type**: integer
- **Required**: No
- **Default**: `30`
- **Example**: `20`, `50`
- **Environment**: dev/prod

#### MCP_RATE_DECISION_GET
- **Description**: Rate limit for decision.get tool (per minute)
- **Type**: integer
- **Required**: No
- **Default**: `60`
- **Example**: `40`, `100`
- **Environment**: dev/prod

#### MCP_RATE_CASE_SEARCH
- **Description**: Rate limit for case.search tool (per minute)
- **Type**: integer
- **Required**: No
- **Default**: `20`
- **Example**: `10`, `30`
- **Environment**: dev/prod

#### MCP_MAX_PAGE_SIZE
- **Description**: Maximum page size for MCP responses
- **Type**: integer
- **Required**: No
- **Default**: `100`
- **Example**: `50`, `200`
- **Environment**: dev/prod

#### MCP_DEFAULT_PAGE_SIZE
- **Description**: Default page size for MCP responses
- **Type**: integer
- **Required**: No
- **Default**: `10`
- **Example**: `10`, `25`
- **Environment**: dev/prod

#### MCP_SIGNED_URL_EXPIRY
- **Description**: Signed URL expiry time in seconds
- **Type**: integer
- **Required**: No
- **Default**: `3600` (1 hour)
- **Example**: `1800`, `7200`
- **Environment**: dev/prod

#### MCP_NPX_PATH
- **Description**: Path to npx executable for MCP servers
- **Type**: string
- **Required**: No
- **Default**: `npx`
- **Example**: `npx`, `/usr/local/bin/npx`
- **Environment**: dev/prod

#### MCP_FILESYSTEM_PATH
- **Description**: Base path for filesystem MCP server
- **Type**: string
- **Required**: No
- **Default**: `app()` (app directory)
- **Example**: `/app`, `/home/user/documents`
- **Environment**: dev/prod

#### MCP_FILESYSTEM_ENABLED
- **Description**: Enable filesystem MCP server
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### MCP_GITHUB_ENABLED
- **Description**: Enable GitHub MCP server
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### GITHUB_TOKEN
- **Description**: GitHub personal access token for MCP server
- **Type**: string
- **Required**: No (Yes if MCP_GITHUB_ENABLED=true)
- **Default**: None
- **Example**: `ghp_xxxxxxxxxxxxxxxxxxxx`
- **Environment**: dev/prod

#### MCP_GITHUB_HTTP_URL
- **Description**: URL for GitHub HTTP MCP server
- **Type**: string
- **Required**: No
- **Default**: `http://localhost:8001/api/mcp`
- **Example**: `https://mcp.example.com/github`
- **Environment**: dev/prod

#### MCP_GITHUB_HTTP_API_KEY
- **Description**: API key for GitHub HTTP MCP server
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `api_key_here`
- **Environment**: dev/prod

#### MCP_GITHUB_HTTP_ENABLED
- **Description**: Enable GitHub HTTP MCP server
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### MCP_POSTGRES_URL
- **Description**: PostgreSQL connection URL for MCP server
- **Type**: string
- **Required**: No (Yes if MCP_POSTGRES_ENABLED=true)
- **Default**: None (uses DATABASE_URL)
- **Example**: `postgresql://user:pass@localhost:5432/dbname`
- **Environment**: dev/prod

#### MCP_POSTGRES_ENABLED
- **Description**: Enable PostgreSQL MCP server
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### BRAVE_API_KEY
- **Description**: Brave Search API key for MCP server
- **Type**: string
- **Required**: No (Yes if MCP_BRAVE_SEARCH_ENABLED=true)
- **Default**: None
- **Example**: `BSA_xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### MCP_BRAVE_SEARCH_ENABLED
- **Description**: Enable Brave Search MCP server
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### SLACK_BOT_TOKEN
- **Description**: Slack bot token for MCP server
- **Type**: string
- **Required**: No (Yes if MCP_SLACK_ENABLED=true)
- **Default**: None
- **Example**: `xoxb-xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### MCP_SLACK_ENABLED
- **Description**: Enable Slack MCP server
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### MCP_ODLUKE_URL
- **Description**: URL for Odluke MCP server
- **Type**: string
- **Required**: No
- **Default**: `{APP_URL}/mcp/message`
- **Example**: `https://api.example.com/mcp/message`
- **Environment**: dev/prod

#### MCP_ODLUKE_ENABLED
- **Description**: Enable Odluke MCP server (court decisions)
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### MCP_ODLUKE_TIMEOUT
- **Description**: Timeout for Odluke MCP server in seconds
- **Type**: integer
- **Required**: No
- **Default**: `45`
- **Example**: `30`, `60`
- **Environment**: dev/prod

---

### Feature Flags

#### TEXTRACT_AUTO_SYNC
- **Description**: Auto-sync Textract content changes
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### TEXTRACT_ENABLE_CONTENT_EDITING
- **Description**: Enable content editing UI
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### TEXTRACT_ENABLE_MANUAL_SYNC
- **Description**: Enable manual sync triggers
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### TEXTRACT_ENABLE_RESET_CONTENT
- **Description**: Enable reset to original functionality
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### TEXTRACT_AUTO_EMBEDDINGS
- **Description**: Auto-generate embeddings after Textract completion
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### TEXTRACT_AUTO_TABLES
- **Description**: Auto-extract tables from documents
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### DISTRIBUTED_SCALING_ENABLED
- **Description**: Enable auto-scaling for workers
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: prod

---

### External APIs

Configuration for Croatian court system APIs and GraphQL.

#### EKOM_BASE_URL
- **Description**: Base URL for e-Komunikacija court system
- **Type**: string
- **Required**: No
- **Default**: `https://e-komunikacija.pravosudje.hr`
- **Example**: `https://e-komunikacija.pravosudje.hr`
- **Environment**: dev/prod

#### EKOM_TOKEN
- **Description**: Authentication token for EKOM API
- **Type**: string
- **Required**: No (Yes if using EKOM)
- **Default**: None
- **Example**: `token_here`
- **Environment**: dev/prod

#### EKOM_TIMEOUT
- **Description**: Request timeout for EKOM API in seconds
- **Type**: integer
- **Required**: No
- **Default**: `30`
- **Example**: `30`, `60`
- **Environment**: dev/prod

#### EKOM_RETRIES
- **Description**: Number of retry attempts for failed EKOM requests
- **Type**: integer
- **Required**: No
- **Default**: `2`
- **Example**: `2`, `3`
- **Environment**: dev/prod

#### EKOM_RETRY_DELAY_MS
- **Description**: Delay between EKOM retries in milliseconds
- **Type**: integer
- **Required**: No
- **Default**: `300`
- **Example**: `300`, `500`
- **Environment**: dev/prod

#### EKOM_USER_AGENT
- **Description**: User agent string for EKOM requests
- **Type**: string
- **Required**: No
- **Default**: `Laravel-Ekom-Client/1.0`
- **Example**: `AI-Legal-System/1.0`
- **Environment**: dev/prod

#### EKOM_DEFAULT_PAGE_SIZE
- **Description**: Default page size for EKOM API pagination
- **Type**: integer
- **Required**: No
- **Default**: `50`
- **Example**: `25`, `100`
- **Environment**: dev/prod

#### E_OGLASNA_BASE_URL
- **Description**: Base URL for e-Oglasna court notices system
- **Type**: string
- **Required**: No
- **Default**: `https://e-oglasna.pravosudje.hr`
- **Example**: `https://e-oglasna.pravosudje.hr`
- **Environment**: dev/prod

#### E_OGLASNA_TIMEOUT
- **Description**: Request timeout for e-Oglasna in seconds
- **Type**: integer
- **Required**: No
- **Default**: `15`
- **Example**: `15`, `30`
- **Environment**: dev/prod

#### E_OGLASNA_CONNECT_TIMEOUT
- **Description**: Connection timeout for e-Oglasna in seconds
- **Type**: integer
- **Required**: No
- **Default**: `10`
- **Example**: `10`, `20`
- **Environment**: dev/prod

#### E_OGLASNA_MIN_DELAY_MS
- **Description**: Minimum delay between e-Oglasna requests in milliseconds
- **Type**: integer
- **Required**: No
- **Default**: `1100`
- **Example**: `1000`, `1500`
- **Environment**: dev/prod
- **Note**: Rate limit compliance (~1 req/sec)

#### E_OGLASNA_MAX_REQUESTS_PER_HOUR
- **Description**: Maximum requests per hour for e-Oglasna
- **Type**: integer
- **Required**: No
- **Default**: `950`
- **Example**: `500`, `1000`
- **Environment**: dev/prod
- **Note**: Stay under 1000/hour limit

#### E_OGLASNA_RETRY_JITTER_MS
- **Description**: Jitter added to retry backoff in milliseconds
- **Type**: integer
- **Required**: No
- **Default**: `100`
- **Example**: `100`, `200`
- **Environment**: dev/prod

#### E_OGLASNA_DEEP_SCAN_MAX_PAGES
- **Description**: Maximum pages per deep scan for safety
- **Type**: integer
- **Required**: No
- **Default**: `500`
- **Example**: `250`, `1000`
- **Environment**: dev/prod

#### E_OGLASNA_DEFAULT_SORT
- **Description**: Default sort order for e-Oglasna results
- **Type**: string
- **Required**: No
- **Default**: `datePublished,desc`
- **Example**: `datePublished,desc`, `datePublished,asc`
- **Environment**: dev/prod

#### ODLUKE_BASE_URL
- **Description**: Base URL for Odluke court decisions system
- **Type**: string
- **Required**: No
- **Default**: `https://odluke.sudovi.hr`
- **Example**: `https://odluke.sudovi.hr`
- **Environment**: dev/prod

#### ODLUKE_TIMEOUT
- **Description**: Request timeout for Odluke in seconds
- **Type**: integer
- **Required**: No
- **Default**: `30`
- **Example**: `30`, `60`
- **Environment**: dev/prod

#### ODLUKE_RETRY
- **Description**: Number of retry attempts for Odluke
- **Type**: integer
- **Required**: No
- **Default**: `2`
- **Example**: `2`, `3`
- **Environment**: dev/prod

#### ODLUKE_DELAY_MS
- **Description**: Delay between Odluke requests in milliseconds
- **Type**: integer
- **Required**: No
- **Default**: `700`
- **Example**: `500`, `1000`
- **Environment**: dev/prod

#### ODLUKE_RPM
- **Description**: Maximum requests per minute for Odluke
- **Type**: integer
- **Required**: No
- **Default**: `30`
- **Example**: `20`, `40`
- **Environment**: dev/prod

#### ODLUKE_BACKOFF_MS
- **Description**: Extra backoff on 429/5xx errors in milliseconds
- **Type**: integer
- **Required**: No
- **Default**: `800`
- **Example**: `500`, `1000`
- **Environment**: dev/prod

#### GRAPHQL_ENDPOINT
- **Description**: GraphQL API endpoint URL
- **Type**: string
- **Required**: No (Yes if using GraphQL)
- **Default**: None
- **Example**: `https://api.example.com/graphql`
- **Environment**: dev/prod

#### GRAPHQL_AUTH_HEADER
- **Description**: HTTP header name for GraphQL authentication
- **Type**: string
- **Required**: No
- **Default**: `Authorization`
- **Example**: `Authorization`, `X-API-Key`
- **Environment**: dev/prod

#### GRAPHQL_TOKEN
- **Description**: Bearer token for GraphQL authentication
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `bearer_token_here`
- **Environment**: dev/prod

#### GRAPHQL_CACHE_STORE
- **Description**: Cache store for GraphQL queries
- **Type**: string
- **Required**: No
- **Default**: `file`
- **Example**: `file`, `redis`, `database`
- **Environment**: dev/prod

#### GRAPHQL_AUTO_DISCOVER
- **Description**: Auto-discover GraphQL schema via introspection
- **Type**: boolean
- **Required**: No
- **Default**: `true`
- **Example**: `true`, `false`
- **Environment**: dev/prod

---

### Textract Processing

Configuration for AWS Textract OCR processing.

#### TEXTRACT_EMBEDDING_MODEL
- **Description**: Embedding model for Textract content
- **Type**: string
- **Required**: No
- **Default**: `text-embedding-3-small`
- **Example**: `text-embedding-3-small`, `text-embedding-3-large`
- **Environment**: dev/prod

#### TEXTRACT_EMBEDDING_PROVIDER
- **Description**: Embedding provider for Textract
- **Type**: string
- **Required**: No
- **Default**: `openai`
- **Example**: `openai`, `cohere`
- **Environment**: dev/prod

#### TEXTRACT_EMBEDDING_BATCH_SIZE
- **Description**: Batch size for embedding generation
- **Type**: integer
- **Required**: No
- **Default**: `100`
- **Example**: `50`, `200`
- **Environment**: dev/prod

#### TEXTRACT_EMBEDDING_MAX_RETRIES
- **Description**: Maximum retries for failed embedding requests
- **Type**: integer
- **Required**: No
- **Default**: `3`
- **Example**: `2`, `5`
- **Environment**: dev/prod

#### TEXTRACT_CHUNK_SIZE
- **Description**: Target chunk size in characters
- **Type**: integer
- **Required**: No
- **Default**: `1000`
- **Example**: `500`, `2000`
- **Environment**: dev/prod
- **Note**: ~1000 chars ≈ 250 tokens

#### TEXTRACT_CHUNK_OVERLAP
- **Description**: Overlap between chunks in characters
- **Type**: integer
- **Required**: No
- **Default**: `200`
- **Example**: `100`, `300`
- **Environment**: dev/prod

#### TEXTRACT_QUEUE
- **Description**: Queue name for Textract jobs
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `textract`, `ocr`
- **Environment**: dev/prod

#### TEXTRACT_EMBEDDING_TIMEOUT
- **Description**: Timeout for embedding jobs in seconds
- **Type**: integer
- **Required**: No
- **Default**: `600` (10 minutes)
- **Example**: `300`, `900`
- **Environment**: dev/prod

#### TEXTRACT_GRAPH_TIMEOUT
- **Description**: Timeout for graph sync jobs in seconds
- **Type**: integer
- **Required**: No
- **Default**: `300` (5 minutes)
- **Example**: `180`, `600`
- **Environment**: dev/prod

#### TEXTRACT_MIN_CONTENT_LENGTH
- **Description**: Minimum content length in characters
- **Type**: integer
- **Required**: No
- **Default**: `10`
- **Example**: `10`, `50`
- **Environment**: dev/prod

#### TEXTRACT_MAX_CONTENT_LENGTH
- **Description**: Maximum content length in characters
- **Type**: integer
- **Required**: No
- **Default**: `10000000` (10MB)
- **Example**: `5000000`, `20000000`
- **Environment**: dev/prod

#### TEXTRACT_ALLOW_EMPTY_CONTENT
- **Description**: Allow empty content (for deletion)
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

---

### Cache Configuration

#### CACHE_STORE
- **Description**: Default cache store driver
- **Type**: string
- **Required**: No
- **Default**: `database`
- **Example**: `redis`, `database`, `file`, `memcached`
- **Environment**: dev/prod

#### CACHE_PREFIX
- **Description**: Cache key prefix to avoid collisions
- **Type**: string
- **Required**: No
- **Default**: `{APP_NAME}-cache-`
- **Example**: `myapp-cache-`
- **Environment**: dev/prod

#### REDIS_CACHE_CONNECTION
- **Description**: Redis connection for cache
- **Type**: string
- **Required**: No
- **Default**: `cache`
- **Example**: `cache`
- **Environment**: dev/prod

#### REDIS_CACHE_LOCK_CONNECTION
- **Description**: Redis connection for cache locks
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `default`
- **Environment**: dev/prod

#### DYNAMODB_CACHE_TABLE
- **Description**: DynamoDB table name for cache
- **Type**: string
- **Required**: No
- **Default**: `cache`
- **Example**: `app_cache`
- **Environment**: prod

#### DYNAMODB_ENDPOINT
- **Description**: Custom DynamoDB endpoint
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `https://dynamodb.us-east-1.amazonaws.com`
- **Environment**: prod

---

### Queue Configuration

#### QUEUE_CONNECTION
- **Description**: Default queue connection driver
- **Type**: string
- **Required**: No
- **Default**: `database`
- **Example**: `redis`, `database`, `sqs`, `sync`
- **Environment**: dev/prod
- **Note**: Use `database` for local, `redis` for production

#### QUEUE_PREFIX
- **Description**: Redis queue key prefix
- **Type**: string
- **Required**: No
- **Default**: `queues`
- **Example**: `queues`, `jobs`
- **Environment**: dev/prod

#### QUEUE_FAILED_DRIVER
- **Description**: Failed job storage driver
- **Type**: string
- **Required**: No
- **Default**: `database-uuids`
- **Example**: `database-uuids`, `dynamodb`, `file`
- **Environment**: dev/prod

#### REDIS_QUEUE_CONNECTION
- **Description**: Redis connection for queue
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `default`, `queue`
- **Environment**: dev/prod

#### REDIS_QUEUE
- **Description**: Default Redis queue name
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `default`, `high-priority`
- **Environment**: dev/prod

#### REDIS_QUEUE_RETRY_AFTER
- **Description**: Seconds before retrying failed Redis queue jobs
- **Type**: integer
- **Required**: No
- **Default**: `90`
- **Example**: `60`, `120`
- **Environment**: dev/prod

#### QUEUE_REDIS_CONNECTION
- **Description**: Redis connection name for queues
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `default`
- **Environment**: dev/prod

#### BEANSTALKD_QUEUE_HOST
- **Description**: Beanstalkd server hostname
- **Type**: string
- **Required**: No
- **Default**: `localhost`
- **Example**: `localhost`, `beanstalk.example.com`
- **Environment**: dev/prod

#### BEANSTALKD_QUEUE
- **Description**: Default Beanstalkd queue tube
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `default`
- **Environment**: dev/prod

#### BEANSTALKD_QUEUE_RETRY_AFTER
- **Description**: Seconds before retrying failed Beanstalkd jobs
- **Type**: integer
- **Required**: No
- **Default**: `90`
- **Example**: `60`, `120`
- **Environment**: dev/prod

#### SQS_PREFIX
- **Description**: AWS SQS queue URL prefix
- **Type**: string
- **Required**: No
- **Default**: `https://sqs.us-east-1.amazonaws.com/your-account-id`
- **Example**: `https://sqs.us-east-1.amazonaws.com/123456789012`
- **Environment**: prod

#### SQS_QUEUE
- **Description**: Default SQS queue name
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `default`, `production-queue`
- **Environment**: prod

#### SQS_SUFFIX
- **Description**: SQS queue name suffix
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `.fifo`
- **Environment**: prod

---

### Redis Configuration

#### REDIS_CLIENT
- **Description**: Redis client library
- **Type**: string
- **Required**: No
- **Default**: `phpredis`
- **Example**: `phpredis`, `predis`
- **Environment**: dev/prod

#### REDIS_HOST
- **Description**: Redis server hostname
- **Type**: string
- **Required**: No
- **Default**: `127.0.0.1`
- **Example**: `localhost`, `redis.example.com`
- **Environment**: dev/prod

#### REDIS_PASSWORD
- **Description**: Redis server password
- **Type**: string
- **Required**: No
- **Default**: `null`
- **Example**: `secure_password`
- **Environment**: dev/prod

#### REDIS_PORT
- **Description**: Redis server port
- **Type**: integer
- **Required**: No
- **Default**: `6379`
- **Example**: `6379`
- **Environment**: dev/prod

#### REDIS_DB
- **Description**: Redis database number for default connection
- **Type**: integer
- **Required**: No
- **Default**: `0`
- **Example**: `0`, `1`
- **Environment**: dev/prod

#### REDIS_CACHE_DB
- **Description**: Redis database number for cache
- **Type**: integer
- **Required**: No
- **Default**: `1`
- **Example**: `1`, `2`
- **Environment**: dev/prod

#### REDIS_URL
- **Description**: Full Redis connection URL
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `redis://user:password@host:6379/0`
- **Environment**: dev/prod

#### REDIS_USERNAME
- **Description**: Redis username (Redis 6+)
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `default`, `app_user`
- **Environment**: dev/prod

#### REDIS_CLUSTER
- **Description**: Redis cluster mode
- **Type**: string
- **Required**: No
- **Default**: `redis`
- **Example**: `redis`, `cluster`
- **Environment**: prod

#### REDIS_PREFIX
- **Description**: Redis key prefix
- **Type**: string
- **Required**: No
- **Default**: `{APP_NAME}-database-`
- **Example**: `myapp-`
- **Environment**: dev/prod

#### REDIS_PERSISTENT
- **Description**: Use persistent Redis connections
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### REDIS_MAX_RETRIES
- **Description**: Maximum connection retry attempts
- **Type**: integer
- **Required**: No
- **Default**: `3`
- **Example**: `3`, `5`
- **Environment**: dev/prod

#### REDIS_BACKOFF_ALGORITHM
- **Description**: Backoff algorithm for retries
- **Type**: string
- **Required**: No
- **Default**: `decorrelated_jitter`
- **Example**: `decorrelated_jitter`, `exponential`
- **Environment**: dev/prod

#### REDIS_BACKOFF_BASE
- **Description**: Base backoff time in milliseconds
- **Type**: integer
- **Required**: No
- **Default**: `100`
- **Example**: `100`, `200`
- **Environment**: dev/prod

#### REDIS_BACKOFF_CAP
- **Description**: Maximum backoff time in milliseconds
- **Type**: integer
- **Required**: No
- **Default**: `1000`
- **Example**: `1000`, `5000`
- **Environment**: dev/prod

---

### Mail Configuration

#### MAIL_MAILER
- **Description**: Default mail driver
- **Type**: string
- **Required**: No
- **Default**: `log`
- **Example**: `smtp`, `sendmail`, `mailgun`, `postmark`, `ses`, `log`
- **Environment**: dev/prod

#### MAIL_SCHEME
- **Description**: Mail transport scheme
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `tls`, `ssl`
- **Environment**: dev/prod

#### MAIL_URL
- **Description**: Full mail server URL
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `smtp://user:pass@smtp.example.com:587`
- **Environment**: dev/prod

#### MAIL_HOST
- **Description**: SMTP server hostname
- **Type**: string
- **Required**: No
- **Default**: `127.0.0.1`
- **Example**: `smtp.gmail.com`, `smtp.sendgrid.net`
- **Environment**: dev/prod

#### MAIL_PORT
- **Description**: SMTP server port
- **Type**: integer
- **Required**: No
- **Default**: `2525`
- **Example**: `587` (TLS), `465` (SSL), `25` (unencrypted)
- **Environment**: dev/prod

#### MAIL_USERNAME
- **Description**: SMTP authentication username
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `user@example.com`
- **Environment**: dev/prod

#### MAIL_PASSWORD
- **Description**: SMTP authentication password
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `password`
- **Environment**: dev/prod

#### MAIL_FROM_ADDRESS
- **Description**: Default "from" email address
- **Type**: string
- **Required**: No
- **Default**: `hello@example.com`
- **Example**: `noreply@example.com`
- **Environment**: dev/prod

#### MAIL_FROM_NAME
- **Description**: Default "from" name
- **Type**: string
- **Required**: No
- **Default**: `${APP_NAME}`
- **Example**: `AI Legal System`
- **Environment**: dev/prod

#### MAIL_EHLO_DOMAIN
- **Description**: SMTP EHLO domain
- **Type**: string
- **Required**: No
- **Default**: Parsed from APP_URL
- **Example**: `example.com`
- **Environment**: dev/prod

#### MAIL_SENDMAIL_PATH
- **Description**: Path to sendmail binary
- **Type**: string
- **Required**: No
- **Default**: `/usr/sbin/sendmail -bs -i`
- **Example**: `/usr/sbin/sendmail -t`
- **Environment**: dev/prod

#### MAIL_LOG_CHANNEL
- **Description**: Log channel for mail driver
- **Type**: string
- **Required**: No
- **Default**: None (uses default log channel)
- **Example**: `mail`, `stack`
- **Environment**: dev

#### POSTMARK_TOKEN
- **Description**: Postmark API token
- **Type**: string
- **Required**: No (Yes if using Postmark)
- **Default**: None
- **Example**: `token_here`
- **Environment**: prod

#### RESEND_KEY
- **Description**: Resend API key
- **Type**: string
- **Required**: No (Yes if using Resend)
- **Default**: None
- **Example**: `re_xxxxxxxxxxxxx`
- **Environment**: prod

---

### Session & Broadcasting

#### SESSION_DRIVER
- **Description**: Session storage driver
- **Type**: string
- **Required**: No
- **Default**: `database`
- **Example**: `file`, `cookie`, `database`, `redis`
- **Environment**: dev/prod

#### SESSION_LIFETIME
- **Description**: Session lifetime in minutes
- **Type**: integer
- **Required**: No
- **Default**: `120`
- **Example**: `120`, `1440`
- **Environment**: dev/prod

#### SESSION_ENCRYPT
- **Description**: Encrypt session data
- **Type**: boolean
- **Required**: No
- **Default**: `false`
- **Example**: `true`, `false`
- **Environment**: dev/prod

#### SESSION_PATH
- **Description**: Cookie path for sessions
- **Type**: string
- **Required**: No
- **Default**: `/`
- **Example**: `/`, `/app`
- **Environment**: dev/prod

#### SESSION_DOMAIN
- **Description**: Cookie domain for sessions
- **Type**: string
- **Required**: No
- **Default**: `null`
- **Example**: `.example.com`
- **Environment**: dev/prod

#### BROADCAST_CONNECTION
- **Description**: Broadcasting driver
- **Type**: string
- **Required**: No
- **Default**: `log`
- **Example**: `pusher`, `redis`, `log`
- **Environment**: dev/prod

#### FILESYSTEM_DISK
- **Description**: Default filesystem disk
- **Type**: string
- **Required**: No
- **Default**: `local`
- **Example**: `local`, `public`, `s3`
- **Environment**: dev/prod

---

### Memcached Configuration

#### MEMCACHED_HOST
- **Description**: Memcached server hostname
- **Type**: string
- **Required**: No
- **Default**: `127.0.0.1`
- **Example**: `localhost`, `memcached.example.com`
- **Environment**: dev/prod

#### MEMCACHED_PORT
- **Description**: Memcached server port
- **Type**: integer
- **Required**: No
- **Default**: `11211`
- **Example**: `11211`
- **Environment**: dev/prod

#### MEMCACHED_USERNAME
- **Description**: Memcached SASL username
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `user`
- **Environment**: prod

#### MEMCACHED_PASSWORD
- **Description**: Memcached SASL password
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `password`
- **Environment**: prod

#### MEMCACHED_PERSISTENT_ID
- **Description**: Memcached persistent connection ID
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `myapp`
- **Environment**: dev/prod

---

### Meilisearch Configuration

#### MEILISEARCH_HOST
- **Description**: Meilisearch server URL
- **Type**: string
- **Required**: No
- **Default**: `http://localhost:7700`
- **Example**: `http://localhost:7700`, `https://search.example.com`
- **Environment**: dev/prod

#### MEILISEARCH_KEY
- **Description**: Meilisearch API key
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `masterKey`
- **Environment**: dev/prod

#### MEILISEARCH_PREFIX
- **Description**: Meilisearch index prefix
- **Type**: string
- **Required**: No
- **Default**: `agent_vectors_`
- **Example**: `app_`, `vectors_`
- **Environment**: dev/prod

#### MEILISEARCH_EMBEDDER
- **Description**: Meilisearch embedder name
- **Type**: string
- **Required**: No
- **Default**: `default`
- **Example**: `default`, `openai`
- **Environment**: dev/prod

#### MEILISEARCH_SEMANTIC_RATIO
- **Description**: Semantic vs keyword search ratio (0.0-1.0)
- **Type**: float
- **Required**: No
- **Default**: `1.0`
- **Example**: `0.5`, `1.0`
- **Environment**: dev/prod

---

### Logging Configuration

#### LOG_CHANNEL
- **Description**: Default log channel
- **Type**: string
- **Required**: No
- **Default**: `stack`
- **Example**: `stack`, `single`, `daily`, `slack`
- **Environment**: dev/prod

#### LOG_STACK
- **Description**: Log channels to include in stack
- **Type**: string
- **Required**: No
- **Default**: `single`
- **Example**: `single`, `daily,slack`
- **Environment**: dev/prod

#### LOG_DEPRECATIONS_CHANNEL
- **Description**: Channel for deprecation warnings
- **Type**: string
- **Required**: No
- **Default**: `null`
- **Example**: `deprecations`, `null`
- **Environment**: dev/prod

#### LOG_LEVEL
- **Description**: Minimum log level to record
- **Type**: string
- **Required**: No
- **Default**: `debug`
- **Example**: `debug`, `info`, `notice`, `warning`, `error`, `critical`
- **Environment**: dev/prod

---

### Slack Integration

#### SLACK_BOT_USER_OAUTH_TOKEN
- **Description**: Slack bot OAuth token for notifications
- **Type**: string
- **Required**: No (Yes if using Slack notifications)
- **Default**: None
- **Example**: `xoxb-xxxxxxxxxxxxx`
- **Environment**: prod

#### SLACK_BOT_USER_DEFAULT_CHANNEL
- **Description**: Default Slack channel for notifications
- **Type**: string
- **Required**: No
- **Default**: None
- **Example**: `#alerts`, `C01234567`
- **Environment**: prod

---

### Embedding Models

#### EMBEDDING_MODEL
- **Description**: Default embedding model for distributed processing
- **Type**: string
- **Required**: No
- **Default**: `text-embedding-3-small`
- **Example**: `text-embedding-3-small`, `text-embedding-3-large`
- **Environment**: dev/prod

---

### OpenRouter Configuration

#### OPENROUTER_API_KEY
- **Description**: OpenRouter API key for accessing 100+ models
- **Type**: string
- **Required**: No (Yes if using OpenRouter)
- **Default**: None
- **Example**: `sk-or-xxxxxxxxxxxxx`
- **Environment**: dev/prod
- **Note**: Get from https://openrouter.ai/settings

#### OPENROUTER_HTTP_REFERER
- **Description**: HTTP Referer header for OpenRouter requests
- **Type**: string
- **Required**: No
- **Default**: `{APP_URL}`
- **Example**: `https://example.com`
- **Environment**: dev/prod

#### OPENROUTER_APP_NAME
- **Description**: Application name for OpenRouter requests
- **Type**: string
- **Required**: No
- **Default**: `{APP_NAME}`
- **Example**: `AI Legal System`
- **Environment**: dev/prod

---

### Other LLM Provider API Keys

#### ANTHROPIC_API_KEY
- **Description**: Anthropic (Claude) API key
- **Type**: string
- **Required**: No (Yes if using Anthropic)
- **Default**: None
- **Example**: `sk-ant-xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### GEMINI_API_KEY
- **Description**: Google Gemini API key
- **Type**: string
- **Required**: No (Yes if using Gemini)
- **Default**: None
- **Example**: `AIzaxxxxxxxxxxxxx`
- **Environment**: dev/prod

#### DEEPSEEK_API_KEY
- **Description**: DeepSeek AI API key
- **Type**: string
- **Required**: No (Yes if using DeepSeek)
- **Default**: None
- **Example**: `sk-xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### MISTRAL_API_KEY
- **Description**: Mistral AI API key
- **Type**: string
- **Required**: No (Yes if using Mistral)
- **Default**: None
- **Example**: `xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### GROQ_API_KEY
- **Description**: Groq API key for fast inference
- **Type**: string
- **Required**: No (Yes if using Groq)
- **Default**: None
- **Example**: `gsk_xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### XAI_API_KEY
- **Description**: xAI (Grok) API key
- **Type**: string
- **Required**: No (Yes if using xAI)
- **Default**: None
- **Example**: `xai-xxxxxxxxxxxxx`
- **Environment**: dev/prod

#### VOYAGEAI_API_KEY
- **Description**: Voyage AI API key for embeddings
- **Type**: string
- **Required**: No (Yes if using Voyage AI)
- **Default**: None
- **Example**: `pa-xxxxxxxxxxxxx`
- **Environment**: dev/prod

---

### Vite Build

#### VITE_APP_NAME
- **Description**: Application name for Vite build
- **Type**: string
- **Required**: No
- **Default**: `${APP_NAME}`
- **Example**: `AI Legal System`
- **Environment**: dev/prod

---

## Environment Setup

### Development Setup

1. **Clone Repository**
   ```bash
   git clone <repository-url>
   cd ai-legal-war-machine
   ```

2. **Copy Environment File**
   ```bash
   cp .env.example .env
   ```

3. **Configure Minimum Required Variables**
   ```bash
   # Application
   APP_NAME="AI Legal War Machine Dev"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   # Database (SQLite for quick start)
   DB_CONNECTION=sqlite
   DB_DATABASE=database/database.sqlite

   # Queue (Database for development)
   QUEUE_CONNECTION=database
   CACHE_STORE=database
   ```

4. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Run Migrations**
   ```bash
   php artisan migrate
   ```

6. **Start Development Server**
   ```bash
   php artisan serve
   ```

### Production Setup

1. **Server Requirements**
   - PHP 8.2+
   - PostgreSQL 14+ (with pgvector extension)
   - Neo4j 4.0+
   - Redis 6.0+
   - Node.js 18+ (for MCP servers)

2. **Configure Production Variables**
   ```bash
   # Application
   APP_NAME="AI Legal War Machine"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://legal.example.com
   APP_KEY=<generated-key>

   # Database
   DB_CONNECTION=pgsql
   DB_HOST=<database-host>
   DB_PORT=5432
   DB_DATABASE=<database-name>
   DB_USERNAME=<database-user>
   DB_PASSWORD=<secure-password>

   # Neo4j
   NEO4J_URI=bolt://<neo4j-host>:7687
   NEO4J_USERNAME=neo4j
   NEO4J_PASSWORD=<secure-password>

   # OpenAI
   OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxx

   # AWS
   AWS_ACCESS_KEY_ID=<access-key>
   AWS_SECRET_ACCESS_KEY=<secret-key>
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=<bucket-name>

   # Queue & Cache
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   REDIS_HOST=<redis-host>
   REDIS_PASSWORD=<redis-password>
   ```

3. **Security Checklist**
   - [ ] `APP_DEBUG=false`
   - [ ] Strong `APP_KEY` generated
   - [ ] Secure database passwords
   - [ ] Neo4j password changed from default
   - [ ] Redis password set
   - [ ] `MCP_API_TOKEN` set for API protection
   - [ ] HTTPS enabled via reverse proxy
   - [ ] Firewall configured
   - [ ] Rate limiting enabled

4. **Optimization**
   ```bash
   # Cache configuration
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache

   # Run queue workers
   php artisan queue:work redis --queue=high,default,low --tries=3
   ```

### Docker Setup

1. **Docker Compose Configuration**
   ```yaml
   version: '3.8'
   services:
     app:
       build: .
       environment:
         - APP_ENV=production
         - DB_CONNECTION=pgsql
         - DB_HOST=postgres
         - NEO4J_URI=bolt://neo4j:7687
         - REDIS_HOST=redis

     postgres:
       image: pgvector/pgvector:pg14
       environment:
         POSTGRES_DB: legal_db
         POSTGRES_USER: postgres
         POSTGRES_PASSWORD: <password>

     neo4j:
       image: neo4j:4.4
       environment:
         NEO4J_AUTH: neo4j/<password>

     redis:
       image: redis:7-alpine
       command: redis-server --requirepass <password>
   ```

2. **Environment Variables**
   - Create `.env.docker` with container-specific values
   - Use Docker secrets for sensitive data
   - Override defaults in `docker-compose.yml`

3. **Build and Run**
   ```bash
   docker-compose up -d
   docker-compose exec app php artisan migrate
   ```

---

## Integration Guides

### Neo4j Setup

1. **Installation**
   ```bash
   # Docker
   docker run -d \
     --name neo4j \
     -p 7474:7474 -p 7687:7687 \
     -e NEO4J_AUTH=neo4j/your_password \
     neo4j:4.4

   # Or download from https://neo4j.com/download/
   ```

2. **Create Database** (Neo4j 4.0+)
   ```cypher
   CREATE DATABASE legal_graph;
   :use legal_graph;
   ```

3. **Create Indexes**
   ```cypher
   CREATE INDEX law_id FOR (l:Law) ON (l.id);
   CREATE INDEX case_id FOR (c:Case) ON (c.id);
   CREATE INDEX keyword_name FOR (k:Keyword) ON (k.name);
   ```

4. **Configure Application**
   ```bash
   NEO4J_URI=bolt://localhost:7687
   NEO4J_USERNAME=neo4j
   NEO4J_PASSWORD=your_password
   NEO4J_DATABASE=legal_graph
   NEO4J_ENABLED=true
   NEO4J_AUTO_SYNC=true
   ```

5. **Test Connection**
   ```bash
   php artisan tinker
   >>> app('neo4j')->run('RETURN "Connected!" as message');
   ```

### OpenAI Setup

1. **Create Account**
   - Sign up at https://platform.openai.com/
   - Add payment method

2. **Generate API Key**
   - Go to https://platform.openai.com/api-keys
   - Click "Create new secret key"
   - Copy and save the key (shown only once)

3. **Configure Application**
   ```bash
   OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxx
   OPENAI_ORG=org-xxxxxxxxxxxxx  # Optional
   OPENAI_PROJECT=proj-xxxxxxxxxxxxx  # Optional
   ```

4. **Optional: Configure Models**
   ```bash
   OPENAI_CHAT_MODEL=gpt-4o
   OPENAI_EMBEDDINGS_MODEL=text-embedding-3-large
   ```

5. **Test Connection**
   ```bash
   php artisan tinker
   >>> OpenAI::chat()->create(['model' => 'gpt-4o-mini', 'messages' => [['role' => 'user', 'content' => 'Hello']]]);
   ```

### AWS Textract Setup

1. **Create IAM User**
   - Go to AWS IAM Console
   - Create user with programmatic access
   - Attach policies:
     - `AmazonTextractFullAccess`
     - `AmazonS3FullAccess` (or restrict to specific bucket)

2. **Create S3 Bucket**
   ```bash
   aws s3 mb s3://my-legal-documents --region us-east-1
   ```

3. **Configure CORS** (if accessing from browser)
   ```json
   [
     {
       "AllowedHeaders": ["*"],
       "AllowedMethods": ["GET", "PUT", "POST"],
       "AllowedOrigins": ["https://legal.example.com"],
       "ExposeHeaders": []
     }
   ]
   ```

4. **Configure Application**
   ```bash
   AWS_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
   AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=my-legal-documents

   S3_INPUT_PREFIX=textract/input
   S3_OUTPUT_PREFIX=textract/output
   S3_JSON_PREFIX=textract/json
   ```

5. **Test Upload**
   ```bash
   php artisan tinker
   >>> Storage::disk('s3')->put('test.txt', 'Hello S3!');
   ```

### Google Drive Setup

1. **Create Google Cloud Project**
   - Go to https://console.cloud.google.com/
   - Create new project

2. **Enable APIs**
   - Enable "Google Drive API"
   - Enable "Google Docs API" (if needed)

3. **Create Service Account**
   - Go to IAM & Admin > Service Accounts
   - Create service account
   - Grant "Editor" role
   - Create JSON key
   - Download and save JSON file

4. **Configure Drive Folder**
   - Create folder in Google Drive
   - Share folder with service account email
   - Copy folder ID from URL: `https://drive.google.com/drive/folders/FOLDER_ID_HERE`

5. **Configure Application**
   ```bash
   GOOGLE_APPLICATION_CREDENTIALS=/absolute/path/to/service-account.json
   GOOGLE_DRIVE_FOLDER_ID=1a2b3c4d5e6f7g8h9i0j

   # Optional: For domain-wide delegation
   GOOGLE_IMPERSONATE_USER=user@example.com
   ```

6. **Test Access**
   ```bash
   php artisan tinker
   >>> app('google.drive')->listFiles();
   ```

---

## Environment Variable Summary

**Total Variables Documented**: 325

### By Category:
- **Application Core**: 13 variables
- **Database**: 25 variables
- **Neo4j**: 15 variables
- **OpenAI**: 14 variables
- **AWS**: 11 variables
- **Google Drive**: 3 variables
- **Vizra ADK**: 50 variables
- **Agent Framework**: 22 variables
- **MCP**: 35 variables
- **Feature Flags**: 7 variables
- **External APIs**: 27 variables
- **Textract**: 23 variables
- **Cache**: 10 variables
- **Queue**: 18 variables
- **Redis**: 20 variables
- **Mail**: 13 variables
- **Session/Broadcasting**: 7 variables
- **Memcached**: 5 variables
- **Meilisearch**: 5 variables
- **Logging**: 4 variables
- **Slack**: 2 variables
- **LLM Providers**: 7 variables
- **Other**: 4 variables

### By Environment:
- **Required (Production)**: 35 variables
- **Optional (Production)**: 135 variables
- **Development Only**: 155 variables

---

## Quick Start Templates

### Minimal Development .env
```bash
APP_NAME="AI Legal War Machine Dev"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=  # Generate with: php artisan key:generate

DB_CONNECTION=sqlite
QUEUE_CONNECTION=database
CACHE_STORE=database
```

### Production .env Template
```bash
# Application
APP_NAME="AI Legal War Machine"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://legal.example.com
APP_KEY=  # REQUIRED: Generate secure key

# Database
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=  # REQUIRED

# Neo4j
NEO4J_URI=bolt://localhost:7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=  # REQUIRED

# OpenAI
OPENAI_API_KEY=  # REQUIRED

# AWS
AWS_ACCESS_KEY_ID=  # REQUIRED
AWS_SECRET_ACCESS_KEY=  # REQUIRED
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=  # REQUIRED

# Google Drive (Optional)
GOOGLE_APPLICATION_CREDENTIALS=
GOOGLE_DRIVE_FOLDER_ID=

# Queue & Cache
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=localhost
REDIS_PASSWORD=  # REQUIRED

# MCP Security
MCP_API_TOKEN=  # REQUIRED: Generate secure token
```

---

## Troubleshooting

### Common Issues

1. **OpenAI Rate Limits**
   - Increase `OPENAI_TIMEOUT` to 120
   - Add retry logic with `OPENAI_RETRY_TIMES=3`

2. **Neo4j Connection Refused**
   - Check `NEO4J_URI` format: `bolt://host:port`
   - Verify Neo4j is running: `docker ps` or `systemctl status neo4j`
   - Test port: `nc -zv localhost 7687`

3. **AWS Textract Timeout**
   - Increase `TEXTRACT_EMBEDDING_TIMEOUT` for large documents
   - Check S3 bucket permissions
   - Verify IAM user has Textract access

4. **Queue Jobs Not Processing**
   - Check queue connection: `QUEUE_CONNECTION=redis`
   - Start queue worker: `php artisan queue:work`
   - Verify Redis connection

5. **Vector Search Slow**
   - Ensure pgvector extension installed
   - Create indexes on vector columns
   - Adjust `VIZRA_ADK_CHUNK_SIZE` for better performance

---

## Security Best Practices

1. **Never commit `.env` file** to version control
2. **Rotate API keys** regularly (quarterly recommended)
3. **Use separate credentials** for dev/staging/production
4. **Enable MCP authentication** in production (`MCP_AUTH_ENABLED=true`)
5. **Set strong passwords** for all databases
6. **Use HTTPS** in production (`APP_URL=https://...`)
7. **Enable rate limiting** (`MCP_RATE_LIMIT_ENABLED=true`)
8. **Monitor costs** with `AGENT_COST_BUDGET` limits
9. **Restrict S3 bucket** permissions to minimum required
10. **Use IAM roles** instead of access keys when on AWS infrastructure

---

## Additional Resources

- [Laravel Configuration Documentation](https://laravel.com/docs/configuration)
- [Neo4j Connection Guide](https://neo4j.com/docs/driver-manual/current/get-started/)
- [OpenAI API Reference](https://platform.openai.com/docs/api-reference)
- [AWS Textract Documentation](https://docs.aws.amazon.com/textract/)
- [Google Drive API Guide](https://developers.google.com/drive/api/guides/about-sdk)
- [Vizra ADK Documentation](https://github.com/aglavas/vizra-adk)

---

## Support

For configuration assistance:
- Check the [README.md](../README.md) for general information
- Review [AUTHENTICATION.md](../AUTHENTICATION.md) for auth setup
- See [DISTRIBUTED_PROCESSING_ARCHITECTURE.md](DISTRIBUTED_PROCESSING_ARCHITECTURE.md) for queue configuration
- Open an issue on GitHub for bugs or feature requests

---

*Last Updated: 2025-10-31*
