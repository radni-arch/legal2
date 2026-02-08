# Neo4j AuraDB Quick Start Guide

This guide walks you through setting up Neo4j AuraDB Free for the AI Legal War Machine project.

## What is Neo4j AuraDB?

Neo4j AuraDB is a fully managed cloud graph database service by Neo4j. The Free tier provides:
- **200,000 nodes** and **400,000 relationships**
- Automatic backups
- No credit card required (but payment method on file needed for API access)
- Auto-pauses after 3 days of inactivity
- Deleted after 30 days if paused

**Perfect for development and testing!**

## Setup Instructions

AuraDB setup is performed manually through the Neo4j web console. There are no automated setup scripts for AuraDB in this project.

**Note:** If you want to use the Aura CLI for instance management, you'll need to install it manually from [https://github.com/neo4j/aura-cli/releases](https://github.com/neo4j/aura-cli/releases).

## Creating Your AuraDB Instance

### Step 1: Create Instance via Web Console

1. Visit https://console.neo4j.io/
2. Click **+ New Instance**
3. Select **AuraDB Free**
4. Choose a name (e.g., "ai-legal-war-machine")
5. Click **Create**
6. **IMPORTANT:** Save the generated password immediately!

### Step 2: Get Connection Details

After creation, you'll see:
```
Connection URI: neo4j+s://xxxxx.databases.neo4j.io
Username: neo4j
Password: <your-generated-password>
```

### Step 3: Configure Laravel

Update your `.env` file with the connection details:
```env
NEO4J_ENABLED=true
NEO4J_URI=neo4j+s://xxxxx.databases.neo4j.io
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=<your-password>
```

## Verify Connection

Test your connection:

```bash
php artisan neo4j:health-check
```

Expected output:
```
✓ Neo4j connection successful
✓ Version: 5.x
✓ Database: neo4j
```

## Initialize Graph Schema

Once connected, initialize the graph database:

```bash
# Create schema and indexes
php artisan graph:init

# Create Neo4j-specific indexes
php artisan neo4j:create-indexes

# Verify
php artisan graph:stats
```

## Sync Existing Data (Optional)

If you have existing data in PostgreSQL, sync it to the graph:

```bash
# Sync all data
php artisan graph:sync --all

# Or sync specific types
php artisan graph:sync --type=laws
php artisan graph:sync --type=decisions
php artisan graph:sync --type=cases
```

## AuraDB Management

### Using Aura CLI

```bash
# List instances
aura-cli instance list

# Get instance details
aura-cli instance get --name ai-legal-war-machine

# Pause instance
aura-cli instance pause --name ai-legal-war-machine

# Resume instance
aura-cli instance resume --name ai-legal-war-machine

# Delete instance
aura-cli instance delete --name ai-legal-war-machine
```

### Using Web Console

Visit https://console.neo4j.io/ to:
- View instance status
- Access Neo4j Browser (query interface)
- Monitor usage and metrics
- Download backups
- Manage settings

## Accessing Neo4j Browser

1. Go to https://console.neo4j.io/
2. Click **Open** on your instance
3. Login with your credentials
4. Run Cypher queries:

```cypher
// Count nodes
MATCH (n) RETURN count(n);

// View laws
MATCH (l:Law) RETURN l LIMIT 10;

// View relationships
MATCH (d:Decision)-[r:CITES]->(l:Law)
RETURN d.title, type(r), l.title
LIMIT 10;
```

## Troubleshooting

### "Instance paused"

AuraDB Free instances pause after 3 days of no writes:

```bash
# Resume via CLI
aura-cli instance resume --name ai-legal-war-machine

# Or via web console
```

### Connection timeout

Check if instance is running:
```bash
aura-cli instance get --name ai-legal-war-machine --output json | grep status
```

Should show: `"status": "running"`

### "Payment method required"

Even for free tier, you need a payment method on file to:
- Use API credentials
- Create instances via CLI

Add payment method at: https://console.neo4j.io/ → Billing

(You won't be charged for Free tier usage)

### Wrong credentials

Reset password:
1. Visit https://console.neo4j.io/
2. Click instance → **Connection details**
3. Click **Reset password**
4. Update `.env` with new password

## Free Tier Limits

| Resource | Limit |
|----------|-------|
| Instances | 1 per account |
| Nodes | 200,000 |
| Relationships | 400,000 |
| Storage | Based on node/relationship limits |
| Queries | Unlimited |
| Auto-pause | 3 days of no writes |
| Deletion | 30 days after pause |

## Upgrading

Need more resources? Upgrade to Professional:

```bash
aura-cli instance migrate --instance-id <ID> --tier professional
```

Or via web console → Instance → **Migrate**

Professional tier pricing: ~$65/month (varies by region)

## Environment Variables Reference

```env
# AuraDB Cloud Connection (recommended)
NEO4J_ENABLED=true
NEO4J_URI=neo4j+s://xxxxx.databases.neo4j.io
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your-password

# Legacy local connection (if using local Neo4j)
NEO4J_SCHEME=bolt
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_USER=neo4j
NEO4J_PASSWORD=pass
NEO4J_DATABASE=neo4j
```

The app automatically detects connection type based on `NEO4J_URI`.

## Additional Resources

- [AuraDB Documentation](https://neo4j.com/docs/aura/)
- [Aura CLI Guide](https://neo4j.com/docs/aura/aura-cli/)
- [Neo4j Browser Guide](https://neo4j.com/docs/browser-manual/current/)
- [Cypher Query Language](https://neo4j.com/docs/cypher-manual/current/)
- [AuraDB Free FAQ](https://support.neo4j.com/s/article/16094506528787-Support-resources-and-FAQ-for-Aura-Free-Tier)

## Support

- Neo4j Community Forum: https://community.neo4j.com/
- AuraDB Support: https://support.neo4j.com/
- Project Issues: Check `docs/TROUBLESHOOTING.md`
