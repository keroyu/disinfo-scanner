# Tasks: Member Registration System - Core Module

**Feature**: 011-member-system
**Module**: Core Authentication & Database
**Branch**: `011-member-system`
**Generated**: 2025-11-20

## Overview

This module contains core authentication functionality, database infrastructure, and foundational services that all other modules depend on.

**User Stories Covered**:
- User Story 1 (P1): New User Registration and Email Verification
- User Story 2 (P2): Mandatory Password Change on First Login

---

## Phase 1: Setup & Database Foundation

**Purpose**: Initialize project structure and create database schema

### Database Migrations

- [ ] T001 [P] Create migration to update users table in database/migrations/2025_11_20_000001_update_users_table_for_member_system.php
- [ ] T002 [P] Create roles table migration in database/migrations/2025_11_20_000002_create_roles_table.php
- [ ] T003 [P] Create permissions table migration in database/migrations/2025_11_20_000003_create_permissions_table.php
- [ ] T004 [P] Create role_user pivot table migration in database/migrations/2025_11_20_000004_create_role_user_table.php
- [ ] T005 [P] Create permission_role pivot table migration in database/migrations/2025_11_20_000005_create_permission_role_table.php
- [ ] T006 [P] Create email_verification_tokens table migration in database/migrations/2025_11_20_000006_create_email_verification_tokens_table.php
- [ ] T007 [P] Create api_quotas table migration in database/migrations/2025_11_20_000007_create_api_quotas_table.php
- [ ] T008 [P] Create identity_verifications table migration in database/migrations/2025_11_20_000008_create_identity_verifications_table.php

### Database Seeders

- [ ] T009 [P] Create RoleSeeder to seed 5 default roles in database/seeders/RoleSeeder.php
- [ ] T010 [P] Create PermissionSeeder to seed permissions in database/seeders/PermissionSeeder.php
- [ ] T011 [P] Create AdminUserSeeder to seed admin account (themustbig@gmail.com) in database/seeders/AdminUserSeeder.php

### Model Layer

- [ ] T012 [P] Update User model to add relationships and attributes in app/Models/User.php
- [ ] T013 [P] Create Role model with relationships in app/Models/Role.php
- [ ] T014 [P] Create Permission model with relationships in app/Models/Permission.php
- [ ] T015 [P] Create EmailVerificationToken model in app/Models/EmailVerificationToken.php
- [ ] T016 [P] Create ApiQuota model in app/Models/ApiQuota.php
- [ ] T017 [P] Create IdentityVerification model in app/Models/IdentityVerification.php

**Checkpoint**: Run migrations and seeders, verify database schema is correct

---

## Phase 2: Core Services Layer

**Purpose**: Business logic services that support authentication flows

### Service Classes

- [ ] T018 [P] Create PasswordService with validation and hashing logic in app/Services/PasswordService.php
- [ ] T019 [P] Create EmailVerificationService for token generation/validation in app/Services/EmailVerificationService.php
- [ ] T020 [P] Create RolePermissionService for role/permission checks in app/Services/RolePermissionService.php
- [ ] T021 [P] Create ApiQuotaService for quota tracking in app/Services/ApiQuotaService.php
- [ ] T022 Create AuthenticationService for authentication logic in app/Services/AuthenticationService.php

### Mail Classes

- [ ] T023 [P] Create VerificationEmail mailable in app/Mail/VerificationEmail.php
- [ ] T024 [P] Create PasswordResetEmail mailable in app/Mail/PasswordResetEmail.php

### Form Request Validation

- [ ] T025 [P] Create RegisterRequest validation in app/Http/Requests/RegisterRequest.php
- [ ] T026 [P] Create PasswordChangeRequest validation in app/Http/Requests/PasswordChangeRequest.php
- [ ] T027 [P] Create UserSettingsRequest validation in app/Http/Requests/UserSettingsRequest.php

**Checkpoint**: Services can be instantiated and basic methods work

---

## Phase 3: User Story 1 - Registration & Email Verification (P1) 🎯 MVP

**Goal**: Allow new users to register with email, receive verification email, verify account, and log in as Regular Member

**Independent Test**: Complete registration flow end-to-end - register with email, receive verification email, click link, log in successfully

### Contract Tests for US1 (Test-First Development)

- [ ] T028 [P] [US1] Create contract test for registration endpoint in tests/Contract/RegistrationContractTest.php
- [ ] T029 [P] [US1] Create contract test for email verification endpoint in tests/Contract/EmailVerificationContractTest.php
- [ ] T030 [P] [US1] Create contract test for login endpoint in tests/Contract/LoginContractTest.php

### Feature Tests for US1

- [ ] T031 [P] [US1] Create registration flow feature test in tests/Feature/Auth/RegistrationTest.php
- [ ] T032 [P] [US1] Create email verification feature test in tests/Feature/Auth/EmailVerificationTest.php
- [ ] T033 [P] [US1] Create login flow feature test in tests/Feature/Auth/LoginTest.php

### Unit Tests for US1

- [ ] T034 [P] [US1] Create email verification token expiration unit test in tests/Unit/EmailVerificationTokenTest.php
- [ ] T035 [P] [US1] Create email format validation unit test in tests/Unit/EmailValidationTest.php

### Controllers for US1

- [ ] T036 [US1] Create RegisterController with registration logic in app/Http/Controllers/Auth/RegisterController.php
- [ ] T037 [US1] Create EmailVerificationController for email verification in app/Http/Controllers/Auth/EmailVerificationController.php
- [ ] T038 [US1] Create LoginController for user login in app/Http/Controllers/Auth/LoginController.php

### Middleware for US1

- [ ] T039 [P] [US1] Create CheckEmailVerified middleware in app/Http/Middleware/CheckEmailVerified.php

### Routes for US1

- [ ] T040 [US1] Add registration, verification, and login routes in routes/web.php
- [ ] T041 [US1] Add registration, verification, and login API routes in routes/api.php

### Integration for US1

- [ ] T042 [US1] Test complete registration-to-login flow manually
- [ ] T043 [US1] Verify email sending works with configured SMTP
- [ ] T044 [US1] Verify rate limiting (3 verification emails per hour)

**Checkpoint**: User Story 1 complete - users can register, verify email, and log in

---

## Phase 4: User Story 2 - Mandatory Password Change (P2)

**Goal**: Force newly verified users to change default password (123456) to strong password before platform access

**Independent Test**: Log in with new account using default password, system forces password change, cannot access platform until strong password set

### Contract Tests for US2

- [ ] T045 [P] [US2] Create contract test for password change endpoint in tests/Contract/PasswordChangeContractTest.php
- [ ] T046 [P] [US2] Create contract test for password reset endpoint in tests/Contract/PasswordResetContractTest.php

### Feature Tests for US2

- [ ] T047 [P] [US2] Create mandatory password change feature test in tests/Feature/Auth/MandatoryPasswordChangeTest.php
- [ ] T048 [P] [US2] Create password reset flow feature test in tests/Feature/Auth/PasswordResetTest.php

### Unit Tests for US2

- [ ] T049 [P] [US2] Create password strength validation unit test in tests/Unit/PasswordValidationTest.php
- [ ] T050 [P] [US2] Create password reset token expiration unit test in tests/Unit/PasswordResetTokenTest.php

### Controllers for US2

- [ ] T051 [US2] Create PasswordChangeController for mandatory password change in app/Http/Controllers/Auth/PasswordChangeController.php
- [ ] T052 [US2] Create PasswordResetController for password reset flow in app/Http/Controllers/Auth/PasswordResetController.php

### Middleware for US2

- [ ] T053 [US2] Create CheckDefaultPassword middleware to enforce password change in app/Http/Middleware/CheckDefaultPassword.php

### Routes for US2

- [ ] T054 [US2] Add password change and reset routes in routes/web.php
- [ ] T055 [US2] Add password change and reset API routes in routes/api.php

### Integration for US2

- [ ] T056 [US2] Test mandatory password change flow end-to-end
- [ ] T057 [US2] Test password reset flow end-to-end
- [ ] T058 [US2] Verify password strength validation rejects weak passwords
- [ ] T059 [US2] Verify rate limiting (3 password reset emails per hour)

**Checkpoint**: User Story 2 complete - users must change default password before accessing platform

---

## Phase 5: Polish & Optimization

**Purpose**: Cross-cutting improvements for core module

- [ ] T060 [P] Add comprehensive logging for all authentication events in AuthenticationService
- [ ] T061 [P] Add error handling and validation messages in Traditional Chinese
- [ ] T062 Configure email queue worker for async email sending
- [ ] T063 [P] Create scheduled task for token cleanup (expired verification/reset tokens)
- [ ] T064 [P] Optimize database queries with proper indexes
- [ ] T065 Run all core module tests and ensure 100% pass rate
- [ ] T066 Update CLAUDE.md with core module completion status

---

## Dependencies & Execution Order

### Phase Dependencies

1. **Phase 1 (Setup)**: No dependencies - start immediately
2. **Phase 2 (Services)**: Depends on Phase 1 completion (models must exist)
3. **Phase 3 (US1)**: Depends on Phases 1 + 2 completion
4. **Phase 4 (US2)**: Depends on Phases 1 + 2 completion (can run parallel to US1 if staffed)
5. **Phase 5 (Polish)**: Depends on Phases 3 + 4 completion

### User Story Dependencies

- **US1 (Registration)**: Independent - can start after Phase 2
- **US2 (Password Change)**: Independent - can start after Phase 2 (parallel to US1)

### Parallel Opportunities

**Phase 1 - All migrations and models can run in parallel**:
```bash
# Launch all migration tasks together (T001-T008)
Task: T001, T002, T003, T004, T005, T006, T007, T008

# Launch all seeder tasks together (T009-T011)
Task: T009, T010, T011

# Launch all model tasks together (T012-T017)
Task: T012, T013, T014, T015, T016, T017
```

**Phase 2 - All services and mail classes can run in parallel**:
```bash
# Launch service tasks together (T018-T022)
Task: T018, T019, T020, T021

# Launch mail tasks together (T023-T024)
Task: T023, T024

# Launch form request tasks together (T025-T027)
Task: T025, T026, T027
```

**Phase 3 - All US1 tests can run in parallel**:
```bash
# Launch contract tests together (T028-T030)
Task: T028, T029, T030

# Launch feature tests together (T031-T033)
Task: T031, T032, T033

# Launch unit tests together (T034-T035)
Task: T034, T035

# Middleware (T039) can be created in parallel to controllers
Task: T039
```

**Phase 4 - All US2 tests can run in parallel**:
```bash
# Launch contract tests together (T045-T046)
Task: T045, T046

# Launch feature tests together (T047-T048)
Task: T047, T048

# Launch unit tests together (T049-T050)
Task: T049, T050
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup & Database
2. Complete Phase 2: Core Services
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Test registration → email verification → login flow
5. Deploy/demo if ready

### Incremental Delivery

1. Phase 1 + 2 → Foundation ready
2. Add User Story 1 → Test independently → Deploy (MVP!)
3. Add User Story 2 → Test independently → Deploy
4. Add Phase 5 improvements → Deploy

### Test-First Approach

- Write ALL tests FIRST (contract → feature → unit)
- Ensure tests FAIL before implementation
- Implement code to make tests pass
- Refactor while keeping tests green

---

## Success Metrics

- [ ] All migrations run without errors
- [ ] All seeders populate correct data
- [ ] Default admin account (themustbig@gmail.com / 2025Nov20) can log in
- [ ] New users can register and receive verification email within 2 minutes
- [ ] Email verification works and marks accounts as verified
- [ ] Unverified users cannot log in
- [ ] Verified users can log in successfully
- [ ] Default password (123456) triggers mandatory password change
- [ ] Password strength validation rejects weak passwords
- [ ] Password reset flow works end-to-end
- [ ] Rate limiting prevents abuse (3 emails per hour)
- [ ] All timestamps stored in UTC, displayed in GMT+8
- [ ] All tests pass (100% success rate)

---

**Total Tasks**: 66
**Parallel Tasks**: 37 (marked with [P])
**User Stories**: 2 (US1, US2)
**Estimated Completion**: Foundation for all other modules
