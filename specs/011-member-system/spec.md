# Feature Specification: Member Registration System

**Feature Branch**: `011-member-system`
**Created**: 2025-11-20
**Status**: Draft
**Input**: User description: "新增：會員註冊系統

管理員是最高權限，可以使用平台前台、後台任何功能。
預設帳號：themustbig@gmail.com / 密碼：2025Nov20

會員需要使用 email 註冊，需接收驗證信。驗證完成即可登入。
預設密碼是123456，系統會提示請立刻更改密碼(強度要求:標準)

系統權限分為 管理員 / 網站編輯 / 高級會員 / 一般會員 4個級別"

## Clarifications

### Session 2025-11-20

- Q: Can Visitors (unregistered users) access Channels List page? → A: No, Visitors can only access Home page and Videos List page
- Q: What should happen when a verification email link expires? → A: Allow users to request a new verification email if the link expires or they never receive it, with rate limiting to prevent abuse. Before completing verification, users cannot log in.
- Q: Should password reset functionality be included in this member system feature? → A: Yes, provide email-based password reset with secure token (time-limited link sent to registered email)
- Q: What should happen when a Premium Member reaches their monthly quota limit? → A: Display error message explaining quota exceeded, show current usage (10/10), and suggest identity verification for unlimited access
- Q: Should admins be able to change their own permission level? → A: No, prevent admins from changing their own permission level with a warning message
- Q: What should happen when a verification link is clicked multiple times? → A: First click verifies account and invalidates token; subsequent clicks show "already verified" message
- Q: How should permission-denied notifications be displayed to users? → A: When users click buttons/interface elements requiring higher permissions, display modal notifications with messages like "請登入會員" (Please login) or "需升級為高級會員" (Requires upgrade to Premium Member)

### Session 2025-11-21

- Q: The spec mentions "video analysis feature" but the update refers to "Export CSV feature on Video Analysis page." Are these the same feature, or is Export CSV a sub-feature within video analysis? → A: Export CSV is a separate sub-feature within video analysis that requires additional permissions beyond just viewing analysis results. Members (Regular Members) and above can use it.
- Q: Should there be rate limiting or throttling on CSV export requests to prevent abuse or server overload? → A: Yes, implement rate limiting of 5 exports per user per hour to prevent abuse and protect server resources.
- Q: How should the 5 exports per hour rate limit reset work? → A: Rolling window (resets based on time since first export, e.g., if first export at 10:15, can export again after 11:15).
- Q: Should the 5 exports per hour rate limit apply equally to all authenticated users, or should Administrators have different (unlimited) access? → A: Administrators have unlimited CSV exports; rate limit only applies to Regular Members, Premium Members, and Website Editors.
- Q: Should there be maximum limits on CSV export file size or number of rows to prevent memory issues and long processing times? → A: Tiered row limits - Regular Members: 1,000 rows max, Premium Members and Website Editors: 3,000 rows max, Administrators: unlimited. Error message displayed if data exceeds limit.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New User Registration and Email Verification (Priority: P1)

A new user visits the platform and wants to create an account to access the disinformation analysis system. They provide their email address to register, receive a verification email, verify their account, and can then log in as a Regular Member.

**Why this priority**: This is the foundation of the member system. Without user registration and email verification, no one can create accounts or access member-specific features. This is the minimum viable functionality needed for any member system.

**Independent Test**: Can be fully tested by completing the registration flow from start to finish - user enters email, receives verification email, clicks verification link, and successfully logs in. Delivers value by allowing users to create verified accounts.

**Acceptance Scenarios**:

1. **Given** a new user on the registration page, **When** they enter a valid email address and submit the form, **Then** the system creates an unverified account and sends a verification email to that address
2. **Given** a user with an unverified account, **When** they click the verification link in their email, **Then** their account becomes verified and they can log in
3. **Given** a user with a verified account, **When** they enter their email and password on the login page, **Then** they are authenticated and redirected to the platform homepage
4. **Given** a user with an unverified account, **When** they attempt to log in, **Then** they are shown a message indicating their account is not yet verified
5. **Given** a user who already has an account, **When** they try to register again with the same email, **Then** they see an error message indicating the email is already registered

---

### User Story 2 - Mandatory Password Change on First Login (Priority: P2)

A newly verified user logs in for the first time with the default password (123456) and is immediately prompted to change their password to a secure one that meets standard password strength requirements before they can access any platform features.

**Why this priority**: Password security is critical but can be implemented after basic registration works. This ensures users don't continue using weak default passwords while still allowing the core registration flow to function.

**Independent Test**: Can be tested by logging in with a new account using the default password. System should immediately show password change prompt and not allow access until a strong password is set. Delivers value by enforcing security best practices.

**Acceptance Scenarios**:

1. **Given** a newly verified user logging in with default password 123456, **When** they successfully authenticate, **Then** they are immediately redirected to a password change page and cannot access other features
2. **Given** a user on the mandatory password change page, **When** they enter a new password that meets standard strength requirements, **Then** their password is updated and they gain full platform access
3. **Given** a user on the mandatory password change page, **When** they enter a weak password (e.g., less than 8 characters, no special characters), **Then** they see validation errors explaining the password strength requirements
4. **Given** a user who has already changed their default password, **When** they log in subsequently, **Then** they are not prompted to change password again and can access the platform directly

---

### User Story 3 - Admin Management of Member Accounts (Priority: P3)

An administrator logs in with the pre-configured admin account (themustbig@gmail.com) and can view all member accounts, manage user permission levels (Admin, Website Editor, Premium Member, Regular Member), and perform administrative actions on user accounts.

**Why this priority**: Admin functions are important for ongoing system management but not required for users to register and use the system. Basic registration and login must work before admin management is needed.

**Independent Test**: Can be tested by logging in as admin and verifying access to user management interface. Admin can view user list, change permission levels, and these changes take effect immediately. Delivers value by enabling platform administration.

**Acceptance Scenarios**:

1. **Given** the admin account (themustbig@gmail.com / 2025Nov20) is pre-configured, **When** an administrator logs in with these credentials, **Then** they have full access to all frontend and backend platform features
2. **Given** an administrator in the user management interface, **When** they view the list of members, **Then** they can see all registered users with their current permission level
3. **Given** an administrator viewing a member's account, **When** they change that member's permission level from Regular Member to Website Editor, **Then** the change takes effect immediately and that user gains Website Editor permissions
4. **Given** an administrator with full platform access, **When** they navigate to any frontend or backend feature, **Then** they have complete access without restrictions

---

### User Story 4 - Role-Based Access Control (Priority: P3)

The platform supports five distinct user roles with progressively expanding access rights: Visitors (unregistered), Regular Members, Premium Members, Website Editors, and Administrators. Each role has specific permissions controlling what pages they can view, what features they can use, and what actions they can perform.

**Detailed Role Permissions**:

**Visitors (Unregistered Users)**:
- Can view Home page and Videos List page
- Cannot access Channels List or Comments List
- Cannot use search function in Videos List
- Can use video "analysis" feature to view analysis results but cannot use "Export CSV" feature
- Cannot use video "update" feature

**Regular Members**:
- Can view Home, Channels List, Videos List, and Comments List pages
- Can use video "analysis" feature in Videos List, including "Export CSV" functionality (limited to 1,000 rows per export)
- Can access Comments List but cannot use search function
- Can only use "U-API import", cannot use "Official API import"
- After setting YouTube API Key in Settings, can use video "update" feature
- Can see "Upgrade to Premium Member" button in top right corner

**Premium Members**:
- Can use all current frontend features of the website
- Can export CSV files with up to 3,000 rows per export
- After setting YouTube API Key, can use "Official API import" button in top right of Videos List
- "Official API import" is limited to 10 videos per month
- After completing identity verification, unlimited "Official API import" is enabled

**Website Editors**:
- Can use all frontend functions, including all Premium Member capabilities
- Can export CSV files with up to 3,000 rows per export
- Has full access to all frontend features without restrictions

**Administrators**:
- Have unrestricted access to all frontend and backend platform features
- Can export CSV files without rate limiting (unlimited exports) and without row count limits (unlimited rows per export)
- Backend admin panel (data CRUD system, permission management system) UI will be included as placeholder, with full implementation to come later

**All Authenticated Members** (Regular, Paid, Website Editor, Administrator):
- Access to Settings interface in frontend where they can change their password (subject to password strength validation)

**Why this priority**: Role-based permissions can be implemented after the core registration and admin management features are working. This is important for proper access control but builds on the foundation of earlier priorities.

**Independent Test**: Can be tested by logging in as each role type and verifying the features they can and cannot access. Delivers value by enforcing proper access boundaries between different user types.

**Acceptance Scenarios**:

1. **Given** a visitor (unregistered user), **When** they click on a button/link to access Comments List, **Then** a modal displays with message "請登入會員" (Please login as a member)
2. **Given** a Regular Member, **When** they click the "Official API import" button, **Then** a modal displays with message "需升級為高級會員" (Requires upgrade to Premium Member)
3. **Given** a Regular Member without YouTube API Key configured, **When** they attempt to use video "update", **Then** they see a prompt to configure their API Key in Settings
4. **Given** a Premium Member who has set their YouTube API Key, **When** they use "Official API import", **Then** they can import videos with a counter showing remaining quota (X/10 this month)
5. **Given** a Premium Member who has completed identity verification, **When** they use "Official API import", **Then** they can import unlimited videos without quota restrictions
6. **Given** a Website Editor, **When** they access any frontend feature, **Then** they have full access equivalent to a verified Premium Member
7. **Given** a visitor, **When** they click the search button in Videos List, **Then** a modal displays with message "請登入會員" (Please login as a member)
8. **Given** a visitor viewing video analysis results, **When** they click the "Export CSV" button, **Then** a modal displays with message "請登入會員" (Please login as a member)
9. **Given** a Regular Member viewing video analysis results, **When** they click the "Export CSV" button, **Then** the analysis data is exported as a CSV file and downloaded to their device
10. **Given** a Regular Member who has already exported 5 CSV files in the current hour, **When** they attempt to export another CSV, **Then** system displays an error message showing "5/5 exports used" and time until limit resets
11. **Given** a Regular Member viewing analysis data with 1,500 rows, **When** they attempt to export CSV, **Then** system displays an error message showing their 1,000 row limit is exceeded and suggests filtering data or upgrading to Premium Member
12. **Given** a Premium Member viewing analysis data with 2,500 rows, **When** they click "Export CSV", **Then** the data is successfully exported as all 2,500 rows are within their 3,000 row limit
13. **Given** a Premium Member viewing analysis data with 4,000 rows, **When** they attempt to export CSV, **Then** system displays an error message showing their 3,000 row limit is exceeded and suggests filtering data or contacting an administrator
14. **Given** an Administrator viewing video analysis results, **When** they export CSV files more than 5 times in an hour, **Then** all exports succeed without rate limiting restrictions
15. **Given** an Administrator viewing analysis data with 10,000 rows, **When** they click "Export CSV", **Then** the data is successfully exported with all rows as administrators have no row limits
16. **Given** any authenticated member, **When** they access Settings and change their password to a weak one, **Then** they see validation errors and the change is rejected

---

### Edge Cases

- **Verification email expiration or non-delivery**: Users can request a new verification email through the login page if their original link expired or they never received it. Rate limiting prevents abuse (maximum 3 requests per hour per email address). Until email verification is completed, users cannot log in.
- **Forgotten password**: Users can request a password reset through the login page. System sends a time-limited secure token link to their registered email address. Password reset follows same security requirements as initial password change.
- **Admin self-permission change prevention**: System prevents administrators from changing their own permission level. When an admin attempts this, system displays a warning message explaining that permission changes to their own account must be made by another administrator.
- **Multiple verification link clicks**: When a verification link is clicked for the first time, the account is verified and the token is immediately invalidated. Subsequent clicks on the same link display an "already verified" message indicating the account verification was already completed.
- What happens when an admin deletes or deactivates their own account?
- What happens if someone tries to register with a disposable or invalid email address?
- What happens when a user's session expires while they're on the mandatory password change page?
- **Premium Member quota limit reached**: When a Premium Member reaches their 10 videos per month quota limit and attempts another import, system displays an error message explaining quota is exceeded, shows current usage (10/10 this month), and suggests completing identity verification for unlimited access.
- What happens if a Regular Member configures an invalid or expired YouTube API Key in their Settings?
- What happens when an admin changes a Premium Member's permission level to Regular Member mid-month? Does their quota usage carry over or reset?
- What happens if a Premium Member completes identity verification after already using some of their monthly quota?
- What happens when the calendar month changes while a Premium Member is in the middle of an API import operation?
- What happens if multiple users (Website Editor and Premium Member) try to import the same video simultaneously?
- What happens when a visitor tries to directly access a restricted page URL without being logged in?
- **CSV export rate limit exceeded**: When an authenticated user attempts to export CSV after reaching the 5 exports per hour limit, system displays an error message showing current usage (e.g., "5/5 exports used") and time remaining until the limit resets (e.g., "Limit resets in 42 minutes"). The rate limit uses a rolling window mechanism, resetting 60 minutes after the user's first export in the current window. Export request is blocked until the window expires.
- **CSV export row count limit exceeded**: When a user attempts to export analysis data that exceeds their role-based row limit (Regular Members: 1,000 rows, Premium Members/Website Editors: 3,000 rows), system displays an error message indicating the data size exceeds the export limit, shows the actual row count and the user's limit (e.g., "Data contains 2,500 rows, your limit is 1,000 rows"), and suggests applying filters to reduce the dataset. For Regular Members, message also suggests upgrading to Premium Member for higher limits (3,000 rows). All users are informed they can contact an administrator for unlimited exports. Administrators can export any amount of data without row limits.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow new users to register using only their email address
- **FR-002**: System MUST validate email addresses for proper format before accepting registration
- **FR-003**: System MUST send verification emails to newly registered users immediately after registration
- **FR-004**: System MUST create user accounts in an "unverified" state until email verification is completed
- **FR-005**: System MUST provide a unique, secure verification link in the verification email that expires after a reasonable period
- **FR-006**: System MUST mark user accounts as "verified" when they click the verification link
- **FR-006a**: System MUST immediately invalidate the verification token after successful account verification
- **FR-006b**: System MUST display an "already verified" message when a previously used verification link is clicked again
- **FR-007**: System MUST only allow verified users to log in to the platform
- **FR-007a**: System MUST provide a mechanism for users to request a new verification email if their original link expired or was not received
- **FR-007b**: System MUST implement rate limiting on verification email requests (maximum 3 requests per hour per email address) to prevent abuse
- **FR-007c**: System MUST display clear messaging when unverified users attempt to log in, with option to request new verification email
- **FR-008**: System MUST assign all newly registered users the default password "123456"
- **FR-009**: System MUST detect when a user logs in for the first time with the default password
- **FR-010**: System MUST immediately redirect users with default passwords to a password change page before allowing platform access
- **FR-011**: System MUST enforce standard password strength requirements including minimum 8 characters, at least one uppercase letter, one lowercase letter, one number, and one special character
- **FR-012**: System MUST prevent users from bypassing the mandatory password change requirement
- **FR-012a**: System MUST provide a password reset mechanism accessible from the login page
- **FR-012b**: System MUST send a time-limited secure token link to the user's registered email address when password reset is requested
- **FR-012c**: System MUST enforce the same password strength requirements for reset passwords as for initial password changes
- **FR-012d**: System MUST invalidate password reset tokens after successful use or after expiration (24 hours)
- **FR-012e**: System MUST implement rate limiting on password reset requests (maximum 3 requests per hour per email address) to prevent abuse
- **FR-013**: System MUST support five distinct permission levels: Visitor (unregistered), Regular Member, Premium Member, Website Editor, and Administrator
- **FR-014**: System MUST pre-configure an administrator account with email "themustbig@gmail.com" and password "2025Nov20"
- **FR-015**: System MUST grant administrators unrestricted access to all frontend and backend platform features
- **FR-016**: System MUST provide administrators with a user management interface to view all registered members
- **FR-017**: System MUST allow administrators to change any user's permission level
- **FR-017a**: System MUST prevent administrators from changing their own permission level
- **FR-017b**: System MUST display a warning message when an admin attempts to change their own permission, explaining that another administrator must make this change
- **FR-018**: System MUST apply permission level changes immediately without requiring user re-login
- **FR-019**: System MUST prevent duplicate registrations with the same email address
- **FR-020**: System MUST store passwords securely using industry-standard hashing algorithms
- **FR-021**: System MUST provide clear error messages when registration, verification, or login fails
- **FR-022**: System MUST maintain user sessions after successful authentication
- **FR-023**: System MUST log all security-related events including registration, login, password changes, and permission modifications
- **FR-023a**: System MUST display permission-denied notifications as modal dialogs when users click buttons/interface elements requiring higher permissions
- **FR-023b**: System MUST show message "請登入會員" (Please login as a member) in modal when visitors attempt to access member-only features
- **FR-023c**: System MUST show message "需升級為高級會員" (Requires upgrade to Premium Member) in modal when Regular Members attempt to access Premium Member features
- **FR-024**: System MUST allow visitors (unregistered users) to view Home page and Videos List page without authentication
- **FR-025**: System MUST prevent visitors from accessing Channels List and Comments List pages
- **FR-026**: System MUST prevent visitors from using search functionality in Videos List
- **FR-027**: System MUST allow visitors to use video "analysis" feature to view analysis results but prevent them from using "Export CSV" functionality
- **FR-027a**: System MUST prevent visitors from using video "update" feature
- **FR-027b**: System MUST allow Regular Members and above to use "Export CSV" functionality within video analysis
- **FR-027c**: System MUST display a modal notification with message "請登入會員" (Please login as a member) when visitors attempt to use "Export CSV"
- **FR-027d**: System MUST implement rate limiting on CSV export requests at 5 exports per user per hour for Regular Members, Premium Members, and Website Editors using a rolling window mechanism
- **FR-027e**: System MUST allow Administrators unlimited CSV export access without rate limiting
- **FR-027f**: System MUST track CSV export usage per user with timestamp to enforce rate limiting, where the rate limit resets 60 minutes after the first export in the current window
- **FR-027g**: System MUST display an error message when a user attempts to export CSV after exceeding the 5 exports per hour limit, showing current usage count and time until limit resets (based on 60 minutes from first export)
- **FR-027h**: System MUST implement tiered row limits for CSV exports: Regular Members limited to 1,000 rows per export, Premium Members and Website Editors limited to 3,000 rows per export
- **FR-027i**: System MUST allow Administrators to export CSV files without row count limits (unlimited rows)
- **FR-027j**: System MUST display an error message when analysis data exceeds the user's row limit, showing the actual row count, the user's limit based on their role, and suggesting they filter the data, upgrade membership (for Regular Members), or contact an administrator
- **FR-028**: System MUST allow Regular Members to access Home, Channels List, Videos List, and Comments List pages
- **FR-029**: System MUST prevent Regular Members from using search functionality in Comments List
- **FR-030**: System MUST allow Regular Members to use "U-API import" but prevent them from using "Official API import"
- **FR-031**: System MUST provide all authenticated members with a Settings interface to change their password
- **FR-032**: System MUST validate password strength in the Settings interface and reject weak passwords
- **FR-033**: System MUST allow Regular Members to configure a YouTube API Key in their Settings
- **FR-034**: System MUST enable video "update" feature for Regular Members only after they have configured a YouTube API Key
- **FR-035**: System MUST display an "Upgrade to Premium Member" button in the top right corner for Regular Members
- **FR-036**: System MUST grant Premium Members access to all current frontend features
- **FR-037**: System MUST allow Premium Members to use "Official API import" feature after configuring their YouTube API Key
- **FR-038**: System MUST enforce a quota limit of 10 videos per month for Premium Members using "Official API import"
- **FR-039**: System MUST display remaining quota counter when Premium Members use "Official API import"
- **FR-039a**: System MUST prevent import and display error message when Premium Member attempts to exceed monthly quota limit
- **FR-039b**: System MUST show current quota usage (e.g., "10/10 this month") in the error message when quota is exceeded
- **FR-039c**: System MUST include a suggestion to complete identity verification for unlimited access in the quota exceeded error message
- **FR-040**: System MUST support identity verification process for Premium Members
- **FR-041**: System MUST remove the 10 videos per month quota limit for Premium Members who have completed identity verification
- **FR-042**: System MUST grant Website Editors full access to all frontend features equivalent to verified Premium Members
- **FR-043**: System MUST include a placeholder UI for the admin backend panel (data CRUD system and permission management system)

### Key Entities

- **User Account**: Represents a registered user with attributes including email address (unique identifier), hashed password, permission level, verification status, identity verification status, YouTube API Key, account creation timestamp, and last login timestamp
- **Permission Level**: Defines the access rights for a user, with five possible values: Visitor/unregistered (public access), Regular Member (basic authenticated access), Premium Member (premium features with quota), Website Editor (full frontend access), and Administrator (unrestricted access)
- **Verification Token**: A unique, time-limited token sent to users for email verification, with attributes including token value, associated email, expiration timestamp, and used status
- **Password Reset Token**: A unique, time-limited token sent to users for password reset, with attributes including token value, associated email, expiration timestamp (24 hours), and used status
- **Admin Account**: A special pre-configured account with administrator privileges, email "themustbig@gmail.com" and password "2025Nov20"
- **API Import Quota**: Tracks usage of "Official API import" feature for Premium Members, with attributes including user ID, current month's usage count, monthly limit (10 or unlimited based on identity verification status), and reset date
- **Identity Verification Status**: Indicates whether a Premium Member has completed identity verification, determining if they have unlimited API import quota

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: New users can complete the registration and email verification process in under 5 minutes
- **SC-002**: 95% of verification emails are delivered successfully within 2 minutes of registration
- **SC-003**: Users can successfully change their password from the default within 1 minute
- **SC-004**: Password strength validation rejects 100% of passwords that don't meet standard requirements
- **SC-005**: Administrators can view the complete member list and change user permissions within 30 seconds
- **SC-006**: Role-based access control prevents 100% of unauthorized access attempts to restricted features
- **SC-007**: The pre-configured admin account can successfully authenticate and access all platform features
- **SC-008**: Zero users can bypass email verification to access the platform
- **SC-009**: Zero users can bypass mandatory password change when using default password
- **SC-010**: System maintains secure password storage with zero plain-text password exposures

### Assumptions

1. **Email Delivery**: Assumes the platform has access to a working email service provider for sending verification emails
2. **Default Password Distribution**: Assumes the default password "123456" is communicated to users through a secure out-of-band channel (not shown in this spec)
3. **Permission Scope**: Specific access rights for each role (Visitor, Regular Member, Premium Member, Website Editor, Administrator) are defined in User Story 4 based on existing platform features
4. **Payment Integration**: Assumes no payment processing or subscription management is included in this phase - Premium Member status is assigned manually by administrators through the user management interface
5. **Upgrade Button Functionality**: The "Upgrade to Premium Member" button shown to Regular Members will link to information about becoming a Premium Member, but the actual upgrade process is manual (requires administrator approval)
6. **Identity Verification Process**: The specific method and requirements for Premium Member identity verification will be defined separately - this spec only defines that verified Premium Members get unlimited API import quota
7. **YouTube API Integration**: Assumes the platform already has or will have YouTube API integration for video import features - this spec only defines which roles can access these features
8. **API Import Quota Reset**: Assumes the 10 videos per month quota for Premium Members resets on the first day of each calendar month
9. **Password Reset Token Expiration**: Password reset links expire after 24 hours, matching verification token expiration as an industry-standard practice
10. **Verification Token Expiration**: Assumes verification links expire after 24 hours as an industry-standard practice
11. **Session Management**: Assumes session duration and security follows platform's existing authentication standards
12. **Account Deletion**: Assumes account deletion and deactivation features will be addressed separately
13. **Multi-language Support**: Assumes all user-facing messages, emails, and interfaces are in Traditional Chinese based on the requirements provided in Chinese
14. **Admin Backend Placeholder**: The admin backend UI (data CRUD and permission management) will be created as a placeholder interface with full functionality to be implemented in a future phase

### Dependencies

- **Email Service**: Requires integration with an email service provider (SMTP server or API like SendGrid, AWS SES, etc.)
- **Existing Platform**: Assumes an existing platform with frontend and backend that this member system will be integrated into
- **Database**: Requires database schema to store user accounts, permissions, and verification tokens
