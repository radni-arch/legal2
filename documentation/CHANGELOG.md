# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Search service layer (LawSearchService, DecisionSearchService, CaseSearchService)
- Vector search support in MCP tools
- Hybrid search combining vector and keyword approaches
- BaseSearchService abstract class for shared vector operations
- SearchServiceInterface contract for all search services
- Comprehensive test suite (49 tests across unit, feature, and integration levels)
- ARCHITECTURE.md documentation
- 18 new granular search tool handlers in AutonomousResearchAgent
- search_type parameter (vector/keyword/hybrid) for MCP search tools

### Changed
- MCP tools now delegate to search services
- AgentToolbox refactored to use search services
- AutonomousResearchAgent now uses search services directly
- LawSearchTool, DecisionSearchTool, CaseSearchTool use service layer
- MCP tools support advanced search types (vector/keyword/hybrid)

### Deprecated
- `AgentToolbox::vectorSearch()` - use search services directly
- `AgentToolbox::lawLookup()` - use `LawSearchService::lookupByNumber()`
- `AgentToolbox::decisionLookup()` - use `DecisionSearchService::lookupByCriteria()`

### Removed
- Direct database access from AgentToolbox (moved to services)
- Duplicate vector search logic across multiple components
