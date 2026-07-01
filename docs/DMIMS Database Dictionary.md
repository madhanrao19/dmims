# **DMIMS Database Dictionary**

**Datamation Inventory Management System (DMIMS)**

Version 1.0

---

# **Document Purpose**

This document describes every database table used by DMIMS.

For every table it defines:

* Purpose  
* Business ownership  
* Relationships  
* Fields  
* Data types  
* Constraints  
* Indexes  
* Validation rules  
* Business rules  
* Example data

This document should always be updated whenever the database schema changes.

---

# **Database Design Principles**

DMIMS follows these principles:

* Multi-tenant architecture  
* Customer isolation  
* Immutable movement history  
* Soft deletes for master data  
* Foreign key integrity  
* Audit-first design  
* Subscription and license separation

---

# **Database Overview**

## **Core Tables**

customers

users

roles

permissions

model\_has\_roles

model\_has\_permissions

modules

customer\_modules

subscription\_plans

customer\_subscriptions

subscription\_logs

licenses

license\_logs

billing\_records

billing\_payments

billing\_logs

settings

audit\_logs

notifications

---

## **Inventory Tables**

location\_types

locations

categories

products

product\_location\_stocks

stock\_movements

stock\_alerts

---

## **Document Tracking Tables**

document\_types

boxes

document\_files

document\_movement\_logs

---

## **Barcode Tables**

barcode\_registry

barcode\_scan\_logs

---

# **CUSTOMER TABLE**

## **Table Name**

customers

---

## **Purpose**

Stores every customer company using DMIMS.

Every company is completely isolated from every other company.

---

## **Primary Key**

id

---

## **Soft Delete**

Yes

---

## **Fields**

| Column | Type | Nullable | Description |
| ----- | ----- | ----- | ----- |
| id | bigint | No | Primary Key |
| company\_name | string | No | Customer company name |
| company\_code | string | No | Unique company code |
| registration\_no | string | Yes | Company registration number |
| tin\_no | string | Yes | Tax identification number |
| contact\_person | string | Yes | Primary contact |
| email | string | Yes | Contact email |
| phone | string | Yes | Contact phone |
| address | text | Yes | Company address |
| status | enum | No | Company status |
| notes | text | Yes | Internal notes |
| created\_by | bigint | Yes | User ID |
| updated\_by | bigint | Yes | User ID |
| created\_at | timestamp | No | Creation date |
| updated\_at | timestamp | No | Last update |
| deleted\_at | timestamp | Yes | Soft delete |

---

## **Relationships**

Customer

hasMany Users

hasMany Products

hasMany Categories

hasMany Locations

hasMany Boxes

hasMany Document Files

hasMany Billing Records

hasMany Licenses

hasMany Subscriptions

hasMany Notifications

---

## **Indexes**

Primary Key

company\_code (Unique)

status

created\_at

---

## **Business Rules**

Company code must be unique.

Archived companies cannot perform operations.

Suspended companies cannot access operational functions.

Customer records must never be hard deleted.

---

## **Example**

ID

15

Company

ABC Manufacturing

Company Code

ABC001

Status

Active

\====================================================

# **USERS TABLE**

---

## **Table Name**

users

---

## **Purpose**

Stores all internal Datamation users and customer users.

---

## **Soft Delete**

Yes

---

## **Fields**

| Column | Type | Nullable | Description |
| ----- | ----- | ----- | ----- |
| id | bigint | No | Primary Key |
| customer\_id | bigint | Yes | Owning company |
| name | string | No | User name |
| email | string | No | Login email |
| username | string | Yes | Optional username |
| employee\_id | string | Yes | Employee identifier |
| phone | string | Yes | Contact number |
| job\_title | string | Yes | Job title |
| password | string | No | Hashed password |
| status | enum | No | User status |
| is\_platform\_user | boolean | No | Datamation user flag |
| last\_login\_at | datetime | Yes | Last login |
| created\_by | bigint | Yes | User ID |
| updated\_by | bigint | Yes | User ID |
| deleted\_at | timestamp | Yes | Soft delete |

---

## **Relationships**

belongsTo Customer

belongsToMany Roles

hasMany Audit Logs

hasMany Notifications

---

## **Business Rules**

Internal users

customer\_id \= NULL

is\_platform\_user \= true

Company users

customer\_id \= Company ID

is\_platform\_user \= false

Email must be unique.

Inactive users cannot log in.

---

## **Indexes**

email (Unique)

customer\_id

status

last\_login\_at

\====================================================

# **MODULES TABLE**

---

## **Purpose**

Stores all available system modules.

---

## **Example Modules**

Stock Inventory

Document Tracking

Barcode

Reports

Audit

Import Export

Backup Restore

Billing View

---

## **Relationships**

hasMany Customer Modules

---

## **Business Rules**

Module names are unique.

Inactive modules cannot be assigned.

\====================================================

# **CUSTOMER\_MODULES TABLE**

---

## **Purpose**

Enables or disables modules for each customer.

---

## **Composite Unique Key**

customer\_id

module\_id

---

## **Business Rules**

One module assignment per customer.

Disabling a module immediately prevents access.

\====================================================

# **SUBSCRIPTION\_PLANS TABLE**

---

## **Purpose**

Defines reusable subscription plans.

---

## **Key Fields**

plan\_code

plan\_name

max\_users

max\_products

max\_document\_files

max\_boxes

enabled\_modules

allowed\_reports

billing\_cycle

price

status

---

## **Relationships**

hasMany Customer Subscriptions

---

## **Business Rules**

Plans are templates.

Customer subscriptions copy values from plans.

Inactive plans cannot be assigned.

\====================================================

# **CUSTOMER\_SUBSCRIPTIONS TABLE**

---

## **Purpose**

Stores the active subscription assigned to a customer.

---

## **Relationships**

belongsTo Customer

belongsTo Subscription Plan

---

## **Business Rules**

Controls:

Users

Products

Boxes

Files

Modules

Billing Cycle

Grace Period

Does NOT control final technical access.

That responsibility belongs to the License.

\====================================================

# **LICENSES TABLE**

---

## **Purpose**

Controls technical access to DMIMS.

---

## **Technical Access Modes**

Full Access

View Only

Blocked

---

## **Status Mapping**

Active

↓

Full Access

Suspended

↓

View Only

Expired

↓

View Only

Revoked

↓

Blocked

---

## **Business Rules**

Every customer should have one active license.

License overrides subscription access when necessary.

\====================================================

# **BILLING\_RECORDS TABLE**

---

## **Purpose**

Stores invoices and billing records.

---

## **Relationships**

belongsTo Customer

belongsTo Subscription

belongsTo License

hasMany Payments

---

## **Business Rules**

Manual billing only.

No payment gateway.

Only Datamation Super Admin may modify billing.

\====================================================

# **BILLING\_PAYMENTS TABLE**

---

## **Purpose**

Stores manual payment entries.

---

## **Payment Methods**

Bank Transfer

Cash

Cheque

Online Transfer

Internal Adjustment

Waived

Other

---

## **Business Rules**

Payments update billing balances.

Payment history must remain immutable.

\====================================================

# **LOCATIONS TABLE**

---

## **Purpose**

Stores physical storage locations.

---

## **Relationships**

belongsTo Customer

belongsTo Parent Location

belongsTo Location Type

hasMany Product Stocks

hasMany Boxes

---

## **Business Rules**

Shared between Inventory and Document Tracking.

Never create external locations.

Use movement tables for external destinations.

\====================================================

# **PRODUCTS TABLE**

---

## **Purpose**

Stores inventory items.

---

## **Relationships**

belongsTo Customer

belongsTo Category

belongsTo Default Location

hasMany Stock Movements

---

## **Unique Constraints**

customer\_id \+ sku

customer\_id \+ barcode

---

## **Business Rules**

SKU unique per company.

Barcode unique per company.

\====================================================

# **PRODUCT\_LOCATION\_STOCKS TABLE**

---

## **Purpose**

Stores inventory balance per location.

---

## **Composite Unique Key**

customer\_id

product\_id

location\_id

\====================================================

# **STOCK\_MOVEMENTS TABLE**

---

## **Purpose**

Stores immutable inventory history.

---

## **Movement Types**

Opening Balance

Receive In

Stock Out

Internal Transfer

Adjustment

Return

Disposal

---

## **Business Rules**

Never delete.

Never edit.

Always use transactions.

\====================================================

# **BOXES TABLE**

---

## **Purpose**

Stores archive boxes.

---

## **Relationships**

belongsTo Customer

belongsTo Current Location

hasMany Document Files

---

## **Business Rules**

Moving a box must NOT update every file.

File location is derived from the box.

\====================================================

# **DOCUMENT\_FILES TABLE**

---

## **Purpose**

Stores physical document files.

---

## **Relationships**

belongsTo Customer

belongsTo Current Box

belongsTo Document Type

---

## **Business Rules**

Moving a file changes its current box.

Moving a box does not update every file.

\====================================================

# **DOCUMENT\_MOVEMENT\_LOGS TABLE**

---

## **Purpose**

Stores immutable movement history.

---

## **Actions**

Receive File

Receive Box

Transfer File

Transfer Box

Move Out File

Move Out Box

Return File

Return Box

Correction

---

## **Business Rules**

Never modify.

Corrections are separate movement records.

\====================================================

# **BARCODE\_REGISTRY TABLE**

---

## **Purpose**

Central lookup table for every barcode.

---

## **Barcode Types**

Product

Location

Box

Document File

---

## **Business Rules**

Barcode unique within customer.

Stores print count.

Stores last scanned timestamp.

\====================================================

# **BARCODE\_SCAN\_LOGS TABLE**

---

## **Purpose**

Stores every barcode scan.

---

## **Scan Results**

Found

Unknown

Inactive

Permission Denied

\====================================================

# **AUDIT\_LOGS TABLE**

---

## **Purpose**

Stores complete system audit trail.

---

## **Business Rules**

Immutable.

Never edited.

Never deleted.

Generated only through AuditService.

\====================================================

# **NOTIFICATIONS TABLE**

---

## **Purpose**

Stores user and system notifications.

---

## **Business Rules**

Customer notifications stay inside customer boundary.

Platform notifications are visible only to Datamation users.

\====================================================

# **SETTINGS TABLE**

---

## **Purpose**

Stores platform-wide and customer-specific settings.

---

## **Rules**

customer\_id \= NULL

Platform setting

customer\_id filled

Customer-specific setting

\====================================================

# **Foreign Key Standards**

Every foreign key must use constrained() where possible.

Deletes should follow:

Master Data

Restrict

History Tables

No cascade delete

Movement Logs

Never cascade delete

\====================================================

# **Index Standards**

Every table should index:

customer\_id

status

created\_at

updated\_at

Foreign keys

Frequently searched fields

\====================================================

# **Database Naming Standards**

Tables

Plural snake\_case

Columns

snake\_case

Foreign Keys

singular\_id

Boolean

is\_

Dates

\_at suffix

\====================================================

# **Database Design Principles Summary**

The DMIMS database is designed to provide:

* Complete customer isolation  
* High performance  
* Referential integrity  
* Immutable history  
* Scalable architecture  
* Secure access control  
* Efficient reporting  
* Future expansion without redesign

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Database Dictionary |

