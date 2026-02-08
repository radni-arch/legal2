# AI Legal War Machine - Documentation Index

Welcome to the comprehensive documentation for the AI Legal War Machine - a suite of AI-powered legal defense tools for Croatian criminal defense attorneys.

## 📋 Project Status

**[CURRENT_STATUS.md](CURRENT_STATUS.md)** - Single source of truth for project status, capabilities, and next steps.

---

## 🚀 Quick Start

**New to the project?** Start here:
1. [Current Status](CURRENT_STATUS.md) - What's implemented and what's next
2. [Installation & Setup](../README.md#installation) - Get the system running
3. [Architecture Overview](architecture/README.md) - Understand the system design
4. [Testing Guide](TESTING.md) - Run tests and verify your setup
5. [Legal Playground](LEGAL_PLAYGROUND.md) - Try out the modules interactively

**Common Tasks:**
- [Run tests](TESTING.md#quick-start): `composer test`
- [Start development](../CLAUDE.md#development): `composer dev`
- [Deploy to production](sprints/production/) - Production deployment guides
- [Add new module](implementation/guides/) - Implementation guides

---

## 📚 Documentation Sections

### 🏗️ Architecture & Design

Understand how the system works:

- [**Architecture Overview**](architecture/README.md) - System design and components
- [**Legal Reasoning System**](architecture/legal-reasoning-system.md) - AI reasoning engine
- [**Misconduct Module Architecture**](architecture/misconduct-module.md) - Prosecutorial misconduct detection
- [**Distributed Processing**](architecture/distributed-processing.md) - Queue and worker architecture
- [**Legal Playground**](architecture/legal-playground.md) - Interactive testing interface

### 🤖 Services

Core services that power the platform:

#### Autonomous Agents
- [**Agent API**](AGENT_API.md) - API for autonomous agents
- [**Odluke Search Agent**](ODLUKE_SEARCH_AGENT.md) - Court decision search agent
- [**Multi-Agent Collaboration**](MULTI_AGENT_COLLABORATION.md) - Agent coordination
- [**Decision Discovery Model**](decision-discovery-model-usage.md) - Court decision discovery
- [Service Documentation](services/agents/) - Detailed agent docs

#### Graph & Search
- [**Graph Database**](services/graph/) - Neo4j integration and graph RAG
- [**Vector Search**](services/search/) - Semantic search across legal documents
- [**Search API**](services/search/) - Search endpoints and usage

#### MCP (Model Context Protocol)
- [**MCP Tools**](services/mcp/) - MCP server and tools
- [**MCP Implementation**](services/mcp/) - Integration details

#### Textract & OCR
- [**Textract Pipeline**](services/textract/) - AWS Textract document processing
- [**OCR Workflow**](services/textract/) - PDF text extraction

#### Other Services
- [**EKOM Integration**](services/other/) - Croatian e-courts system
- [**Eoglasna Integration**](services/other/) - Public court notices
- [**Odluke.sudovi.hr Client**](services/other/) - Court decision ingestion

### 📦 Modules

Legal defense functionality:

- [**Evidence Analysis**](modules/evidence/) - Evidence evaluation and suppression motions
- [**Prosecutorial Misconduct**](modules/misconduct/) - Detection and complaint generation
- [**Defense Strategy**](modules/defense/) - Defense recommendations
- [**Home Search Abuse**](modules/home-search/) - Disproportionate warrant detection
- [**Topic Framework**](modules/topic-framework/) - Modular abuse detection system
  - Drug Charge Abuse Detection
  - Regional Statistics & Comparison

### 🧪 Testing

Quality assurance and testing:

- [**Testing Guide**](TESTING.md) - Comprehensive testing documentation
- [**Test Coverage**](testing/summaries/) - Coverage reports and metrics
- [**Integration Tests**](testing/integration/) - Integration test guides
- [**Keyword Extraction Tests**](TESTS-KEYWORD-EXTRACTION.md) - Specific test documentation

### 🚀 Sprints & Production

Production deployment and sprint planning:

- [**Production Sprints Index**](plans/PRODUCTION_SPRINTS_INDEX.md) - Overview of all sprints
- [**Sprint 9: Database & Caching**](plans/SPRINT_9_DATABASE_CACHING.md)
- [**Sprint 10: Queues & Neo4j**](plans/SPRINT_10_QUEUES_NEO4J.md)
- [**Sprint 11: Monitoring & Logging**](plans/SPRINT_11_MONITORING_LOGGING.md)
- [**Sprint 12: Docs & Go-Live**](plans/SPRINT_12_DOCS_GOLIVE.md)
- [**Production Tasks**](plans/SPRINT_9_12_PRODUCTION_TASKS.md) - All production tasks
- [**Production Wrap-Up Plan**](plans/2025-11-08-production-wrap-up-plan.md)

**Sprint Archives:**
- [Completed Sprints](sprints/completed/) - Successfully finished sprints
- [Comprehensive Sprints](sprints/comprehensive/) - Detailed sprint documentation
- [Production Sprints](sprints/production/) - Production deployment sprints
- [Archived Sprints](sprints/archive/) - Historical sprint data

### 🛠️ Implementation

Implementation guides and roadmaps:

- [**Implementation Guides**](implementation/guides/) - How to implement features
- [**Module Implementations**](implementation/modules/) - Module-specific guides
- [**Task Tracking**](tasks/) - Individual task documentation
  - [Task 1.1: Register MCP Tools](tasks/TASK-1.1-REGISTER-MCP-TOOLS.md)
  - [Task 2.1: Implement LLM Planning](tasks/TASK-2.1-IMPLEMENT-LLM-PLANNING.md)

### 🔌 API Documentation

API endpoints and usage:

- [**API Documentation**](API_DOCUMENTATION.md) - Complete API reference
- [**Agent API**](AGENT_API.md) - Autonomous agent endpoints
- [**API Insights**](api/INSIGHTS.md) - API design insights

### 🔒 Security

Authentication and security:

- [**Security Documentation**](security/) - Authentication, authorization, security practices
- [**Access Control**](security/) - User permissions and roles
- [**API Security**](security/) - API token management

### ⚙️ Server Configuration

Server setup and configuration:

- [**PostgreSQL Setup**](server-config/) - Database configuration
- [**Neo4j Setup**](server-config/) - Graph database configuration
- [**Redis Configuration**](server-config/) - Caching setup
- [**Supervisor**](server-config/supervisor/) - Process management
- [**Logrotate**](server-config/logrotate/) - Log rotation configuration
- [**Optimization**](config/optimization/) - Performance optimization

### 🔄 Migration Guides

Service migrations and refactoring:

- [**Migration Guides**](migration-guides/) - Service migration documentation
- [**Court Decision Refactoring**](COURT_DECISION_REFACTORING.md) - Decision service refactoring
- [**Odluke Agent Fix**](ODLUKE_AGENT_FIX.md) - Agent refactoring guide

### 📊 Sprint History

Development tracking and status:

- [**Sprint History**](sprints/README.md) - Complete sprint history and roadmap
- [**Current Status**](CURRENT_STATUS.md) - Latest project status

### 📂 Additional Resources

- [**Changelog**](CHANGELOG.md) - Version history and changes
- [**Playbooks**](playbooks/) - Operational playbooks
  - [Discovery E2E](playbooks/discovery-e2e.md) - End-to-end discovery workflow
- [**Examples**](examples/) - Code examples and samples
- [**Flows**](flows/) - Process flow diagrams
- [**Analysis**](analysis/) - System analysis documents
  - [Weak Sectors Analysis](analysis/weak-sectors/) - Performance bottleneck analysis
- [**Infrastructure**](INFRASTRUCTURE_INVENTORY.md) - Infrastructure documentation
  - [Infrastructure Summary](INFRASTRUCTURE_SUMMARY.md)
  - [Infrastructure Inventory](INFRASTRUCTURE_INVENTORY.md)
- [**Milestones**](MILESTONE_F_SUMMARY.md) - Project milestone summaries
  - [Milestone B Improvements](MILESTONE_B_IMPROVEMENTS.md)
  - [Milestone F Summary](MILESTONE_F_SUMMARY.md)
- [**Training Materials**](training/) - Learning resources

### 📜 Archive

Historical documentation:

- [**Archive**](archive/) - Archived documentation
- [**2025-12 Cleanup**](archive/2025-12-cleanup/) - December 2025 cleanup (sprints, reports, assessments)
- [**2025-11 Archive**](archive/2025-11/) - November 2025 archives

---

## 🔍 Search Tips

**Finding Documentation:**
- Use your IDE's global search (Ctrl+Shift+F / Cmd+Shift+F)
- Search by keyword: "vector search", "neo4j", "textract", "agent"
- Search by module: "misconduct", "evidence", "defense"
- Search by technology: "PostgreSQL", "Redis", "OpenAI"

**Common Queries:**
- "How do I add a new module?" → [Implementation Guides](implementation/guides/)
- "How do vector stores work?" → [Services: Search](services/search/)
- "How do I run tests?" → [Testing Guide](TESTING.md)
- "How do agents work?" → [Services: Agents](services/agents/)
- "What's the database schema?" → [Server Config](server-config/)
- "How do I deploy?" → [Production Sprints](sprints/production/)

**File Naming Conventions:**
- `README.md` - Section overview and index
- `*_GUIDE.md` - Step-by-step guides
- `*_API.md` - API documentation
- `*_SUMMARY.md` - Summary documents
- `TASK-*.md` - Task tracking documents
- `SPRINT_*.md` - Sprint planning documents

---

## 📝 Quick Reference

### Common Commands

| Command | Purpose |
|---------|---------|
| `composer dev` | Start all development services |
| `composer test` | Run quick tests (SQLite) |
| `composer test:integrated` | Run full tests (PostgreSQL) |
| `composer test:setup` | Setup test database |
| `php artisan serve` | Start web server |
| `php artisan queue:work --queue=textract,agents,default` | Start queue worker |
| `php artisan pail --timeout=0` | Watch logs |
| `npm run dev` | Start Vite (frontend) |
| `./vendor/bin/pint` | Format code |
| `php artisan test:setup-db` | Reset test database |

### Key File Locations

| Path | Description |
|------|-------------|
| `app/Modules/` | Legal defense modules |
| `app/Services/` | Core services (vector stores, agents, etc.) |
| `app/Agents/` | Autonomous AI agents |
| `app/Mcp/Tools/` | MCP tools |
| `app/Pipelines/Textract/` | Textract pipeline steps |
| `config/` | Configuration files |
| `tests/` | Test suite |
| `docs/` | This documentation |

### Key Concepts

| Concept | Description | Learn More |
|---------|-------------|------------|
| **Vector Stores** | Semantic search over legal documents | [Services: Search](services/search/) |
| **Graph RAG** | Neo4j-based knowledge graph | [Services: Graph](services/graph/) |
| **Autonomous Agents** | Self-evaluating research agents | [Services: Agents](services/agents/) |
| **MCP** | Model Context Protocol integration | [Services: MCP](services/mcp/) |
| **Textract** | AWS document OCR pipeline | [Services: Textract](services/textract/) |
| **Topic Framework** | Modular abuse detection | [Modules: Topics](modules/topic-framework/) |
| **Odluke** | Court decision ingestion | [Services: Other](services/other/) |

### Croatian Legal Authorities

| Code | Full Name | Description |
|------|-----------|-------------|
| **ZKP** | Zakon o kaznenom postupku | Criminal Procedure Act |
| **KZ** | Kazneni zakon | Criminal Code |
| **Ustav RH** | Ustav Republike Hrvatske | Croatian Constitution |
| **ZODO** | Zakon o Državnom odvjetništvu | State Attorney Act |

---

## 🤝 Contributing to Documentation

### Documentation Standards

1. **Use clear, descriptive titles** - Make it easy to find
2. **Include code examples** - Show, don't just tell
3. **Link related docs** - Create a knowledge web
4. **Keep it updated** - Documentation rots quickly
5. **Add diagrams** - A picture is worth 1000 words

### Adding New Documentation

1. **Choose the right section** - Use the structure above
2. **Create meaningful filenames** - Use kebab-case, be descriptive
3. **Add to relevant README** - Update section README.md files
4. **Link from this index** - Add to this master index
5. **Test all links** - Ensure navigation works

### File Organization

```
docs/
├── README.md (this file)           # Master index
├── section/
│   ├── README.md                   # Section overview
│   ├── specific-topic.md           # Detailed docs
│   └── subsection/
│       ├── README.md               # Subsection overview
│       └── detailed-guide.md       # Specific guides
```

### Markdown Style Guide

- Use ATX-style headers (`#`, `##`, `###`)
- Include table of contents for long documents
- Use code blocks with language hints (```php, ```bash, etc.)
- Use relative links for internal documentation
- Use tables for structured data
- Add horizontal rules (`---`) to separate major sections

---

## 📞 Support

**Issues?**
- Check [Testing Guide](TESTING.md) for common problems
- Review [Troubleshooting](../CLAUDE.md#troubleshooting) in CLAUDE.md
- Check [Production Sprints](sprints/production/) for deployment issues

**Questions?**
- Search this documentation first
- Check the [main README](../README.md)
- Review [CLAUDE.md](../CLAUDE.md) for development guidance

---

## 📊 Documentation Statistics

- **Total Documentation Files**: ~250 markdown files
- **Major Sections**: 15 top-level categories
- **Organization**: Hierarchical structure with 2-3 levels
- **Coverage**: Architecture, Services, Modules, Testing, Deployment, API

---

**Last Updated**: 2025-12-04
**Documentation Version**: 2.0 (Post-Cleanup Consolidation)

