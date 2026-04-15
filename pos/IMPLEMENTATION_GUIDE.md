# POS System - Enhanced Features Implementation Guide

## ✅ Features Implemented

### 1. **Customer Management in POS** (Enhanced)
- **Existing Features Utilized:**
  - Search for existing customers from CRM database
  - Add new customers with name, email, and contact number
  - Automatic saving to CRM when transaction is completed
  - Seamless integration between POS and CRM

### 2. **Payment Options** (Enhanced)
- **Pay Now Option:**
  - Choose between Cash or GCash
  - For Cash: Enter amount received using numpad or denomination buttons
  - Automatic change calculation
  - For GCash: Exact amount charged

- **Pay Later Option:**
  - Mark transaction as receivable/unpaid
  - Customer and amount automatically added to receivables tracking
  - Payment status set to 'unpaid' with payable_amount recorded

### 3. **Receivables Management Module** ✨ NEW
**Shortcode:** `[pos_receivables]`

Features:
- View all customers with outstanding balances
- Display total outstanding amount per customer
- Show transaction count and last transaction date
- One-click access to customer statement
- Send statement emails directly to customers

**Database Table:** `pos_transactions` with fields:
- `payment_status`: 'paid' or 'unpaid'
- `payable_amount`: Outstanding balance
- `customer_id`: Link to CRM customer
- `customer_email`: Email for notifications

### 4. **Customer Statement of Account** ✨ NEW
**Shortcode:** `[pos_customer_statement]` (shown in receivables view)

Features:
- View complete transaction history per customer
- Filter by month
- Display customer info, email, and contact
- Show payment status for each transaction
- Calculate total paid vs unpaid amounts
- Professional formatted statement with summary boxes

**Parameters:**
- `customer_id`: Customer ID to view
- `month`: Optional month filter (format: YYYY-MM)

### 5. **Email Settings Configuration** ✨ NEW
**Shortcode:** `[pos_settings]`

Features:
- Set sender name for statement emails
- Configure sender email address
- Enable/disable statement email feature
- Settings stored per business in `pos_email_settings` table
- Easy-to-use settings page

**Database Table:** `pos_email_settings`
- `sender_name`: Name appearing in email "From" field
- `sender_email`: Email address used to send statements
- `enable_statement_email`: Toggle feature on/off

### 6. **Email Sending Functionality** ✨ NEW
**AJAX Action:** `pos_send_statement_email`

Features:
- Send statement of account via email to customer
- HTML formatted email with transaction details
- Includes outstanding balance summary
- Transaction table showing all unpaid invoices
- Available as button in receivables view
- Works with any validated SMTP configuration

**Email Includes:**
- Greeting with customer name
- Outstanding balance amount
- Detailed transaction table (Transaction #, Date, Amount, Balance)
- Professional formatting with instructions

---

## 🎯 How to Use

### Setting Up Email
1. Go to POS Settings page: `[pos_settings]`
2. Enter your email sender name (e.g., "Company Name")
3. Set the sender email address
4. Check "Enable Statement Email" checkbox
5. Save settings

### Managing Receivables
1. Navigate to Accounts Receivable: `[pos_receivables]`
2. You'll see all customers with outstanding balances
3. For each customer:
   - Click "View Statement" to see transaction history
   - Click "Send Email" to email their statement
   - Email address must be on file

### Viewing Customer Statements
1. From receivables view, click "View Statement" on any customer
2. Or direct URL: `[pos_customer_statement]?customer_id=X`
3. Use the month filter to view specific periods
4. See total paid, outstanding balance, and transaction count

### Processing Sales
**Pay Now (Already existed - still works):**
1. Search/Add customer
2. Add products to cart
3. Select "Pay Now" → Choose "Cash" or "GCash"
4. If Cash: Enter amount received → System calculates change
5. If GCash: Exact amount charged
6. Complete sale

**Pay Later (Enhanced for better UX):**
1. Search/Add customer
2. Add products to cart
3. Select "Pay Later" from payment timing dropdown
4. Payment method field automatically hides
5. System marks as receivable
6. Customer appears in Accounts Receivable
7. Send statement via email when needed

---

## 📊 Database Schema

### New Table: `pos_email_settings`
```sql
CREATE TABLE wp_pos_email_settings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    business_id BIGINT UNSIGNED,
    sender_name VARCHAR(255),
    sender_email VARCHAR(255),
    smtp_enabled BOOLEAN,
    smtp_host VARCHAR(255),
    smtp_port INT,
    smtp_username VARCHAR(255),
    smtp_password VARCHAR(255),
    enable_statement_email BOOLEAN,
    statement_email_template LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

### Enhanced Table: `pos_transactions`
Existing fields utilized:
- `customer_id`: Links to CRM customer
- `customer_name`: Customer name
- `customer_email`: Customer email for notifications
- `customer_contact`: Customer contact number
- `payment_type`: 'pay_now' or 'pay_later'
- `payment_method`: 'cash', 'gcash', or 'pay_later'
- `payment_status`: 'paid' or 'unpaid'
- `payable_amount`: Outstanding balance (0 if paid)
- `paid_amount`: Amount actually paid

---

## 🔧 Technical Details

### New Shortcodes Added
```php
'pos_receivables' => 'bntm_pos_shortcode_receivables',
'pos_customer_statement' => 'bntm_pos_shortcode_customer_statement',
'pos_settings' => 'bntm_pos_shortcode_settings'
```

### New AJAX Handlers
- `wp_ajax_pos_send_statement_email`: Sends statement email to customer
- Authentication: Logged-in users only
- Nonce verification included

### Updated Functions
- `syncPaymentModalState()`: Improved to hide payment method when "Pay Later" selected
- `pos_find_or_create_customer()`: Already integrated and working
- Payment processing: Already handles pay_later logic

---

## 📝 Creating Pages

Create these pages in WordPress and add the shortcodes:

1. **Accounts Receivable** → Add shortcode: `[pos_receivables]`
2. **Settings** → Add shortcode: `[pos_settings]`
3. **Customer Statement** (optional, referenced from receivables) → Add shortcode: `[pos_customer_statement]`

---

## 🚀 Workflow Example

1. **Day 1 - Sale on Credit:**
   - Customer "Juan Dela Cruz" purchases items for ₱5,000
   - Select "Pay Later" → Sale processed as receivable
   - Juan appears in Receivables with ₱5,000 outstanding

2. **Day 3 - Send Statement:**
   - Manager views Receivables
   - Clicks "Send Email" for Juan
   - Statement sent to juan@email.com with:
     - Outstanding balance: ₱5,000
     - Transaction details
     - Payment request

3. **Day 7 - Payment:**
   - Juan pays ₱5,000 in cash
   - Manager processes Payment from POS (Pay Now)
   - Juan's balance now shows as ₱0 in Receivables

---

## ⚙️ Email Configuration Notes

- **SMTP:** Uses WordPress default mail configuration
- **Fallback:** If SMTP not configured, uses PHP mail()
- **HTML Format:** All emails formatted as professional HTML tables
- **Customization:** Email template can be stored in `statement_email_template` field for future customization

---

## 🐛 Troubleshooting

### Emails Not Sending
1. Check WordPress Settings → General for email address
2. Verify your server's mail configuration
3. Check email logs in WordPress/PHP error logs
4. Ensure customer email is saved in transaction

### Customer Not Showing in Receivables
- Customer must have `payment_status = 'unpaid'` and `payable_amount > 0`
- Check if "Pay Later" was selected
- Verify transaction was completed successfully

### Email Appears in Spam
- Update sender email in POS Settings
- Ask customers to add to contacts
- Consider using SMTP with proper authentication

---

## 📚 Files Modified

- `/modules/pos/main.php`
  - Added 3 new shortcodes
  - Added email settings table
  - Added email AJAX handler
  - Added receivables view
  - Added statement view
  - Updated payment modal JavaScript
  - Added CSS styling

---

## ✨ Future Enhancements (Optional)

- Email template customization
- SMTP configuration in settings
- Bulk email sending
- Payment reminders (automated)
- SMS integrations
- Payment history reports
- Late payment tracking

---

**Implementation Date:** April 2026
**Status:** ✅ Complete and Ready to Use
