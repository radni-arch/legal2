# Implementation Roadmap - AI Legal War Machine

**Goal:** Transform from "Good RAG System" to "True AI Legal War Machine"

**Current Score:** 7.3/10
**Target Score:** 9.5/10

---

## **📋 Quick Start**

### **What to Do First (Critical Path)**

**Week 1-2:**
1. ✅ Read: `docs/SPRINT_PLAN.md` - Complete overview
2. 🚨 **DO THIS FIRST:** `docs/tasks/TASK-2.1-IMPLEMENT-LLM-PLANNING.md`
   - Make agent truly autonomous
   - 8 hours, high complexity
   - **This unlocks true AI capability**

3. 🚨 **THEN DO THIS:** `docs/tasks/TASK-1.1-REGISTER-MCP-TOOLS.md`
   - Expose legal knowledge via MCP
   - 4 hours, low complexity
   - **This unlocks external integration**

**Week 3-4:**
4. Continue with Sprint 1 tasks (MCP resource templates, prompts)
5. Continue with Sprint 2 tasks (background execution, streaming)

---

## **📊 Progress Tracking**

### **Sprint Status**

| Sprint | Focus | Status | Progress |
|--------|-------|--------|----------|
| Sprint 1 | MCP Foundation | 🔴 Not Started | 0/4 tasks |
| Sprint 2 | Autonomous Agent | 🔴 Not Started | 0/5 tasks |
| Sprint 3 | Search Intelligence | 🔴 Not Started | 0/3 tasks |
| Sprint 4 | Reliability | 🔴 Not Started | 0/3 tasks |
| Sprint 5 | UX Enhancements | 🔴 Not Started | 0/2 tasks |

**Overall Progress:** 0/17 tasks (0%)

---

## **🎯 Success Metrics**

### **When Sprint 1 is Done:**
- ✅ External systems can access Croatian legal knowledge
- ✅ Claude Desktop can search laws and court decisions
- ✅ MCP tools documented and tested
- **Score: 7.8/10** (+0.5)

### **When Sprint 2 is Done:**
- ✅ Agent makes intelligent, LLM-driven decisions
- ✅ Background execution prevents timeouts
- ✅ Real-time progress monitoring
- ✅ Can resume interrupted research
- **Score: 8.9/10** (+1.1, total: +1.6)

### **When All Sprints Done:**
- ✅ Truly autonomous AI legal assistant
- ✅ Query rewriting improves search quality
- ✅ Law version tracking ensures accuracy
- ✅ Production-ready reliability
- **Score: 9.5/10** (+0.6, total: +2.2)

---

## **🚀 Quick Task Reference**

### **Critical (P0) - Do These First**

1. **TASK-2.1:** Implement LLM-Based Planning
   - File: `app/Agents/AutonomousResearchAgent.php`
   - Time: 8 hours
   - Impact: Makes agent truly autonomous ⭐⭐⭐

2. **TASK-1.1:** Register MCP Tools
   - File: `routes/mcp.php`
   - Time: 4 hours
   - Impact: Unlocks external integration ⭐⭐⭐

3. **TASK-2.3:** Background Agent Execution
   - Files: Create `app/Jobs/RunAutonomousResearchJob.php`
   - Time: 6 hours
   - Impact: Prevents timeouts ⭐⭐

### **High Priority (P1) - Do These Next**

4. **TASK-1.2:** MCP Resource Templates
   - File: `routes/mcp.php`
   - Time: 6 hours
   - Impact: Direct law/decision access ⭐⭐

5. **TASK-3.1:** Query Rewriting
   - File: Create `app/Services/QueryRewriter.php`
   - Time: 8 hours
   - Impact: Better search results ⭐⭐

6. **TASK-4.1:** Law Version Tracking
   - File: Create migration + service
   - Time: 10 hours
   - Impact: Legal accuracy ⭐⭐

### **Medium Priority (P2) - After Critical Items**

7. **TASK-4.2:** OCR Error Recovery
8. **TASK-4.3:** Result Highlighting
9. **TASK-5.1:** Faceted Search UI

---

## **📁 File Structure**

```
ai-legal-war-machine/
├── docs/
│   ├── SPRINT_PLAN.md              ← Complete sprint overview
│   ├── IMPLEMENTATION_ROADMAP.md   ← This file
│   ├── tasks/
│   │   ├── TASK-1.1-REGISTER-MCP-TOOLS.md
│   │   ├── TASK-2.1-IMPLEMENT-LLM-PLANNING.md
│   │   └── [More task cards to be created]
│   ├── mcp/
│   │   └── [MCP documentation - created during tasks]
│   ├── agents/
│   │   └── [Agent documentation - created during tasks]
│   └── search/
│       └── [Search documentation - created during tasks]
└── app/
    ├── Agents/
    │   └── AutonomousResearchAgent.php  ← Task 2.1
    ├── Tools/
    │   ├── OdlukeSearchTool.php
    │   ├── LawArticlesSearchTool.php
    │   └── [5 tools total]
    ├── Services/
    │   ├── QueryRewriter.php            ← Task 3.1 (create)
    │   ├── LawVersioningService.php     ← Task 4.1 (create)
    │   └── [Many services]
    ├── Jobs/
    │   └── RunAutonomousResearchJob.php ← Task 2.3 (create)
    └── Http/
        └── Livewire/
            └── [UI components]
```

---

## **⚡ Quick Commands**

### **Start Development**
```bash
# Ensure environment is ready
php artisan config:clear
php artisan cache:clear

# Run migrations
php artisan migrate

# Start queue worker (for background jobs)
php artisan queue:work --queue=research
```

### **Test MCP Tools (After Task 1.1)**
```bash
# Set your MCP token
export MCP_API_TOKEN="your-token-here"

# Test law search
curl -X POST http://localhost/mcp/message \
  -H "Authorization: Bearer ${MCP_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"method":"tools/call","params":{"name":"law_search","arguments":{"query":"ugovor o radu","limit":5}}}'
```

### **Test Autonomous Agent (After Task 2.1)**
```bash
# Via tinker
php artisan tinker

# In tinker:
$agent = new \App\Agents\AutonomousResearchAgent();
$run = $agent->startRun("Research Croatian labor law on overtime", [], ['max_iterations' => 5]);
$completed = $agent->executeRun($run);
dd($completed->final_output);
```

### **Monitor Progress**
```bash
# Watch agent runs
php artisan agent:runs --watch

# View logs
tail -f storage/logs/laravel.log | grep -i agent
```

---

## **🎓 Learning Path**

### **For New Developers**

**Day 1: Understand the System**
- Read: `docs/SPRINT_PLAN.md`
- Review: Current assessment in main report
- Explore: `app/Agents/`, `app/Tools/`, `app/Services/`

**Day 2-3: MCP Foundation**
- Complete: Task 1.1 (Register MCP Tools)
- Test: All 6 tools via curl
- Document: Tool usage examples

**Day 4-6: Autonomous Agent**
- Complete: Task 2.1 (LLM Planning)
- Test: Agent with real legal queries
- Observe: Planning decisions in logs

**Week 2: Advanced Features**
- Complete: Task 2.3 (Background Execution)
- Complete: Task 1.2 (Resource Templates)
- Complete: Task 3.1 (Query Rewriting)

---

## **📚 Required Reading**

Before starting development:

1. **System Architecture:**
   - Main assessment report (provided separately)
   - `docs/SPRINT_PLAN.md`

2. **For Task 1.1 (MCP):**
   - https://modelcontextprotocol.io/docs
   - `routes/mcp.php` (current state)

3. **For Task 2.1 (Agent):**
   - `app/Agents/AutonomousResearchAgent.php` (current state)
   - `app/Services/AgentToolbox.php`

4. **For Task 3.1 (Search):**
   - `app/Services/UnifiedSearchService.php`
   - `app/Services/RagOrchestrator.php`

---

## **🔍 Debugging Guide**

### **Common Issues**

**Issue: MCP tools return 404**
- Check: `routes/mcp.php` registered correctly
- Check: Tool classes exist in `app/Tools/`
- Check: MCP_API_TOKEN set in `.env`

**Issue: Agent planning fails**
- Check: OpenAI API key in `.env`
- Check: `config/openai.php` model settings
- Check: Logs in `storage/logs/laravel.log`

**Issue: Agent runs timeout**
- Solution: Implement Task 2.3 (background execution)
- Temporary: Reduce `max_iterations` to 3-5

**Issue: Search returns no results**
- Check: Database has ingested data
- Check: pgvector extension installed
- Check: Similarity threshold not too high (>0.9)

---

## **💰 Cost Tracking**

### **OpenAI API Costs**

**Per Agent Run (10 iterations):**
- Planning: 10 × $0.00015 = $0.0015
- Insight extraction: 10 × $0.0001 = $0.001
- Embeddings: varies
- **Total: ~$0.003 per run**

**Per Month (1000 agent runs):**
- Agent operations: $3.00
- Search embeddings: ~$2.00
- **Total: ~$5.00/month**

Very affordable for a powerful AI legal assistant!

---

## **🎉 Victory Conditions**

You'll know you're done when:

✅ **Sprint 1 Victory:**
- External systems can query Croatian legal knowledge via MCP
- Claude Desktop integration works
- All 6 MCP tools tested and documented

✅ **Sprint 2 Victory:**
- Agent generates intelligent, context-aware research plans
- Background jobs handle long research without timeouts
- Real-time progress visible in UI
- Agent can resume from checkpoints

✅ **Sprint 3 Victory:**
- Search quality measurably improved with query rewriting
- Context compression reduces token costs
- Legal prompts produce accurate, well-cited answers

✅ **Sprint 4 Victory:**
- Law amendments detected automatically
- OCR failures handled gracefully
- Search results highlight matches

✅ **Sprint 5 Victory:**
- Faceted filters improve UX
- Analytics dashboard shows usage patterns
- System ready for production

---

## **🚨 Red Flags**

Stop and reassess if:

- ❌ Task takes 2x estimated time → Ask for help
- ❌ Breaking existing tests → Review approach
- ❌ OpenAI costs >$1 per agent run → Optimize prompts
- ❌ Agent gets stuck in loops → Add loop detection
- ❌ MCP tools consistently error → Check tool implementations

---

## **📞 Getting Help**

**Documentation Issues:**
- Check: Relevant task card in `docs/tasks/`
- Check: Main sprint plan in `docs/SPRINT_PLAN.md`

**Code Issues:**
- Review: Existing implementation patterns
- Check: Laravel best practices
- Search: Similar code in services/agents

**Concept Issues:**
- Review: Main assessment report
- Research: MCP documentation
- Research: RAG patterns

---

**Last Updated:** 2025-10-26
**Status:** Ready for sprint execution
**Next Action:** Complete Task 2.1 or Task 1.1
