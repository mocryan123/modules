# Sequential Booking Implementation - Complete Guide

## Overview
The booking system has been completely redesigned with a **sequential/upsell booking flow**. Instead of having separate Hotel, Car, and Yacht booking pages, customers now go through an intelligent flow that suggests complementary services based on their initial choice.

## How It Works

### Step 1: Service Selection Screen
When customers first visit the booking page, they see three equally prominent service cards:
- 🏨 **Hotel Room** - Book a comfortable place to stay
- 🚗 **Car Rental** - Get around comfortably  
- ⛵ **Yacht Cruise** - Explore the island by sea

Customers click their primary service to begin.

### Step 2: Intelligent Upsell Flow
Based on the service selected first, the system asks about complementary services using modal prompts:

**If Hotel is Selected First:**
```
Hotel → "Would you like to rent a car?" (YES/NO)
  → If YES: Show car form → "Would you like to cruise the island?" (YES/NO)
  → If NO: "Would you like to cruise the island?" (YES/NO)
```

**If Car is Selected First:**
```
Car → "Need a place to stay?" (YES/NO)
  → If YES: Show hotel form → "Would you like to cruise the island?" (YES/NO)
  → If NO: "Would you like to cruise the island?" (YES/NO)
```

**If Yacht is Selected First:**
```
Yacht → "Would you like to rent a car?" (YES/NO)
  → If YES: Show car form → "Need a place to stay?" (YES/NO)
  → If NO: "Need a place to stay?" (YES/NO)
```

### Step 3: Unified Booking Form
The form dynamically shows/hides sections based on selected services. Only selected services require data entry.

### Step 4: Smart Quotation Display
The quotation page only displays sections for booked services. No confusing pricing for unselected items.

---

## Technical Implementation

### Frontend (JavaScript)
**Location:** `bm_get_sequential_booking_script()` function

#### Key State Variables:
```javascript
selectedServices = [];  // Array tracking ['hotel', 'yacht', 'car'] based on selections
currentServiceIndex = 0; // Current step in the upsell flow
```

#### Service Flow Logic:
```javascript
const serviceFlows = {
    hotel: ['hotel', 'car', 'yacht'],
    car: ['car', 'hotel', 'yacht'],
    yacht: ['yacht', 'car', 'hotel']
};
```

#### How It Works:
1. User selects a service on the selection screen → `selectInitialService(service)`
2. Initial service is added to `selectedServices` array
3. Form sections are shown/hidden via `showFormForCurrentServices()`
4. Upsell modal displays asking for next service → `askForNextService()`
5. User says Yes → Service is added to array, next upsell displays
6. User says No → Skip to next service in flow
7. When form submitted, `selected_services` field contains comma-separated string

#### Form Validation:
Only required fields for selected services are validated:
```javascript
if (selectedServices.includes('hotel')) {
    checkInInput.required = true;
    checkOutInput.required = true;
} else {
    checkInInput.required = false;
    checkOutInput.required = false;
}
```

#### Price Calculation:
Total price is calculated only from selected services:
```javascript
function calculateTotalAndDeposit() {
    let total = 0;
    
    if (selectedServices.includes('hotel')) {
        total += calculateRoomSubtotals();
    }
    if (selectedServices.includes('car')) {
        total += calculateCarSubtotal();
    }
    if (selectedServices.includes('yacht')) {
        total += calculateYachtSubtotal();
    }
    
    // ... update display
}
```

### Backend (PHP)
**Location:** `bntm_ajax_bm_submit_booking()` function

#### Processing:
1. **Parse Selected Services:**
```php
$selected_services_str = sanitize_text_field($_POST['selected_services'] ?? '');
$selected_services = array_filter(array_map('trim', explode(',', $selected_services_str)));
```

2. **Dynamic Validation:**
Only validates required fields for selected services:
```php
if (in_array('hotel', $selected_services)) {
    $required_fields = array_merge($required_fields, ['check_in_date', 'check_out_date']);
}
if (in_array('car', $selected_services)) {
    $required_fields = array_merge($required_fields, ['car_pickup_date', 'car_return_date']);
}
if (in_array('yacht', $selected_services)) {
    $required_fields = array_merge($required_fields, ['yacht_rental_date', 'yacht_duration']);
}
```

3. **Selective Database Insertion:**
Only inserts records for selected services:
```php
// Insert rooms only if hotel is selected
if (in_array('hotel', $selected_services)) {
    $rooms_table = $wpdb->prefix . 'bm_rooms';
    foreach ($rooms_data as $room) {
        $wpdb->insert($rooms_table, $room_data);
    }
}

// Insert car rental only if car is selected
if (in_array('car', $selected_services) && $car_data) {
    $car_rentals_table = $wpdb->prefix . 'bm_car_rentals';
    $wpdb->insert($car_rentals_table, $car_data);
}

// Insert yacht rental only if yacht is selected
if (in_array('yacht', $selected_services) && $yacht_data) {
    $yacht_rentals_table = $wpdb->prefix . 'bm_yacht_rentals';
    $wpdb->insert($yacht_rentals_table, $yacht_data);
}
```

4. **Smart Total Calculation:**
```php
$total_amount = $room_total + $yacht_total + $car_total;
// Only calculates for booked services
```

### Database
No changes to the database structure. The existing tables are still used:
- `bm_bookings` - Main booking record (one per booking)
- `bm_rooms` - Hotel rooms (only if hotel selected)
- `bm_yacht_rentals` - Yacht rentals (only if yacht selected)
- `bm_car_rentals` - Car rentals (only if car selected)

### Quotation Display
**Location:** `bntm_shortcode_bm_view_quotation()` function

The quotation already conditionally displays sections:
```php
<?php if (!empty($rooms)): ?>
    <!-- Hotel section -->
<?php endif; ?>

<?php if ($yacht): ?>
    <!-- Yacht section -->
<?php endif; ?>

<?php if ($car): ?>
    <!-- Car section -->
<?php endif; ?>
```

Since database records are only created for selected services, the sections automatically show only booked items.

---

## User Experience Flow Diagram

```
┌─────────────────────────────────────┐
│   Service Selection Screen          │
│  [Hotel]  [Car]  [Yacht]            │
└────────────────────┬────────────────┘
                     │
         ┌───────────┼───────────┐
         │           │           │
    ┌────▼────┐ ┌───▼────┐ ┌───▼────┐
    │ Hotel   │ │  Car   │ │ Yacht  │
    │ Selected│ │Selected│ │Selected│
    └────┬────┘ └───┬────┘ └───┬────┘
         │          │          │
    [Show car?]  [Show hotel?][Show car?]
    Y  │  N       Y  │  N      Y  │  N
    ┌──┴──┐      ┌──┴──┐      ┌──┴──┐
    │     │      │     │      │     │
   [Yes][No]   [Yes][No]    [Yes][No]
    │     │      │     │      │     │
    └──┬──┴──┬───┴──┬──┴──────┴──┬──┘
       │     │      │           │
    [Show yacht?] [Show yacht?]
    Y  │  N       Y  │  N
    ┌──┴──┐      ┌──┴──┐
    │     │      │     │
   [Yes][No]   [Yes][No]
    │     │      │     │
    └─────┴──────┴─────┘
          │
    ┌─────▼──────┐
    │ Show Form  │
    │ with only  │
    │ selected   │
    │ services   │
    └─────┬──────┘
          │
    ┌─────▼──────────┐
    │ Submit Booking │
    │ Get Quotation  │
    └────────────────┘
```

---

## Key Features

### 1. Flexible Starting Point
Customers can start with any service (Hotel, Car, or Yacht), not just Hotel.

### 2. Smart Sequencing
The system suggests services in logical order:
- Hotel → Car → Yacht
- Car → Hotel → Yacht
- Yacht → Car → Hotel

### 3. Dynamic Form
Form sections appear/disappear based on selections. Only selected services have required fields.

### 4. Real-Time Pricing
Total and deposit calculations update as customers add/remove services.

### 5. Clean Quotation
Quotation shows only booked services and their prices. No confusing "Why am I seeing prices for things I didn't book?"

### 6. Transparent Database
Database only stores records for actually booked services. No empty yacht_rentals or car_rentals records.

---

## Testing Checklist

- [ ] **Hotel First Flow**
  - Select Hotel → Fill hotel info → See car upsell prompt
  - Select Yes → Fill car info → See yacht upsell prompt
  - Verify quotation shows ONLY hotel + car sections

- [ ] **Car First Flow**
  - Select Car → Fill car info → See hotel upsell prompt
  - Select Yes → Fill hotel info → See yacht upsell prompt
  - Verify quotation shows ONLY car + hotel sections

- [ ] **Yacht Only Flow**
  - Select Yacht → Fill yacht info → See all upsells
  - Decline all → Verify quotation shows ONLY yacht section

- [ ] **Mixed Booking**
  - Book hotel + yacht (skip car)
  - Verify quotation shows hotel + yacht, NO car section

- [ ] **Back Button**
  - Click "Back to Service Selection" during form
  - Verify it returns to service selection screen

- [ ] **Pricing Calculation**
  - Verify totals match selected services only
  - Verify deposit percentage applies correctly
  - Change quantity/dates and verify prices update

- [ ] **Form Validation**
  - Leave required fields empty for selected service
  - Try to submit → should show error
  - Fill all fields → should submit successfully

- [ ] **Email Quotation**
  - Submit booking with multiple services
  - Verify email shows only booked services
  - Verify email totals are correct

---

## CSS Changes

New CSS classes added:
- `.bm-service-selection` - Service selection screen container
- `.bm-service-cards` - Grid of service cards
- `.bm-service-card` - Individual service card
- `.bm-service-icon` - Large emoji icon
- `.bm-modal` - Upsell modal overlay
- `.bm-modal-content` - Modal content box
- `.bm-modal-buttons` - Modal button container
- `.bm-service-section` - Service-specific form sections

All responsive design maintained for mobile devices.

---

## Browser Compatibility

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Mobile)

JavaScript required for sequential prompts.

---

## Future Enhancements

1. **Bundle Discounts** - Automatic discounts for 2+ services
2. **Save Draft Bookings** - Allow users to pause and resume later
3. **Availability Calendar** - Real-time availability checking
4. **Multi-Language Support** - Localized form text
5. **Booking Templates** - Pre-made upsell suggestions based on season/packages

---

## Support & Troubleshooting

### Issue: Services not showing after selection
**Check:** Verify JavaScript console for errors. Ensure `selectedServices` array contains correct service names.

### Issue: Prices not calculating correctly
**Check:** Verify form field names match JavaScript selectors. Check that dates are valid.

### Issue: Empty sections in quotation
**Check:** This is expected behavior. Sections only show if records exist in database. Check `bm_rooms`, `bm_yacht_rentals`, `bm_car_rentals` tables.

### Issue: Validation errors on empty fields
**Check:** Make sure unselected services have `required = false` in JavaScript. Check that input fields have correct names.

---

## Code Locations

| Component | Location | Function |
|-----------|----------|----------|
| Main Form | main.php | `bntm_shortcode_bm_book_hotel()` |
| JavaScript | main.php | `bm_get_sequential_booking_script()` |
| Styles | main.php | `bm_get_booking_styles()` |
| AJAX Handler | main.php | `bntm_ajax_bm_submit_booking()` |
| Quotation | main.php | `bntm_shortcode_bm_view_quotation()` |

---

## Configuration

No new settings needed! The system uses existing settings:
- `bm_hotel_name` - Hotel name
- `bm_deposit_percentage` - Deposit percentage (default 30%)
- `bm_paymaya_*` - PayMaya integration settings

---

## Questions?

For detailed implementation questions, refer to the inline code comments in [main.php](main.php).
