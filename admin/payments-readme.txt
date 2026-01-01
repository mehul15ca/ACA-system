ACA System – Payments Placeholder Layer
======================================

What this does:
- Provides a student-facing "Pay Invoice" page at: student/pay-now.php?id=INVOICE_ID
- Does NOT integrate with any real payment gateway yet.
- Lets you show a "Pay Now" button now, without deciding Stripe/Square/PayPal.
- Prepares placeholder webhook files for future integration.

Files included:
- student/pay-now.php
- webhooks/stripe-placeholder.php
- webhooks/square-placeholder.php
- webhooks/paypal-placeholder.php

How to wire the Pay Now button (student side):
----------------------------------------------
1. Open: student/my-fees.php

2. Inside the table where invoices are shown, add one more column "Pay".
   For each invoice row, add something like:

   <td>
     <?php if ($inv['status'] === 'unpaid'): ?>
        <a href="pay-now.php?id=<?php echo $inv['id']; ?>" class="pay-link">Pay Now</a>
     <?php else: ?>
        -
     <?php endif; ?>
   </td>

3. Optionally style .pay-link as a button.

What student sees:
------------------
- Invoice summary (amount, due date, period, status)
- Message: "Online payment is not enabled yet..."
- Back to My Fees button.

Later, when gateway is chosen:
------------------------------
- Replace the contents of student/pay-now.php to:
  - Create a Stripe/Square/PayPal session
  - Redirect to hosted checkout
- Implement the matching webhook in the /webhooks/*.php files to:
  - Find invoice by reference
  - Mark as paid
  - Insert row into fees_payments
