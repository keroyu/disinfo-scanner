# Tasks: Member Registration System - RBAC Module

**Feature**: 011-member-system
**Module**: Role-Based Access Control & Permissions
**Branch**: `011-member-system`
**Generated**: 2025-11-20

## Overview

This module implements role-based access control (RBAC) for 5 user types: Visitor, Regular Member, Paid Member, Website Editor, and Administrator. It enforces permissions across pages, features, and actions.

**Dependencies**: Requires Core Module (tasks-core.md) and UI Module (tasks-ui.md) to be complete

**User Stories Covered**:
- User Story 4 (P3): Role-Based Access Control

---

## Phase 1: Permission System Foundation

**Purpose**: Create permission checking infrastructure

**Goal**: System can check user permissions for any action

### Permission Data Setup

- [ ] T401 Verify RoleSeeder creates all 5 roles - already in Core Module T009
- [ ] T402 Verify PermissionSeeder creates all permissions - already in Core Module T010
- [ ] T403 Create permission-role mapping seeder in database/seeders/PermissionRoleMappingSeeder.php
- [ ] T404 Map permissions to Visitor role (view Home, Videos List, video analysis)
- [ ] T405 Map permissions to Regular Member role (+ Channels List, Comments List, U-API import, video update with API key)
- [ ] T406 Map permissions to Paid Member role (+ Official API import, search features)
- [ ] T407 Map permissions to Website Editor role (all frontend features)
- [ ] T408 Map permissions to Administrator role (all features)

### Permission Checking Middleware

- [ ] T409 [P] Create CheckUserRole middleware - already in Core Module (verify functionality)
- [ ] T410 [P] [US4] Add permission checking methods to RolePermissionService
- [ ] T411 [P] [US4] Create CheckPermission middleware in app/Http/Middleware/CheckPermission.php

### Gate & Policy Setup

- [ ] T412 [US4] Define permission gates in app/Providers/AuthServiceProvider.php
- [ ] T413 [US4] Create gates for page access (view_channels_list, view_comments_list, view_admin_panel)
- [ ] T414 [US4] Create gates for feature access (use_search_videos, use_official_api_import, use_video_update)
- [ ] T415 [US4] Create gates for actions (manage_users, change_password)

**Checkpoint**: Permission system foundation ready

---

## Phase 2: API Quota Management (User Story 4)

**Purpose**: Enforce API quota limits for Paid Members

**Goal**: Paid Members have 10 imports/month, unlimited after identity verification

### Contract Tests for Quota

- [ ] T416 [P] [US4] Create contract test for check quota endpoint in tests/Contract/ApiQuotaContractTest.php
- [ ] T417 [P] [US4] Create contract test for quota enforcement in tests/Contract/QuotaEnforcementTest.php

### Feature Tests for Quota

- [ ] T418 [P] [US4] Create API quota feature test in tests/Feature/ApiQuotaTest.php

### Unit Tests for Quota

- [ ] T419 [P] [US4] Create quota calculation unit test in tests/Unit/ApiQuotaCalculationTest.php
- [ ] T420 [P] [US4] Create quota reset unit test in tests/Unit/ApiQuotaResetTest.php

### Quota Service Methods (already in Core Module T021, verify functionality)

- [ ] T421 [US4] Verify ApiQuotaService::checkQuota method works correctly
- [ ] T422 [US4] Verify ApiQuotaService::incrementUsage method works correctly
- [ ] T423 [US4] Verify ApiQuotaService::resetMonthlyQuota scheduled task works

### Quota Middleware

- [ ] T424 [US4] Create CheckApiQuota middleware - already in Core Module (verify functionality)
- [ ] T425 [US4] Apply quota middleware to Official API import routes
- [ ] T426 [US4] Ensure quota check only applies to Paid Members (not verified Paid Members)

### Quota Error Handling

- [ ] T427 [US4] When quota exceeded, return error with current usage (10/10)
- [ ] T428 [US4] Include suggestion to complete identity verification in error message
- [ ] T429 [US4] Add Traditional Chinese quota exceeded messages

### Integration Testing

- [ ] T430 [US4] Test Paid Member can import 10 videos per month
- [ ] T431 [US4] Test 11th import attempt shows quota exceeded error
- [ ] T432 [US4] Test verified Paid Member has unlimited quota
- [ ] T433 [US4] Test quota resets on 1st of month
- [ ] T434 [US4] Test Regular Member cannot access Official API import

**Checkpoint**: API quota management complete

---

## Phase 3: Page Access Control (User Story 4)

**Purpose**: Enforce page-level permissions for different roles

**Goal**: Users can only access pages allowed by their role

### Contract Tests for Page Access

- [ ] T435 [P] [US4] Create contract test for page permission checks in tests/Contract/PageAccessContractTest.php

### Feature Tests for Page Access

- [ ] T436 [P] [US4] Create page access feature test in tests/Feature/PageAccessTest.php

### Route Protection

- [ ] T437 [US4] Apply auth middleware to Channels List route
- [ ] T438 [US4] Apply auth middleware to Comments List route
- [ ] T439 [US4] Apply admin middleware to admin panel routes
- [ ] T440 [US4] Keep Home and Videos List accessible to visitors

### Access Denied Handling

- [ ] T441 [US4] When visitor accesses protected page, redirect to login
- [ ] T442 [US4] When authenticated user lacks permission, show 403 error page
- [ ] T443 [US4] Add Traditional Chinese 403 error page in resources/views/errors/403.blade.php

### Integration Testing

- [ ] T444 [US4] Test visitor can access Home and Videos List
- [ ] T445 [US4] Test visitor redirected to login when accessing Channels List
- [ ] T446 [US4] Test Regular Member can access Channels List and Comments List
- [ ] T447 [US4] Test Regular Member cannot access admin panel
- [ ] T448 [US4] Test Administrator can access all pages

**Checkpoint**: Page access control complete

---

## Phase 4: Feature Access Control (User Story 4)

**Purpose**: Enforce feature-level permissions (search, import, update)

**Goal**: Users can only use features allowed by their role

### Contract Tests for Feature Access

- [ ] T449 [P] [US4] Create contract test for feature permission checks in tests/Contract/FeatureAccessContractTest.php

### Feature Tests for Feature Access

- [ ] T450 [P] [US4] Create feature access test in tests/Feature/FeatureAccessTest.php

### Search Feature Protection

- [ ] T451 [US4] Prevent visitors from using Videos List search
- [ ] T452 [US4] Prevent Regular Members from using Comments List search
- [ ] T453 [US4] Allow Paid Members to use all search features

### Import Feature Protection

- [ ] T454 [US4] Allow Regular Members to use U-API import
- [ ] T455 [US4] Prevent Regular Members from using Official API import
- [ ] T456 [US4] Allow Paid Members to use Official API import (with quota)
- [ ] T457 [US4] Allow verified Paid Members unlimited Official API import

### Video Update Protection

- [ ] T458 [US4] Allow visitors to use video analysis feature
- [ ] T459 [US4] Prevent visitors from using video update feature
- [ ] T460 [US4] Allow Regular Members to use video update ONLY if YouTube API key configured
- [ ] T461 [US4] Show prompt to configure API key if not set

### Integration Testing

- [ ] T462 [US4] Test visitor can use video analysis but not video update
- [ ] T463 [US4] Test visitor cannot use Videos List search
- [ ] T464 [US4] Test Regular Member can use U-API import but not Official API import
- [ ] T465 [US4] Test Regular Member can use video update after setting API key
- [ ] T466 [US4] Test Paid Member can use Official API import with quota check
- [ ] T467 [US4] Test Website Editor has access to all frontend features

**Checkpoint**: Feature access control complete

---

## Phase 5: Permission Modals & UI Feedback (User Story 4)

**Purpose**: Show helpful modals when users attempt restricted actions

**Goal**: Users understand why they cannot access features and how to upgrade

**Note**: Modal components already created in UI Module (tasks-ui.md T129-T133), this phase integrates them

### Modal Integration Points

- [ ] T468 [US4] Integrate permission modal on Comments List link for visitors
- [ ] T469 [US4] Integrate permission modal on Official API import button for Regular Members
- [ ] T470 [US4] Integrate permission modal on Videos List search for visitors
- [ ] T471 [US4] Integrate permission modal on Comments List search for Regular Members
- [ ] T472 [US4] Integrate permission modal on admin panel access for non-admins

### Modal Messages

- [ ] T473 [US4] Set modal message "請登入會員" for visitor access attempts
- [ ] T474 [US4] Set modal message "需升級為高級會員" for Regular Member paid feature attempts
- [ ] T475 [US4] Set modal message "需設定 YouTube API 金鑰" for video update without API key
- [ ] T476 [US4] Set modal message with quota info when quota exceeded

### Upgrade Button Display

- [ ] T477 [US4] Display "Upgrade to Paid Member" button for Regular Members - already in UI Module T134
- [ ] T478 [US4] Link upgrade button to membership information page
- [ ] T479 [US4] Hide upgrade button for Paid Members and above

### Quota Counter Display

- [ ] T480 [US4] Display quota counter for Paid Members (X/10 this month) - already in UI Module T135
- [ ] T481 [US4] Update quota counter after each import
- [ ] T482 [US4] Show "Unlimited" for verified Paid Members

### Integration Testing

- [ ] T483 [US4] Test visitor sees "請登入會員" modal when clicking Comments List
- [ ] T484 [US4] Test Regular Member sees "需升級為高級會員" modal on Official API import
- [ ] T485 [US4] Test Paid Member sees quota counter (7/10 remaining)
- [ ] T486 [US4] Test quota exceeded modal shows correct usage (10/10)
- [ ] T487 [US4] Test verified Paid Member sees "Unlimited" instead of quota

**Checkpoint**: Permission modals and UI feedback complete

---

## Phase 6: Role-Specific Settings Access

**Purpose**: Ensure settings page shows role-appropriate options

**Goal**: Each role sees relevant settings for their permission level

### Settings Page Customization

- [ ] T488 [US4] All authenticated users see password change section
- [ ] T489 [US4] Regular Members and above see YouTube API key configuration
- [ ] T490 [US4] Paid Members see identity verification submission section
- [ ] T491 [US4] Hide identity verification section for verified Paid Members
- [ ] T492 [US4] Website Editors and Admins see all settings

### YouTube API Key Configuration

- [ ] T493 [US4] Add YouTube API key validation (format check)
- [ ] T494 [US4] Save YouTube API key to user record
- [ ] T495 [US4] Enable video update feature after API key saved
- [ ] T496 [US4] Show API key status indicator (configured/not configured)

### Identity Verification Submission

- [ ] T497 [US4] Add identity verification submission form to settings
- [ ] T498 [US4] Validate verification method field
- [ ] T499 [US4] Create identity verification record on submission
- [ ] T500 [US4] Show verification status (pending/approved/rejected)

### Integration Testing

- [ ] T501 [US4] Test all authenticated users can change password
- [ ] T502 [US4] Test Regular Member can configure YouTube API key
- [ ] T503 [US4] Test video update enabled after API key configured
- [ ] T504 [US4] Test Paid Member can submit identity verification
- [ ] T505 [US4] Test verification status displays correctly in settings

**Checkpoint**: Role-specific settings complete

---

## Phase 7: RBAC Testing & Validation

**Purpose**: Comprehensive testing of all permission scenarios

**Goal**: 100% coverage of all role-permission combinations

### Role Permission Matrix Testing

- [ ] T506 [P] Test all Visitor permissions (4 scenarios)
- [ ] T507 [P] Test all Regular Member permissions (10 scenarios)
- [ ] T508 [P] Test all Paid Member permissions (12 scenarios)
- [ ] T509 [P] Test all Website Editor permissions (15 scenarios)
- [ ] T510 [P] Test all Administrator permissions (all features)

### Edge Case Testing

- [ ] T511 Test session expiration during permission-protected action
- [ ] T512 Test role change takes effect without re-login
- [ ] T513 Test simultaneous role changes by multiple admins
- [ ] T514 Test quota limit reached mid-import operation
- [ ] T515 Test visitor directly accessing restricted URL

### Permission Boundary Testing

- [ ] T516 [P] Test Regular Member cannot elevate to Paid Member permissions
- [ ] T517 [P] Test Paid Member cannot access admin functions
- [ ] T518 [P] Test Website Editor cannot access admin panel
- [ ] T519 Test permission denied for deleted/deactivated roles

### Performance Testing

- [ ] T520 Test permission check latency (<50ms per request)
- [ ] T521 Test quota check performance with 1000+ users
- [ ] T522 Test role caching improves permission check speed

**Checkpoint**: All RBAC scenarios tested and validated

---

## Phase 8: RBAC Documentation & Maintenance

**Purpose**: Document permission system for developers and admins

### Developer Documentation

- [ ] T523 [P] Document permission system architecture in docs/rbac-architecture.md
- [ ] T524 [P] Document how to add new permissions in docs/add-permissions.md
- [ ] T525 [P] Document how to assign permissions to roles in docs/assign-permissions.md
- [ ] T526 Create permission matrix reference table

### Admin Documentation

- [ ] T527 [P] Document role capabilities in docs/role-capabilities.md
- [ ] T528 [P] Document how to change user roles (already in Admin Module)
- [ ] T529 Add role comparison chart for admins

### Maintenance Tools

- [ ] T530 [P] Create command to list all permissions: php artisan permissions:list
- [ ] T531 [P] Create command to check user permissions: php artisan permissions:check {user_id}
- [ ] T532 [P] Create command to sync permissions: php artisan permissions:sync

### Final Validation

- [ ] T533 Run all RBAC tests and ensure 100% pass rate
- [ ] T534 Perform security audit of permission system
- [ ] T535 Verify all permissions documented
- [ ] T536 Test permission system with quickstart.md validation

**Checkpoint**: RBAC module complete and documented

---

## Dependencies & Execution Order

### Module Dependencies

- **This module requires Core Module completion** (tasks-core.md)
- **This module requires UI Module completion** (tasks-ui.md) for modal integration
- Requires Role, Permission, and ApiQuota models from Core
- Requires permission modal components from UI

### Phase Dependencies

1. **Phase 1 (Foundation)**: Requires Core Module Phase 1 (Database)
2. **Phase 2 (Quota)**: Depends on Phase 1 + Core Module ApiQuotaService
3. **Phase 3 (Page Access)**: Depends on Phase 1 completion
4. **Phase 4 (Feature Access)**: Depends on Phase 1 completion (can run parallel to Phase 3)
5. **Phase 5 (Modals)**: Depends on UI Module Phase 4 + Phases 3-4 of this module
6. **Phase 6 (Settings)**: Depends on Phase 1 completion
7. **Phase 7 (Testing)**: Depends on all phases 1-6 complete
8. **Phase 8 (Documentation)**: Depends on all phases complete

### Parallel Opportunities

**Phase 1 - Permission mapping can run in parallel**:
```bash
# Launch permission mapping tasks (T404-T408)
Task: T404, T405, T406, T407, T408
```

**Phase 2 - All quota tests can run in parallel**:
```bash
# Launch quota tests (T416-T420)
Task: T416, T417, T418, T419, T420
```

**Phase 3 - Page access tests can run in parallel**:
```bash
# Launch page access tests (T435-T436)
Task: T435, T436

# Launch route protection tasks (T437-T440)
Task: T437, T438, T439, T440
```

**Phase 4 - Feature access tests can run in parallel**:
```bash
# Launch feature tests (T449-T450)
Task: T449, T450
```

**Phase 7 - Role testing can run in parallel**:
```bash
# Launch role permission tests (T506-T510)
Task: T506, T507, T508, T509, T510

# Launch boundary tests (T516-T518)
Task: T516, T517, T518
```

**Phase 8 - Documentation can run in parallel**:
```bash
# Launch developer docs (T523-T525)
Task: T523, T524, T525

# Launch admin docs (T527-T529)
Task: T527, T529

# Launch maintenance tools (T530-T532)
Task: T530, T531, T532
```

---

## Implementation Strategy

### Sequential by Priority

1. Complete Phase 1 (Foundation) → Permission system ready
2. Complete Phase 2 (Quota) → API quota enforcement works
3. Complete Phase 3 (Page Access) → Page-level permissions work
4. Complete Phase 4 (Feature Access) → Feature-level permissions work
5. Complete Phase 5 (Modals) → User feedback works
6. Complete Phase 6 (Settings) → Role-specific settings work
7. Complete Phase 7 (Testing) → All scenarios validated
8. Complete Phase 8 (Documentation) → System documented

### MVP Scope (User Story 4)

- Phases 1-5 = Core RBAC functionality
- Phases 6-8 = Enhancements and documentation

---

## Success Metrics

### Permission Enforcement

- [ ] All 5 roles have correct permissions assigned
- [ ] Visitor permissions enforced (Home, Videos List only)
- [ ] Regular Member permissions enforced (+ Channels List, Comments List, U-API, video update with API key)
- [ ] Paid Member permissions enforced (+ Official API import with 10/month quota, search features)
- [ ] Website Editor permissions enforced (all frontend features)
- [ ] Administrator permissions enforced (all features)

### API Quota Management

- [ ] Paid Members can import 10 videos per month
- [ ] 11th import shows quota exceeded error with usage (10/10)
- [ ] Quota resets on 1st of month
- [ ] Identity verification grants unlimited quota
- [ ] Quota counter displays correctly in UI

### UI Feedback

- [ ] Visitors see "請登入會員" modal for member-only features
- [ ] Regular Members see "需升級為高級會員" modal for paid features
- [ ] Quota exceeded modal shows correct usage and verification suggestion
- [ ] Upgrade button displays for Regular Members only
- [ ] Quota counter displays for Paid Members

### Settings Access

- [ ] All authenticated users can change password
- [ ] Regular Members can configure YouTube API key
- [ ] Video update enabled after API key configured
- [ ] Paid Members can submit identity verification
- [ ] Verification status displays correctly

### Performance

- [ ] Permission check latency <50ms per request
- [ ] Quota check performs efficiently with 1000+ users
- [ ] Role caching improves performance

### Testing Coverage

- [ ] 100% of permission scenarios tested
- [ ] All edge cases handled correctly
- [ ] All role-permission combinations validated
- [ ] Security audit passed

---

**Total Tasks**: 136 (T401-T536)
**Parallel Tasks**: 32 (marked with [P])
**User Stories**: 1 (US4)
**Dependencies**: Core Module + UI Module must be complete
**Estimated Completion**: Full RBAC system with all 5 roles
