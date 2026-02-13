# Sequential Booking System - Quick Start Guide

## For Customers

### Booking Process

1. **Visit the Booking Page** 
   - Go to the "[bm_book_hotel]" page
   - See three service options: Hotel, Car, Yacht

2. **Choose Your First Service**
   - Click on Hotel, Car, or Yacht
   - The booking form will appear

3. **Fill Your First Service Details**
   - Enter your customer information (name, email, phone)
   - Fill in details for your selected service:
     - **Hotel:** Check-in/out dates, number of adults/children, select rooms
     - **Car:** Pickup/return dates, select car type, choose with/without driver
     - **Yacht:** Rental date, duration (hours/days), number of guests

4. **Answer Upsell Questions**
   - A modal will ask if you want to add the next service
   - Example: "Would you like to rent a car for your trip?"
   - Click **Yes, Add It** or **No, Continue**
   - Each service gets offered once

5. **Review Your Booking**
   - See the total amount due
   - See the deposit required
   - Only your selected services are shown

6. **Submit and Get Quotation**
   - Click "Submit Booking Request"
   - Redirected to your personalized quotation
   - Email sent with full details

---

## For Administrators

### Shortcodes

Use one shortcode for all booking types:
```
[bm_book_hotel]
```

This single shortcode handles:
- Service selection
- Hotel bookings
- Car bookings
- Yacht bookings
- Intelligent upselling

### Monitoring Bookings

Check the Dashboard → Bookings tab to see:
- Which services were booked in each booking
- Data only appears for actually selected services
- No empty records for unselected services

### Database Records

**Booking record always created:**
- `bm_bookings` table
- Contains customer info and totals

**Service-specific records (only if selected):**
- `bm_rooms` - Only if hotel was booked
- `bm_car_rentals` - Only if car was booked
- `bm_yacht_rentals` - Only if yacht was booked

### Quotation Emails

Emails are sent with ONLY the booked services:
- Hotel details if hotel was booked
- Car details if car was booked
- Yacht details if yacht was booked
- Prices reflect only booked items

---

## Common Scenarios

### Scenario 1: Customer Books Only Hotel
1. Selects "Hotel Room"
2. Fills hotel dates and room selection
3. Asked "Would you like to rent a car?" → Says No
4. Asked "Would you like to cruise the island?" → Says No
5. **Quotation shows:** Hotel only (no car, no yacht sections)

### Scenario 2: Customer Starts with Car, Adds Hotel
1. Selects "Car Rental"
2. Fills car dates and car selection
3. Asked "Need a place to stay?" → Says Yes
4. Fills hotel dates and room selection
5. Asked "Would you like to cruise the island?" → Says No
6. **Quotation shows:** Car + Hotel (no yacht section)

### Scenario 3: Customer Books All Three
1. Selects "Hotel Room"
2. Asked about car → Says Yes → Fills car details
3. Asked about yacht → Says Yes → Fills yacht details
4. **Quotation shows:** Hotel + Car + Yacht

### Scenario 4: Customer Changes Mind
1. Starts booking process
2. Clicks "← Back to Service Selection"
3. Starts over with a different service
4. Form clears completely

---

## Pricing Example

### Example 1: Hotel Only
- 2 nights × ₱5,000/night = ₱10,000
- **Total: ₱10,000**
- **Deposit (30%): ₱3,000**

### Example 2: Hotel + Car
- Hotel: 2 nights × ₱5,000/night = ₱10,000
- Car: 2 days × ₱1,000/day = ₱2,000
- **Total: ₱12,000**
- **Deposit (30%): ₱3,600**

### Example 3: All Services
- Hotel: 2 nights × ₱5,000/night = ₱10,000
- Car: 2 days × ₱1,000/day = ₱2,000
- Yacht: 4 hours × ₱500/hour = ₱2,000
- **Total: ₱14,000**
- **Deposit (30%): ₱4,200**

---

## Form Validation Rules

### Always Required:
- Customer name
- Customer email
- Customer phone

### Hotel (if selected):
- Check-in date
- Check-out date
- At least one room must be selected

### Car (if selected):
- Car type
- Pickup date
- Return date

### Yacht (if selected):
- Yacht type
- Rental date
- Duration (hours or days)

**Important:** Only the selected services require validation!

---

## Mobile Experience

The booking system is fully responsive:
- Service cards stack on mobile
- Form is optimized for touch
- Modals display properly on small screens
- All buttons are easily tappable

---

## Troubleshooting

### "Why am I seeing an empty section in the quotation?"
This shouldn't happen. If you see empty sections, check that:
1. The service was actually selected
2. The form was filled out completely
3. The booking was submitted successfully

### "My total seems wrong"
Check:
1. Verify all selected services are shown in the quotation
2. Check the number of nights/days used in calculation
3. Verify room quantities and car/yacht quantities

### "I didn't intend to book that service"
If you added a service by mistake:
1. Click "Back to Service Selection"
2. Start over with just the services you want
3. The form will reset

### "The form won't let me submit"
Check:
1. All required fields are filled (red asterisks *)
2. Dates are valid (return/checkout after pickup/checkin)
3. At least one room is selected (for hotel bookings)

---

## Key Differences from Old System

| Feature | Old System | New System |
|---------|-----------|-----------|
| **Start** | Separate pages | Single unified page |
| **Selection** | No choice | Choose first service |
| **Upsells** | None | Smart modals ask for add-ons |
| **Form** | Always full | Dynamic based on selections |
| **Validation** | All fields required | Only selected services required |
| **Database** | Empty records | Clean - only booked services |
| **Quotation** | All services shown | Only booked services |

---

## Performance

The system is optimized for:
- Single page load (no page reloads between steps)
- Minimal database queries
- Real-time price calculation
- Smooth animations and transitions
- Mobile-friendly interactions

---

## Support Notes

### For Hotel/Travel Agency Staff:
1. Inform customers they can start with any service
2. Explain the upsell process is optional
3. Emphasize only booked items appear on quotation
4. Show customers how prices are calculated in real-time

### For IT/Web Team:
1. No database migrations needed
2. Existing booking data remains unchanged
3. New bookings use sequential flow automatically
4. All customizations can be made via CSS in styles section

---

## Frequently Asked Questions

**Q: Do customers have to book all three services?**
A: No! Customers can book just one service or any combination.

**Q: What if a customer declines all add-ons?**
A: They proceed with just their first selected service.

**Q: Are the upsell modals mandatory?**
A: No. Customers can always click "No, Continue" to skip.

**Q: Can customers go back and change their selections?**
A: Yes! The "Back to Service Selection" button lets them start over.

**Q: Does the order matter?**
A: The system intelligently sequences suggestions based on the first service chosen.

**Q: Are prices calculated correctly?**
A: Yes! Prices only include selected services, calculated in real-time.

**Q: Is this mobile-friendly?**
A: Yes! Fully responsive design for all devices.

---

## Contact for Issues

If you encounter any issues:
1. Check the browser console for JavaScript errors
2. Verify all services (hotel, cars, yachts) are properly configured
3. Test with different service combinations
4. Check the existing booking system documentation

---

*Last Updated: January 20, 2026*
