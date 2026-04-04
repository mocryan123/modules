# Class Scheduler Module Specification

## 1. Module Identity
- Name: `Class Scheduler`
- Slug: `cs`
- Source: `modules/cs/main.php`
- Version: `1.0.0`

## 2. Purpose
Provide a section-centric scheduling system for weekly academic timetables with instructor and room assignment.

## 3. Scope
- Manage section master data
- Manage schedule entries per section
- Manage instructor directory
- Manage room inventory
- Render internal and public timetable views

## 4. Functional Requirements

### 4.1 Dashboard
- System shall render dashboard via shortcode `[bntm_class_scheduler]`.
- User must be logged in; otherwise show notice.
- Dashboard shall provide tabs:
  - Overview
  - Timetable View
  - Sections
  - Schedule Entry
  - Instructors
  - Rooms

### 4.2 Sections
- Create, update, delete sections.
- Section fields:
  - `section_name`, `course_code`, `year_level`, `block`, `academic_year`, `semester`, `status`
- Deleting a section shall also delete its schedule entries.

### 4.3 Schedule Entries
- Create, update, delete schedule entries.
- Required at save:
  - `section_id`, `subject_code`, `day_group`, `time_slot`, `instructor_initials`, `room`
- Entry uniqueness rule:
  - Reject if another row exists with same `section_id + day_group + time_slot` (excluding edited row ID).

### 4.4 Instructors
- Create, update, delete instructor rows.
- Fields:
  - `initials`, `full_name`, `department`, `status`

### 4.5 Rooms
- Create, update, delete room rows.
- Fields:
  - `room_code`, `building`, `capacity`, `room_type`, `status`

### 4.6 Timetable Rendering
- Timetable view shall show section schedule across:
  - Days: Monday to Sunday
  - Time slots: fixed 8-slot list
- Entry display shall include:
  - subject code
  - instructor initials
  - room

### 4.7 Public Timetable
- Public shortcode `[bntm_section_timetable]` shall render one section by query param `section`.
- If `section` is missing or invalid, show notice.

### 4.8 Bulk Import
- Accept array payload of entries and target section.
- For each entry:
  - insert if no existing `section_id + day_group + time_slot`
  - skip duplicates
- Return inserted count.

## 5. Non-Functional Requirements
- Must run within WordPress AJAX/shortcode lifecycle.
- SQL operations must be scoped by `business_id` where implemented.
- Input sanitization required for all user-provided values.
- Nonce protection required for all write endpoints.

## 6. Data Specification

### 6.1 Tables
- `{$prefix}cs_sections`
- `{$prefix}cs_schedules`
- `{$prefix}cs_instructors`
- `{$prefix}cs_rooms`

### 6.2 Key Constraints (Current)
- Each table has `id` primary key and `rand_id` unique.
- `cs_schedules` indexed by `section_id`, `business_id`, `(day_group, time_slot)`.
- No DB-level unique index currently enforces schedule uniqueness tuple.

## 7. API Specification (AJAX)

### 7.1 Section
- `cs_save_section` (nonce: `cs_section_nonce`)
  - upsert section record.
- `cs_delete_section` (nonce: `cs_section_nonce`)
  - delete section and dependent schedule rows.

### 7.2 Schedule
- `cs_save_schedule` (nonce: `cs_schedule_nonce`)
  - upsert schedule entry with conflict check.
- `cs_delete_schedule` (nonce: `cs_schedule_nonce`)
  - delete schedule row.
- `cs_get_section_data` (nonce: `cs_schedule_nonce`)
  - fetch entries for one section.
- `cs_bulk_import_section` (nonce: `cs_section_nonce`)
  - bulk insert entries with duplicate skip.

### 7.3 Instructors
- `cs_save_instructor` (nonce: `cs_instructor_nonce`)
- `cs_delete_instructor` (nonce: `cs_instructor_nonce`)

### 7.4 Rooms
- `cs_save_room` (nonce: `cs_room_nonce`)
- `cs_delete_room` (nonce: `cs_room_nonce`)

### 7.5 Common Endpoint Behavior
- Unauthorized (not logged in) => `wp_send_json_error(['message' => 'Unauthorized'])`
- DB failure => structured error message
- Success => structured success message

## 8. Business Rules
- `business_id` is derived from `get_current_user_id()`.
- Section display name convention from UI:
  - `<course_code><year_level> <block>`
- Day-group mapping:
  - `mon_thu` => Monday, Thursday
  - `tue_fri` => Tuesday, Friday
  - `wed_sat` => Wednesday, Saturday

## 9. Security Specification
- CSRF protection: nonce verification via `check_ajax_referer`.
- AuthN gate: all AJAX handlers require logged-in user.
- AuthZ is minimal:
  - no explicit role checks in current module for tabs/actions.
- Input sanitization:
  - `sanitize_text_field`, `intval`, `strtoupper`, and explicit casting.

## 10. Known Gaps
- Role-based tab/action restriction is not implemented.
- Room/instructor cross-section conflict helper functions exist but are not enforced during `cs_save_schedule`.
- Public timetable shortcode does not verify section ownership by business ID.
- Data integrity for schedule uniqueness relies on app logic only (not DB unique key).

## 11. Recommended Enhancements
- Add DB unique constraint:
  - `UNIQUE(section_id, day_group, time_slot)`
- Enforce `cs_check_room_conflict` and `cs_check_instructor_conflict` in schedule save.
- Add role-policy layer:
  - Admin/Owner/Manager full access
  - Staff limited view/edit as required
- Add audit fields (`created_by`, `updated_by`) and change log.

