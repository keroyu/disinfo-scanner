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
- 011-member-system: **CORE MODULE COMPLETE** (2025-11-21)
  - Implemented complete authentication system with email verification
  - Password management with mandatory change and reset functionality
  - Role-Based Access Control (RBAC) foundation
  - API quota tracking for paid members
  - Identity verification workflow
  - Comprehensive logging for all security events
  - Traditional Chinese (zh_TW) localization support
  - Scheduled tasks for token cleanup and quota reset
  - **Status**: 66/66 tasks complete, ready for UI/Admin module implementation
- 010-time-based-comment-filter: Added PHP 8.2 with Laravel Framework 12.0 + Laravel Framework, Chart.js 4.4.0 (existing), Tailwind CSS (via CDN)
- 008-video-comment-density: Added PHP 8.2 with Laravel Framework 12.0 + Chart.js (client-side visualization), Tailwind CSS (existing UI framework), Carbon (datetime handling)


<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
