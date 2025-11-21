# DISINFO_SCANNER Development Guidelines

Auto-generated from all feature plans. Last updated: 2025-11-18

## Active Technologies
- PHP 8.2 with Laravel Framework 12.0 (008-video-comment-analysis)
- MySQL/MariaDB (existing database with videos and comments tables) (008-video-comment-analysis)
- PHP 8.2 + Chart.js (for client-side visualization), Tailwind CSS (existing UI framework) (008-video-comment-density)
- PHP 8.2 with Laravel Framework 12.0 + Chart.js (client-side visualization), Tailwind CSS (existing UI framework), Carbon (datetime handling) (008-video-comment-density)
- PHP 8.2 with Laravel Framework 12.0 + Laravel Framework, Chart.js 4.4.0 (existing), Tailwind CSS (via CDN) (010-time-based-comment-filter)
- MySQL/MariaDB with existing comments table (published_at in UTC) (010-time-based-comment-filter)
- PHP 8.2 + Laravel Framework 12.38.1, Laravel Breeze (authentication scaffolding), Laravel Mail (email delivery) (011-member-system)
- MySQL/MariaDB (existing database with users, password_reset_tokens, sessions tables already present) (011-member-system)

- PHP 8.2 with Laravel Framework 12.38.1 (007-video-incremental-update)

## Project Structure

```text
src/
tests/
```

## Commands

# Add commands for PHP 8.2 with Laravel Framework 12.38.1

## Code Style

PHP 8.2 with Laravel Framework 12.38.1: Follow standard conventions

## Recent Changes
- 011-member-system: **✅ COMPLETE & PRODUCTION-TESTED** (2025-11-21)
  - ✅ **Core Module**: Complete authentication backend (66/66 tasks)
    - Database schema with 9 migrations
    - All models, services, controllers, and middleware
    - Email verification and password management
    - RBAC foundation with 5 user roles
    - API quota tracking and identity verification
    - Traditional Chinese (zh_TW) localization
    - All tests passing (43/43 core tests)
  - ✅ **UI Module**: Complete user interface (77/77 tasks) - **TESTED**
    - **Phase 1-2**: Registration, login, email verification, password management views
    - **Phase 3**: User settings page with password change and API key configuration
    - **Phase 4**: Permission modals with Alpine.js for RBAC
    - **Phase 5**: Navigation integration with user dropdown, role badges, quota display
    - **Phase 6**: Accessibility and UX polish
    - **Phase 7**: Manual testing complete, all flows verified
    - All views with Traditional Chinese localization
    - Responsive design with Tailwind CSS
  - **🐛 Bugs Fixed During Testing**:
    - Fixed missing POST routes for authentication forms
    - Fixed missing settings routes (password, api-key, remove)
    - Removed incorrect HTTP method overrides (@method PUT/DELETE)
    - Fixed logout to redirect instead of returning JSON
  - **Status**: ✅ Full member registration system **TESTED & PRODUCTION-READY**
- 010-time-based-comment-filter: Added PHP 8.2 with Laravel Framework 12.0 + Laravel Framework, Chart.js 4.4.0 (existing), Tailwind CSS (via CDN)
- 008-video-comment-density: Added PHP 8.2 with Laravel Framework 12.0 + Chart.js (client-side visualization), Tailwind CSS (existing UI framework), Carbon (datetime handling)


<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
