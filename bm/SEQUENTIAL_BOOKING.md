# Sequential/Upsell Booking System

## Overview
The booking management module has been redesigned to implement a sequential, intelligent booking experience. Instead of forcing customers to choose between Hotel, Yacht, or Car bookings, the system now:

1. **Asks what they want to book first** - Hotel, Yacht, or Car
2. **Then intelligently suggests complementary services** based on their first choice
3. **Tracks all selected services** and shows only booked items in the quotation
4. **Calculates totals only for selected items** - no confusing pricing for items the customer didn't select

## How It Works

### Step 1: Service Selection
The customer arrives at the booking page and is presented with three equally prominent options:
- 🏨 **Hotel Room** - Book a comfortable place to stay
- ⛵ **Yacht Cruise** - Explore the island by sea
- 🚗 **Car Rental** - Get around comfortably

### Step 2: Main Booking & Intelligent Upsells
Once a primary service is selected, the form displays:
1. Customer information (always)
2. Details for the selected service
3. **Smart upsell prompts** as modals

#### Upsell Logic by Initial Choice:

**If Hotel is Selected First:**
```
Hotel → Would you like to rent a car? (YES/NO)
  → If YES: Show car form → Would you like to cruise the island? (YES/NO)
  → If NO: Would you like to cruise the island? (YES/NO)
```

**If Car is Selected First:**
```
Car → Need a place to stay? (YES/NO)
  → If YES: Show hotel form → Would you like to cruise? (YES/NO)
  → If NO: Would you like to cruise? (YES/NO)
```

**If Yacht is Selected First:**
```
Yacht → Need transportation? (YES/NO)
  → If YES: Show car form → Need place to stay? (YES/NO)
  → If NO: Need place to stay? (YES/NO)
```

### Step 3: Smart Quotation Display
The quotation only shows:
- ✅ **Hotel section** - IF customer actually booked rooms
- ✅ **Yacht section** - IF customer actually booked a yacht
- ✅ **Car section** - IF customer actually booked a car

This prevents confusion with "Why am I seeing prices for things I didn't book?"

## Technical Implementation

### Frontend (JavaScript)
- **State tracking**: `selectedServices` array tracks ['hotel', 'yacht', 'car'] based on user selections
- **Modal system**: Custom modal shows upsell questions
- **Dynamic sections**: Form sections show/hide based on `selectedServices`
- **No page reloads**: Entire experience happens on one form

### Form Data
The unified form sends `selected_services` to the AJAX handler:
```javascript
formData.set('selected_services', selectedServices.join(','));
// Results in: "hotel,car,yacht" or just "hotel" etc
```

### AJAX Handler Updates
The `bntm_ajax_bm_submit_booking()` function:
1. Reads `selected_services` parameter
2. **Only validates required fields for selected services**
   - If 'hotel' not in selected: ignore room validation
   - If 'yacht' not in selected: ignore yacht date validation
   - If 'car' not in selected: ignore car date validation
3. **Only inserts records for selected services**
   - Only inserts rows in `bm_rooms` if hotel was selected
   - Only inserts rows in `bm_yacht_rentals` if yacht was selected
   - Only inserts rows in `bm_car_rentals` if car was selected

### Database Records
- **One booking record**: `bm_bookings` - contains customer info and overall totals
- **Related records**: Only created for selected services
  - No empty yacht_rentals if yacht wasn't booked
  - No empty car_rentals if car wasn't booked
  - No empty rooms if hotel wasn't booked

### Quotation Display
The `bntm_shortcode_bm_view_quotation()` function:
```php
<?php if (!empty($rooms)): ?>
    <div class="bm-quotation-section">
        <h3>Hotel Accommodation</h3>
        <!-- Hotel details only shown if rooms exist -->
    </div>
<?php endif; ?>

<?php if ($yacht): ?>
    <div class="bm-quotation-section">
        <h3>Yacht Rental</h3>
        <!-- Yacht details only shown if yacht rental exists -->
    </div>
<?php endif; ?>

<?php if ($car): ?>
    <div class="bm-quotation-section">
        <h3>Car Rental</h3>
        <!-- Car details only shown if car rental exists -->
    </div>
<?php endif; ?>
```

## Shortcode Usage

### Main Booking Page
Use the hotel booking shortcode:
```
[bm_book_hotel]
```

The unified form handles all three service types internally.

### Car & Yacht Redirects
The old car and yacht shortcodes now redirect to the unified form:
- `[bm_book_car]` → redirects with `?bm_start=car`
- `[bm_book_yacht]` → redirects with `?bm_start=yacht`

*Future enhancement: Use the `bm_start` parameter to pre-select the initial service*

## Configuration

No new settings needed! The system uses existing settings:
- `bm_hotel_name` - Hotel name
- `bm_deposit_percentage` - Deposit percentage (default 30%)
- `bm_paymaya_*` - PayMaya integration settings

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

- [ ] **Pricing Calculation**
  - Verify totals match selected services only
  - Verify deposit percentage applies correctly

- [ ] **Email Quotation**
  - Verify email shows only booked services
  - Verify email totals are correct

## Styling

### Color Scheme
- **Primary Gradient**: #667eea to #764ba2 (purple/blue)
- **Service Cards**: Light gradient with hover effects
- **Buttons**: Full gradient with shadow effects
- **Summary**: Light gradient background with dark text

### Responsive
- Mobile-friendly form layout
- Service cards adapt to screen size
- Summary section optimized for small screens

## Browser Support
- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Known Limitations
- JavaScript must be enabled for sequential prompts
- Form requires valid dates before submission
- No save-and-return feature (one-session only)

## Future Enhancements
1. **Save draft bookings** - Allow users to save progress
2. **Template quotations** - Pre-made upsell suggestions
3. **Bundle discounts** - Automatic discounts for 2+ services
4. **Availability calendar** - Real-time availability checking
5. **Multi-language support** - Localized form text
