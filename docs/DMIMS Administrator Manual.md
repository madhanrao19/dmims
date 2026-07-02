# **DMIMS Administrator Manual**

## **Datamation Inventory Management System (DMIMS)**

**Version:** 1.0

---

# **Document Purpose**

This manual provides complete operational guidance for administrators responsible for managing DMIMS.

It explains how to perform routine administration tasks, manage customer companies, configure the platform, and maintain day-to-day operations.

This manual is intended for:

* Datamation Super Administrators  
* Datamation Management  
* Customer Company Administrators

---

# **1\. Administrator Roles**

## **Datamation Super Admin**

Full platform administrator.

Responsibilities:

* Customer onboarding  
* User management  
* Subscription management  
* License management  
* Billing  
* Reports  
* Audit review  
* System settings

Has unrestricted access.

---

## **Datamation Management**

Read-only administration.

Can view:

* Reports  
* Analytics  
* Billing summaries  
* Customer summaries  
* License summaries

Cannot modify operational data.

---

## **Customer Company Administrator**

Responsible only for their own company.

Can manage:

* Company users  
* Inventory  
* Documents  
* Locations  
* Reports  
* Barcode operations

Cannot access platform configuration.

---

# **2\. Logging In**

Navigate to

https://dmims.datamationgroup.com/login

Enter:

Email

Password

If Multi-Factor Authentication (future) is enabled, complete verification.

---

# **3\. Dashboard**

After login, the dashboard displays:

* Notifications  
* Summary cards  
* Recent activities  
* Alerts  
* Quick actions

Dashboard content depends on the logged-in role.

---

# **4\. Customer Management**

Available only to Datamation Super Admin.

Functions:

* Create customer  
* Edit customer  
* Suspend customer  
* Reactivate customer  
* Archive customer  
* View customer details

---

## **Create Customer**

Required fields:

Company Name

Company Code

Status

Optional:

Registration Number

Tax Number

Contact Person

Email

Phone

Address

Notes

Click:

Save

The customer becomes available immediately.

---

# **5\. User Management**

Create users

Edit users

Assign roles

Reset passwords

Deactivate users

Unlock users

Every customer user must belong to exactly one customer.

---

## **Password Reset**

Open User

↓

Reset Password

↓

Enter new password

↓

Save

User will use the new password immediately.

---

# **6\. Role Assignment**

Available roles:

Company Admin

Company Supervisor

Stock Inventory User

Document Tracking User

Viewer

Assign only the minimum permissions required.

---

# **7\. Module Management**

Datamation Super Admin may enable or disable modules per customer.

Example:

Enable

Inventory

Documents

Barcode

Disable

Billing View

Backup

Changes take effect immediately.

---

# **8\. Subscription Management**

Functions:

* Assign plan  
* Renew subscription  
* Update limits  
* Modify modules  
* Change expiry

Renewal process:

Open customer

↓

Subscription

↓

Renew

↓

Select plan

↓

Save

Subscription log is created automatically.

---

# **9\. License Management**

Functions:

* Activate  
* Suspend  
* Revoke  
* Renew

License determines technical access.

Subscription alone does not grant access.

---

## **Suspend License**

Open customer

↓

License

↓

Suspend

↓

Enter reason

↓

Confirm

Customer immediately enters View Only mode.

---

# **10\. Billing Management**

Functions:

* Create invoice  
* Update payment  
* View outstanding balance  
* Export reports

Payments are entered manually.

No payment gateway is integrated in Version 1\.

---

## **Record Payment**

Open billing record

↓

Update Payment

↓

Enter amount

↓

Enter reference

↓

Select payment method

↓

Save

Balances are recalculated automatically.

---

# **11\. Inventory Administration**

Customer administrators manage:

Categories

Products

Locations

Inventory

Movements

Reports

---

## **Create Product**

Required:

SKU

Product Name

Category

Location

Barcode (optional)

Status

Click Save.

---

# **12\. Document Administration**

Functions:

* Create box  
* Create file  
* Transfer  
* Move out  
* Return  
* Archive

Monitor overdue returns regularly.

---

# **13\. Shared Locations**

Locations are shared between:

Inventory

Documents

Never create duplicate locations.

Maintain a clean hierarchy.

---

# **14\. Barcode Administration**

Functions:

* Generate barcode  
* Print barcode  
* Register barcode  
* Scan barcode  
* View barcode history

Verify barcode uniqueness before printing replacements.

---

# **15\. Reports**

Available reports depend on role.

Examples:

Inventory Summary

Low Stock

Stock Movement

Document Reports

Billing Reports

Audit Reports

Reports may be exported as:

CSV

Excel

PDF

---

# **16\. Notifications**

Administrators receive notifications for:

* Low stock  
* Expiring subscriptions  
* Expiring licenses  
* Billing overdue  
* Overdue document returns  
* Import failures  
* Export completion

Review notifications daily.

---

# **17\. Audit Logs**

Audit logs record:

* Login  
* User changes  
* Customer changes  
* Billing changes  
* Inventory movements  
* Document movements  
* Barcode activity

Audit records cannot be modified.

---

# **18\. System Settings**

Platform settings include:

* General configuration  
* Barcode settings  
* Notification settings  
* Report settings  
* Security settings

Only Datamation Super Admin may modify platform settings.

---

# **19\. Backup Verification**

Administrators should verify:

* Daily backup completed  
* Backup size appears correct  
* Restore tests completed according to schedule

Backup verification is an operational responsibility even when backups are automated.

---

# **20\. Routine Daily Tasks**

Recommended daily checklist:

✓ Review notifications

✓ Check dashboard alerts

✓ Review overdue returns

✓ Monitor expiring subscriptions

✓ Monitor expiring licenses

✓ Review outstanding billing

✓ Verify backup status

---

# **21\. Weekly Tasks**

✓ Review audit logs

✓ Verify user accounts

✓ Check low stock reports

✓ Review system health

✓ Review failed imports

---

# **22\. Monthly Tasks**

✓ Review inactive users

✓ Review subscriptions

✓ Review licenses

✓ Verify backups

✓ Review reports

✓ Review storage usage

✓ Review documentation updates

---

# **23\. Common Administrative Tasks**

Examples:

* Create customer  
* Reset password  
* Add new warehouse location  
* Assign barcode  
* Renew subscription  
* Suspend license  
* Record payment  
* Export inventory report

Follow documented procedures for each task.

---

# **24\. Troubleshooting**

## **User Cannot Login**

Check:

* User status  
* Company status  
* Subscription  
* License  
* Password

---

## **Barcode Not Found**

Check:

* Barcode registry  
* Customer ownership  
* Barcode status

---

## **Product Not Visible**

Check:

* Customer assignment  
* Permissions  
* Module enabled  
* Filters

---

## **Reports Empty**

Check:

* Filters  
* Date range  
* Customer selection  
* Permissions

---

# **25\. Security Best Practices**

Administrators should:

* Use strong passwords  
* Lock inactive accounts  
* Review permissions regularly  
* Avoid sharing accounts  
* Log out after use  
* Review audit logs  
* Report suspicious activity immediately

---

# **26\. Administrator Checklist**

Before ending each week:

✓ New users reviewed

✓ Old users disabled

✓ Backups verified

✓ Notifications reviewed

✓ Audit logs checked

✓ Billing reviewed

✓ Inventory exceptions reviewed

✓ Document exceptions reviewed

---

# **27\. Frequently Asked Questions**

### **Can a customer see another customer's data?**

No.

---

### **Can audit logs be edited?**

Never.

---

### **Can movement history be changed?**

No.

Corrections create new movement records.

---

### **Why can't a suspended customer create new records?**

Because the license places the customer into View Only mode.

---

### **Why is billing manual?**

Version 1 is designed for manual billing and payment processing.

---

# **28\. Summary**

The Administrator Manual provides all procedures required to operate DMIMS safely and consistently.

Following this manual helps maintain:

* Data integrity  
* Customer isolation  
* Operational consistency  
* Security  
* Compliance  
* Reliable day-to-day administration

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Administrator Manual |

