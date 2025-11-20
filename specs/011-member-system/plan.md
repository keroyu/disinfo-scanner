# Implementation Plan: Member Registration System

**Branch**: `011-member-system` | **Date**: 2025-11-20 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/011-member-system/spec.md`

**Note**: This template is filled in by the `/speckit.plan` command. See `.specify/templates/commands/plan.md` for the execution workflow.

## Summary

Implement a comprehensive member registration system with email verification, role-based access control (5 user types: Visitor, Regular Member, Paid Member, Website Editor, Administrator), password management with strength validation, and permission-based UI modals. The system includes pre-configured admin account, quota management for Paid Members, and identity verification support for unlimited API access.

## Technical Context

**Language/Version**: PHP 8.2
**Primary Dependencies**: Laravel Framework 12.38.1, Laravel Breeze (authentication scaffolding), Laravel Mail (email delivery)
**Storage**: MySQL/MariaDB (existing database with users, password_reset_tokens, sessions tables already present)
**Testing**: PHPUnit (Laravel's default testing framework)
**Target Platform**: Web server (Linux/macOS with PHP 8.2+ and MySQL)
**Project Type**: Web application (Laravel MVC with Blade templates)
**Performance Goals**: 95% of verification emails delivered within 2 minutes, password validation response <100ms, role-based access checks <50ms per request
**Constraints**: Email verification links expire after 24 hours, password reset tokens expire after 24 hours, rate limiting (3 requests/hour for verification/reset emails), all timestamps stored in UTC with GMT+8 display conversion
**Scale/Scope**: ~10-20 new UI views/components (registration, login, email verification, password change, settings, admin panel placeholder), 7 database tables (users, roles, permissions, verification_tokens, password_reset_tokens, api_quotas, identity_verifications), 56 functional requirements

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### I. Test-First Development ✅
- **Status**: PASS
- **Plan**: Will write PHPUnit tests before implementation for all authentication flows, email verification, password validation, role-based access control, and API quota enforcement
- **Test Structure**: Feature tests for user flows, unit tests for validation logic, contract tests for email service boundary

### II. API-First Design ✅
- **Status**: PASS
- **Plan**: Authentication and user management will be exposed through Laravel routes/controllers with JSON API responses. All endpoints will return structured JSON with actionable error messages. Web UI will consume same APIs.
- **Contracts**: Registration endpoint, login endpoint, verification endpoint, password reset endpoint, user management endpoints (admin), settings endpoints

### III. Observable Systems ✅
- **Status**: PASS
- **Plan**: FR-023 requires logging of all security-related events (registration, login, password changes, permission modifications). Will use Laravel's structured logging to JSON format with trace IDs for audit trails.
- **Logging Points**: User registration, email verification, login attempts, password changes, password resets, permission level changes, API quota usage

### IV. Contract Testing ✅
- **Status**: PASS
- **Plan**: Email verification tokens, password reset tokens, and role permission contracts will have dedicated contract tests. Tests will validate token structure, expiration behavior, and permission boundaries without touching implementation details.
- **Contracts to Test**: Email verification token format/expiration, password reset token format/expiration, role permission mappings, API quota structures

### V. Semantic Versioning ✅
- **Status**: PASS
- **Plan**: This is a new feature (no existing member system), starting at version 1.0.0. Future changes to authentication API, role permissions, or database schema will follow MAJOR.MINOR.PATCH versioning.
- **Initial Version**: 1.0.0

### VI. Timezone Consistency ✅
- **Status**: PASS
- **Plan**: All timestamps (account creation, email verification, password changes, login times, token expiration) will be stored in UTC in MySQL database. Laravel backend will convert to GMT+8 (Asia/Taipei) before rendering in Blade templates. All datetime displays will show explicit timezone indicator "(GMT+8)".
- **Implementation**: Laravel's built-in Carbon library for timezone handling, database columns use `timestamp` type storing UTC, Blade templates apply `->timezone('Asia/Taipei')` for display

## Project Structure

### Documentation (this feature)

```text
specs/[###-feature]/
├── plan.md              # This file (/speckit.plan command output)
├── research.md          # Phase 0 output (/speckit.plan command)
├── data-model.md        # Phase 1 output (/speckit.plan command)
├── quickstart.md        # Phase 1 output (/speckit.plan command)
├── contracts/           # Phase 1 output (/speckit.plan command)
└── tasks.md             # Phase 2 output (/speckit.tasks command - NOT created by /speckit.plan)
```

### Source Code (repository root)

**Structure Decision**: Laravel MVC web application structure. This is an existing Laravel 12 project, so the member system will integrate into the established Laravel directory structure.

#### New Files to Create:

```text
app/
├── Models/
│   ├── User.php                          # MODIFY: Add role, verification, API quota fields
│   ├── Role.php                          # NEW: Role model (Visitor, Regular, Paid, Editor, Admin)
│   ├── Permission.php                    # NEW: Permission model for fine-grained access control
│   ├── EmailVerificationToken.php       # NEW: Email verification token model
│   ├── ApiQuota.php                      # NEW: API import quota tracking for Paid Members
│   └── IdentityVerification.php         # NEW: Identity verification status for Paid Members
│
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   ├── RegisterController.php           # NEW: User registration
│   │   │   ├── LoginController.php              # NEW: User login
│   │   │   ├── EmailVerificationController.php  # NEW: Email verification handling
│   │   │   ├── PasswordResetController.php      # NEW: Password reset flow
│   │   │   └── PasswordChangeController.php     # NEW: Mandatory password change
│   │   ├── UserSettingsController.php           # NEW: User settings (password change, API key)
│   │   └── Admin/
│   │       └── UserManagementController.php     # NEW: Admin user/permission management
│   │
│   ├── Middleware/
│   │   ├── CheckUserRole.php                    # NEW: Role-based access middleware
│   │   ├── CheckEmailVerified.php               # NEW: Email verification check
│   │   ├── CheckDefaultPassword.php             # NEW: Force password change middleware
│   │   └── CheckApiQuota.php                    # NEW: API quota enforcement middleware
│   │
│   └── Requests/
│       ├── RegisterRequest.php                  # NEW: Registration validation
│       ├── PasswordChangeRequest.php            # NEW: Password change validation
│       └── UserSettingsRequest.php              # NEW: Settings update validation
│
├── Services/
│   ├── AuthenticationService.php                # NEW: Authentication business logic
│   ├── EmailVerificationService.php             # NEW: Email verification logic
│   ├── PasswordService.php                      # NEW: Password validation/hashing
│   ├── RolePermissionService.php                # NEW: Role/permission checking
│   └── ApiQuotaService.php                      # NEW: API quota management
│
├── Mail/
│   ├── VerificationEmail.php                    # NEW: Email verification mailable
│   └── PasswordResetEmail.php                   # NEW: Password reset mailable
│
└── Policies/
    └── UserPolicy.php                            # NEW: Authorization policies for user actions

database/
├── migrations/
│   ├── 2025_11_20_000001_update_users_table_for_member_system.php      # NEW: Modify users table
│   ├── 2025_11_20_000002_create_roles_table.php                        # NEW: Roles table
│   ├── 2025_11_20_000003_create_permissions_table.php                  # NEW: Permissions table
│   ├── 2025_11_20_000004_create_role_user_table.php                    # NEW: Role-user pivot
│   ├── 2025_11_20_000005_create_email_verification_tokens_table.php    # NEW: Verification tokens
│   ├── 2025_11_20_000006_create_api_quotas_table.php                   # NEW: API quotas
│   ├── 2025_11_20_000007_create_identity_verifications_table.php       # NEW: Identity verifications
│   └── 2025_11_20_000008_seed_default_admin_account.php                # NEW: Pre-configured admin
│
├── seeders/
│   ├── RoleSeeder.php                           # NEW: Seed default roles
│   ├── PermissionSeeder.php                     # NEW: Seed default permissions
│   └── AdminUserSeeder.php                      # NEW: Seed admin account
│
└── factories/
    ├── UserFactory.php                          # MODIFY: Add role/verification support
    └── EmailVerificationTokenFactory.php        # NEW: Token factory for testing

resources/
├── views/
│   ├── auth/
│   │   ├── register.blade.php                   # NEW: Registration form
│   │   ├── login.blade.php                      # NEW: Login form
│   │   ├── verify-email.blade.php               # NEW: Email verification page
│   │   ├── password-reset-request.blade.php     # NEW: Password reset request
│   │   ├── password-reset.blade.php             # NEW: Password reset form
│   │   └── mandatory-password-change.blade.php  # NEW: Forced password change
│   │
│   ├── settings/
│   │   └── index.blade.php                      # NEW: User settings page
│   │
│   ├── admin/
│   │   ├── users/
│   │   │   ├── index.blade.php                  # NEW: User list (admin)
│   │   │   └── edit.blade.php                   # NEW: Edit user permissions
│   │   └── dashboard.blade.php                  # NEW: Admin dashboard placeholder
│   │
│   ├── components/
│   │   ├── permission-modal.blade.php           # NEW: Permission-denied modal component
│   │   └── upgrade-button.blade.php             # NEW: Upgrade to Paid Member button
│   │
│   └── emails/
│       ├── verify-email.blade.php               # NEW: Verification email template
│       └── reset-password.blade.php             # NEW: Password reset email template
│
└── lang/
    └── zh_TW/
        ├── auth.php                             # MODIFY: Add Traditional Chinese auth messages
        └── validation.php                       # MODIFY: Add password strength messages

routes/
├── web.php                                      # MODIFY: Add auth/user routes
└── api.php                                      # MODIFY: Add auth API routes (optional)

tests/
├── Feature/
│   ├── Auth/
│   │   ├── RegistrationTest.php                 # NEW: Registration flow tests
│   │   ├── EmailVerificationTest.php            # NEW: Email verification tests
│   │   ├── LoginTest.php                        # NEW: Login flow tests
│   │   ├── PasswordResetTest.php                # NEW: Password reset tests
│   │   └── MandatoryPasswordChangeTest.php      # NEW: Forced password change tests
│   │
│   ├── UserSettingsTest.php                     # NEW: Settings page tests
│   ├── RoleBasedAccessTest.php                  # NEW: Permission modal tests
│   └── Admin/
│       └── UserManagementTest.php               # NEW: Admin user management tests
│
├── Unit/
│   ├── PasswordValidationTest.php               # NEW: Password strength tests
│   ├── RolePermissionTest.php                   # NEW: Role/permission logic tests
│   ├── EmailVerificationTokenTest.php           # NEW: Token expiration tests
│   └── ApiQuotaTest.php                         # NEW: API quota logic tests
│
└── Contract/
    ├── EmailServiceContractTest.php             # NEW: Email service boundary tests
    └── RolePermissionContractTest.php           # NEW: Permission contract tests

config/
└── mail.php                                     # MODIFY: Email service configuration
```

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

No constitutional violations. All principles pass.

---

## Implementation Summary

### Files Overview

**Total Files**: 73 files (3 modified, 70 new)

**Modified Files** (3):
1. `app/Models/User.php` - Add role, verification, API quota fields
2. `database/factories/UserFactory.php` - Add role/verification support
3. `config/mail.php` - Email service configuration

**New Files by Category**:

**Models** (5 new):
- `app/Models/Role.php`
- `app/Models/Permission.php`
- `app/Models/EmailVerificationToken.php`
- `app/Models/ApiQuota.php`
- `app/Models/IdentityVerification.php`

**Controllers** (7 new):
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Auth/EmailVerificationController.php`
- `app/Http/Controllers/Auth/PasswordResetController.php`
- `app/Http/Controllers/Auth/PasswordChangeController.php`
- `app/Http/Controllers/UserSettingsController.php`
- `app/Http/Controllers/Admin/UserManagementController.php`

**Middleware** (4 new):
- `app/Http/Middleware/CheckUserRole.php`
- `app/Http/Middleware/CheckEmailVerified.php`
- `app/Http/Middleware/CheckDefaultPassword.php`
- `app/Http/Middleware/CheckApiQuota.php`

**Services** (5 new):
- `app/Services/AuthenticationService.php`
- `app/Services/EmailVerificationService.php`
- `app/Services/PasswordService.php`
- `app/Services/RolePermissionService.php`
- `app/Services/ApiQuotaService.php`

**Form Requests** (3 new):
- `app/Http/Requests/RegisterRequest.php`
- `app/Http/Requests/PasswordChangeRequest.php`
- `app/Http/Requests/UserSettingsRequest.php`

**Mail/Policies** (3 new):
- `app/Mail/VerificationEmail.php`
- `app/Mail/PasswordResetEmail.php`
- `app/Policies/UserPolicy.php`

**Database Migrations** (9 new):
- `2025_11_20_000001_update_users_table_for_member_system.php`
- `2025_11_20_000002_create_roles_table.php`
- `2025_11_20_000003_create_permissions_table.php`
- `2025_11_20_000004_create_role_user_table.php`
- `2025_11_20_000005_create_permission_role_table.php` (corrected from wrong name in earlier doc)
- `2025_11_20_000006_create_email_verification_tokens_table.php`
- `2025_11_20_000007_create_api_quotas_table.php`
- `2025_11_20_000008_create_identity_verifications_table.php`
- `2025_11_20_000009_seed_default_admin_account.php`

**Seeders** (3 new):
- `database/seeders/RoleSeeder.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/AdminUserSeeder.php`

**Factories** (1 new):
- `database/factories/EmailVerificationTokenFactory.php`

**Views** (16 new):
- 6 auth views (register, login, verify-email, password-reset-request, password-reset, mandatory-password-change)
- 1 settings view
- 3 admin views (user list, edit user, dashboard placeholder)
- 2 components (permission-modal, upgrade-button)
- 2 email templates (verify-email, reset-password)
- 2 language files (modify auth.php and validation.php for Traditional Chinese)

**Tests** (13 new):
- 5 Feature tests (Auth: Registration, EmailVerification, Login, PasswordReset, MandatoryPasswordChange)
- 3 Feature tests (UserSettings, RoleBasedAccess, Admin/UserManagement)
- 4 Unit tests (PasswordValidation, RolePermission, EmailVerificationToken, ApiQuota)
- 2 Contract tests (EmailServiceContract, RolePermissionContract)

**Routes** (2 modified):
- `routes/web.php` - Add auth/user routes
- `routes/api.php` - Add auth API routes

---

## Phase 0 Deliverables ✅

- [x] `research.md` - Technology decisions and rationale
  - Email service: Laravel Mail with SMTP
  - Password hashing: bcrypt (Laravel default)
  - RBAC: Custom implementation (no Spatie package)
  - Token storage: Separate verification/reset tables
  - API quota: Dedicated table with monthly reset
  - Timezone: UTC storage, GMT+8 display
  - Modal UI: Blade + Alpine.js
  - Rate limiting: Laravel RateLimiter

---

## Phase 1 Deliverables ✅

- [x] `data-model.md` - Complete database schema with 9 tables
  - Modified: `users` table (added 4 columns)
  - New: `roles`, `permissions`, `role_user`, `permission_role`, `email_verification_tokens`, `api_quotas`, `identity_verifications`
  - Includes ER diagram, validation rules, state transitions, indexes

- [x] `contracts/authentication-api.md` - Authentication API contracts
  - 8 endpoints: Register, Verify Email, Resend Verification, Login, Change Password, Reset Request, Reset Confirm, Logout
  - JSON request/response formats
  - Rate limiting specs
  - Security considerations

- [x] `contracts/user-management-api.md` - User management API contracts
  - 9 endpoints: List Users, Get User Details, Update Role, Get/Update Settings, Check Quota, Submit/Review Identity Verification, Check Permissions
  - Admin-only vs authenticated user endpoints
  - Authorization rules

- [x] `quickstart.md` - Developer setup guide
  - Prerequisites and environment setup
  - Migration and seeding steps
  - Testing instructions
  - API usage examples
  - Troubleshooting guide
  - Production deployment checklist

- [x] Agent context updated
  - Updated `CLAUDE.md` with PHP 8.2, Laravel 12.38.1, MySQL/MariaDB

---

## Next Steps

### Phase 2: Task Breakdown (Run `/speckit.tasks`)

The next command will generate `tasks.md` with:
- Dependency-ordered task list
- Test requirements for each task
- Acceptance criteria per task
- Estimated implementation order

### Implementation Order (Recommended)

1. **Database Layer** (Migrations, Models, Seeders)
2. **Service Layer** (Authentication, Email, Password, Role, Quota services)
3. **Controller Layer** (Auth controllers, User settings, Admin management)
4. **Middleware Layer** (Role check, verification check, quota check)
5. **View Layer** (Blade templates, components, email templates)
6. **Test Layer** (Feature tests, Unit tests, Contract tests)
7. **Routes Configuration** (Web routes, API routes)
8. **Language Files** (Traditional Chinese translations)

---

## Documentation Location

All planning artifacts are in:
```
/Users/yueyu/Dev/DISINFO_SCANNER/specs/011-member-system/
├── plan.md              ✅ This file
├── research.md          ✅ Phase 0 output
├── data-model.md        ✅ Phase 1 output
├── quickstart.md        ✅ Phase 1 output
├── contracts/           ✅ Phase 1 output
│   ├── authentication-api.md
│   └── user-management-api.md
└── tasks.md             ⏳ Phase 2 (next step - run /speckit.tasks)
```

---

**Planning Status**: COMPLETE
**Ready for**: Task breakdown (`/speckit.tasks`) and implementation
**Branch**: `011-member-system`
**Last Updated**: 2025-11-20
