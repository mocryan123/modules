# Class Scheduler Module Documentation

## Overview
The `cs` (Class Scheduler) module manages section timetables with:
- Sections
- Schedule entries
- Instructors
- Rooms

It includes:
- Admin dashboard shortcode: `[bntm_class_scheduler]`
- Public section timetable shortcode: `[bntm_section_timetable]`

Source file: `modules/cs/main.php`

## Features
- Weekly timetable rendering (Monday to Sunday)
- Section-based scheduling with fixed time slots
- Instructor and room management
- Schedule conflict check (same section + day group + time slot)
- Bulk import schedule entries into a section
- Print-ready timetable view

## Shortcodes
- `[bntm_class_scheduler]`
  - Shows tabbed scheduler dashboard for logged-in users.
- `[bntm_section_timetable]`
  - Public timetable output for one section via URL query param:
  - `?section=<section_id>`

## Tabs in Dashboard
- Overview
- Timetable View
- Sections
- Schedule Entry
- Instructors
- Rooms

## Data Model (Quick View)
- `cs_sections`: section metadata (course, year, block, AY, semester, status)
- `cs_schedules`: timetable entries (subject, instructor, room, day group, time slot)
- `cs_instructors`: faculty directory
- `cs_rooms`: room master list

All records are scoped by `business_id` (current user ID).

## Time/Day Structure
- Time slots:
  - 7:30 - 9:00
  - 9:00 - 10:30
  - 10:30 - 12:00
  - 12:00 - 1:30
  - 1:30 - 3:00
  - 3:00 - 4:30
  - 4:30 - 6:00
  - 6:00 - 7:30
- Day groups:
  - `mon_thu` (Monday/Thursday)
  - `tue_fri` (Tuesday/Friday)
  - `wed_sat` (Wednesday/Saturday)

## Access and Security
- All dashboard and AJAX actions require logged-in users.
- AJAX uses nonce verification per domain:
  - `cs_section_nonce`
  - `cs_schedule_nonce`
  - `cs_instructor_nonce`
  - `cs_room_nonce`

## Operational Notes
- `section_name` is generated in UI as: `<course_code><year_level> <block>`
- Schedule save checks for duplicate tuple:
  - `section_id + day_group + time_slot`
- Bulk import skips entries that already exist at the same tuple above.

## Limitations
- No role-specific tab restrictions in this module yet (only login check).
- Conflict checking currently enforces per-section/day-group/time-slot only.
  - Helper functions for room/instructor cross-section conflict exist, but are not enforced during save.
- Public shortcode uses section ID from URL directly; no business ownership filter.

## Suggested Next Improvements
- Add role-based restrictions (owner/manager/staff behavior)
- Enforce room/instructor global conflicts in `cs_save_schedule`
- Add pagination/search for large section and schedule sets
- Add export (CSV/PDF) and versioned timetable snapshots

