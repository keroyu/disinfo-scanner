<!--
## Sync Impact Report
- Version: 1.0.0 → 1.1.0 (MINOR: New principle added)
- Modified principles: None
- Added principles: VI. Timezone Consistency (NEW)
- Removed principles: None
- Templates requiring updates:
  - ✅ plan-template.md (Constitution Check section - already generic)
  - ✅ spec-template.md (No updates needed - technology-agnostic)
  - ✅ tasks-template.md (No updates needed - follows principles)
- Follow-up TODOs: None (principle integrated)
-->

# DISINFO_SCANNER Constitution

A YouTube comment data management system MVP designed to collect and query video comment records, with future capability to detect coordinated negative campaigns.

## Core Principles

### I. Test-First Development

Every feature begins with tests written **before** implementation. This project enforces a strict Red-Green-Refactor cycle:
- **Red**: Write tests for desired behavior (tests fail initially)
- **Green**: Implement minimal code to pass tests
- **Refactor**: Improve code while keeping tests passing

**Rationale**: TDD ensures correctness, reduces bugs in production, and creates living documentation through test cases. For a system handling real YouTube data, reliability is non-negotiable.

---

### II. API-First Design

All functionality is exposed through clear, well-documented APIs before UI or internal implementation details are finalized.
- Every feature MUST have a defined contract (REST endpoint, CLI interface, or library function signature)
- APIs MUST support both human-readable and structured (JSON) output
- Error responses MUST include actionable context (not just error codes)

**Rationale**: API-first design decouples components, enables independent testing, and simplifies future extensions (e.g., adding web UI or analytics without touching data collection logic).

---

### III. Observable Systems

All systems MUST produce structured logs and maintain traceability for debugging and monitoring.
- Text-based I/O ensures debuggability: stdout for results, stderr for errors
- Structured logging (JSON-formatted logs) required for all operations
- Every request/operation MUST include a unique trace ID for audit trails
- Data collection operations MUST log source, timestamp, and record count

**Rationale**: As this system grows to detect campaigns, observability is critical for understanding what data was collected, when, and from which sources. This enables post-hoc analysis of potential attacks.

---

### IV. Contract Testing

Service boundaries MUST be validated through contract tests, not implementation details.
- New API contracts MUST have contract tests before implementation
- Contract changes MUST update existing contract tests and trigger integration tests
- Inter-service communication (if applicable) MUST be tested at service boundaries
- Shared schemas (e.g., comment data structure) MUST have contract tests

**Rationale**: Contract testing ensures different components (YouTube API client, data store, analysis engine) can be swapped or updated independently without breaking the overall system.

---

### V. Semantic Versioning

All releases follow MAJOR.MINOR.PATCH versioning with clear breaking-change documentation.
- **MAJOR**: Backward-incompatible changes (API signature changes, data schema migrations)
- **MINOR**: New functionality added in backward-compatible manner
- **PATCH**: Bug fixes, internal improvements, no API changes

**Rationale**: Clear versioning prevents silent failures when code updates cause incompatibility, critical for a system that may be integrated with external tools or dashboards.

---

### VI. Timezone Consistency

All datetime data MUST be handled with explicit timezone awareness to prevent data corruption and analysis errors.
- Database MUST store all timestamps in UTC (Coordinated Universal Time)
- Backend MUST convert UTC to appropriate display timezone (GMT+8 / Asia/Taipei) before sending to frontend
- Frontend MUST display all times with explicit timezone indicators (e.g., "2025-11-20 14:30 (GMT+8)")
- Time-based queries and filters MUST operate on properly converted timezone data
- No timezone conversions MUST occur in the database layer (store UTC, convert in application)

**Rationale**: YouTube data originates in UTC; inconsistent timezone handling creates invisible data corruption where the same timestamp means different wall-clock times in queries vs displays. For a disinformation detection system analyzing time-based patterns (e.g., coordinated night-time posting), timezone bugs can cause false positives/negatives and undermine trust in the analysis.

---

## Development Quality Standards

To support these principles, the following standards apply:

### Code Review Requirements
- All PRs MUST demonstrate test coverage (red tests → passing tests)
- Reviewers MUST verify API contracts are unchanged or properly versioned
- Breaking changes MUST include migration documentation

### Data Integrity Standards
- Comment data MUST be immutable once stored (no overwrites; use versioning for corrections)
- All data modifications MUST be logged with timestamp, operator, and reason
- Exports MUST include metadata (export date, record count, schema version)
- All timestamps MUST be stored in UTC and converted to display timezone only at presentation layer

### Documentation Obligations
- Every API endpoint MUST have usage examples (curl/Python/etc.)
- Deployment procedures MUST include rollback instructions
- Schema changes MUST include migration scripts
- Timezone handling MUST be explicitly documented in API contracts and data models

---

## Governance

This Constitution is the supreme document governing DISINFO_SCANNER development. All PRs, feature branches, and design decisions MUST comply with these principles.

### Amendment Process
1. Propose amendment with rationale and impact analysis
2. Discuss with team; require consensus on principle changes
3. Update this document with new version and ratification date
4. Trigger review of dependent templates (spec, plan, tasks) within 24 hours
5. Create follow-up issues for any required refactoring

### Compliance Review
- Project leads MUST audit principle compliance during weekly sync meetings
- All PRs MUST be reviewed for constitutional alignment before merge
- Violations SHOULD be caught at code review stage; escalate if needed

### Runtime Development Guidance
For implementation details and workflow specifics, see `.specify/` directory structure:
- Feature specifications: `.specify/templates/spec-template.md`
- Implementation plans: `.specify/templates/plan-template.md`
- Task breakdowns: `.specify/templates/tasks-template.md`

---

**Version**: 1.1.0 | **Ratified**: 2025-11-15 | **Last Amended**: 2025-11-20
