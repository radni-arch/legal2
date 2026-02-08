# Autonomous Discovery Training Screenshots

This directory contains screenshots referenced in the training guide: `docs/training/autonomous-discovery.md`

## Screenshot List

Below is the complete list of screenshots needed for the training guide. Create these screenshots during the training session or system demonstration.

### Authentication & Access

| # | Filename | Description | Required Elements |
|---|----------|-------------|------------------|
| 01 | `01-login.png` | Login screen | Email/password fields, Login button, App logo |

### Dashboard

| # | Filename | Description | Required Elements |
|---|----------|-------------|------------------|
| 02 | `02-dashboard-overview.png` | Main dashboard view | Navigation menu, Key metrics panel, Latest run status |
| 12 | `12-overview-tab.png` | Dashboard overview tab | Total runs, Evaluated, Ingested, Success rate metrics |
| 13 | `13-run-history.png` | Run history table | Paginated runs, Status indicators, Filter options |

### Discovery Process

| # | Filename | Description | Required Elements |
|---|----------|-------------|------------------|
| 03 | `03-topic-generation.png` | AI-generated topics list | 5 topics in Croatian, Topic relevance scores |
| 04 | `04-search-results.png` | Search results per topic | Decision count per topic, Court distribution |
| 05 | `05-scoring-results.png` | AI scoring interface | Decisions with scores 0-100, AI reasoning text |
| 06 | `06-filtering.png` | Filtering visualization | Score distribution chart, Threshold line at 70 |
| 07 | `07-ingestion-progress.png` | Live ingestion progress | Progress bar, Current decision being processed |
| 08 | `08-completion-summary.png` | Run completion screen | Final stats, Duration, Success checkmark |

### Manual Operations

| # | Filename | Description | Required Elements |
|---|----------|-------------|------------------|
| 09 | `09-manual-run-config.png` | Manual run configuration | Topics slider, Threshold input, Start button |
| 10 | `10-api-trigger.png` | API response for trigger | JSON response, Run ID, Status URL |
| 11 | `11-live-progress.png` | Real-time progress monitor | Current stage, ETA, Topic checklist |

### Inspection Tools

| # | Filename | Description | Required Elements |
|---|----------|-------------|------------------|
| 14 | `14-run-details.png` | Detailed run information | Topics breakdown, Score distribution, Top decisions |
| 15 | `15-decision-inspector.png` | Decision detail view | Full metadata, AI evaluation, Ingestion status |
| 17 | `17-qa-checklist.png` | QA checklist interface | Checklist items, Status indicators |
| 18 | `18-manual-override.png` | Manual override screen | Decision card, Override actions, Justification field |

### API & Integration

| # | Filename | Description | Required Elements |
|---|----------|-------------|------------------|
| 16 | `16-postman-collection.png` | Postman collection view | Request folders, Variables, Test results |

## Screenshot Guidelines

### Image Specifications

- **Format:** PNG
- **Resolution:** 1920x1080 recommended (can be scaled down)
- **Quality:** High quality, avoid compression artifacts
- **File Size:** < 500KB per image (use compression if needed)
- **Color:** Full color, ensure good contrast

### Content Requirements

#### DO Include:
- ✅ Clear, readable text
- ✅ Relevant UI elements highlighted if needed
- ✅ Representative data (use test/demo data)
- ✅ Consistent branding (keep app logo/name visible)
- ✅ Annotations or arrows if helpful

#### DON'T Include:
- ❌ Real sensitive data (use sample data)
- ❌ Personal information (emails, names, etc.)
- ❌ API tokens or credentials
- ❌ Localhost URLs (use generic "your-domain.com")
- ❌ Development error messages

### Capturing Screenshots

#### Browser-Based Screenshots

1. **Prepare the view:**
   - Zoom: 100% (Ctrl+0)
   - Hide browser dev tools
   - Clear console errors
   - Use consistent browser (Chrome recommended)

2. **Capture:**
   - Windows: `Win + Shift + S` → Select area
   - Mac: `Cmd + Shift + 4` → Select area
   - Linux: `Gnome Screenshot` or `Flameshot`

3. **Save:**
   - Name according to list above
   - Place in this directory
   - Verify image loads correctly

#### Command Line Output Screenshots

For CLI output (e.g., `php artisan decisions:discover`):

1. Use a clean terminal window
2. Set terminal size: 120x30
3. Use readable font size (14-16pt)
4. Use color scheme with good contrast
5. Capture full output from start to finish

### Placeholder Images

Until real screenshots are available, you can create placeholders:

```bash
# Install ImageMagick (if not installed)
# sudo apt-get install imagemagick

# Generate placeholder
convert -size 1920x1080 xc:lightgray \
  -pointsize 72 -fill black \
  -gravity center -annotate +0+0 "Screenshot:\n01-login.png" \
  01-login.png
```

Or use online tools:
- https://placeholder.com/
- https://placehold.co/

Example placeholder:
```
https://placehold.co/1920x1080/gray/white?text=Screenshot+01+Login
```

### Annotation Tools

If you need to add arrows, highlights, or text:

- **Snagit** (Windows/Mac) - Professional tool
- **Greenshot** (Windows) - Free with annotation
- **Skitch** (Mac) - Simple annotation tool
- **GIMP** (All platforms) - Advanced editing
- **draw.io** (Web) - Add diagrams/arrows

### Quality Checklist

Before finalizing screenshots:

- [ ] Image is clear and readable
- [ ] No sensitive data visible
- [ ] Filename matches list above
- [ ] File size is reasonable (< 500KB)
- [ ] Image displays correctly in markdown
- [ ] Annotations are clear and helpful
- [ ] Consistent styling across all images

## Screenshot Status

Track screenshot completion:

| Status | Count | Percentage |
|--------|-------|------------|
| ✅ Complete | 0 | 0% |
| 🔄 In Progress | 0 | 0% |
| ⏸️ Pending | 18 | 100% |

**Last Updated:** 2025-01-29

## Usage in Training Guide

Screenshots are referenced in the training guide using:

```markdown
![Dashboard Login](../images/autonomous-discovery/01-login.png)
*Screenshot 1: Login screen*
```

Ensure all referenced images exist before conducting training sessions.

## Creating Screenshot Documentation

For live training sessions:

1. Open application in demo environment
2. Follow training guide step-by-step
3. Capture screenshots at each numbered step
4. Save with correct filenames
5. Review and annotate if needed
6. Update status tracker above

## Alternative: Screen Recording

Instead of individual screenshots, consider recording:

1. **Screen recording** of full workflow
2. Export key frames as screenshots
3. Use tools like:
   - OBS Studio (free, all platforms)
   - Loom (web-based)
   - Camtasia (professional)

Video file: `autonomous-discovery-demo.mp4`

---

**Contact:** For questions about screenshot requirements, contact the training team.
