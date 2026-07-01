# **DMIMS Developer Getting Started Guide**

**Datamation Inventory Management System (DMIMS)**

**Version:** 1.0  
**Project Status:** Production Development  
**Technology Stack:** Laravel 13 \+ Filament 5 \+ MariaDB (MySQL-compatible) \+ Vite \+ PHP 8.4+

---

# **Document Purpose**

This document helps new developers understand, install, configure, and contribute to the Datamation Inventory Management System (DMIMS).

It should be the **first document** every developer reads before starting development.

Related documents:

1. DMIMS Developer Blueprint  
2. Database Schema & Migration Guide  
3. Developer Implementation Checklist

---

# **1\. Project Overview**

## **System Name**

Datamation Inventory Management System

**Short Name:** DMIMS

## **Purpose**

DMIMS is a multi-tenant inventory and document tracking platform built for Datamation.

The system enables Datamation to manage multiple customer companies from a single platform while ensuring complete data isolation between customers.

Major modules include:

* Customer Management  
* User Management  
* Stock Inventory  
* Document Tracking  
* Shared Location Management  
* Barcode Registry  
* Barcode Scanning  
* Barcode Printing  
* Subscription Management  
* License Management  
* Manual Billing  
* Reports  
* Audit Logs  
* Notifications  
* Progressive Web App (PWA)

---

# **2\. Technology Stack**

## **Backend**

Laravel 13

## **Admin Framework**

Filament 5

## **Programming Language**

PHP 8.4+

## **Database**

MariaDB 11 (MySQL 8 compatible)

## **Authentication**

Laravel Authentication

Filament Authentication

Spatie Laravel Permission

## **Frontend**

Blade

Tailwind CSS

Alpine.js

Vite

## **Queue**

Laravel Database Queue

## **Cache**

File Cache

Redis (future enhancement)

## **Barcode**

Code128

Future:

QR Code

## **Reports**

CSV

Excel

PDF

Print View

## **Server**

Ubuntu 24.04 LTS

Apache

PHP-FPM

Supervisor

Cron

Cloudflare Tunnel

---

# **3\. Development Principles**

Every developer must understand these rules before writing code.

## **Rule 1**

Every customer-owned record contains:

customer\_id

## **Rule 2**

Company users only see records belonging to their own customer.

## **Rule 3**

Never trust customer\_id submitted from the browser.

Always use the authenticated user's customer context.

## **Rule 4**

Business logic belongs inside Services.

Controllers should remain thin.

## **Rule 5**

Every important action must be audited.

## **Rule 6**

Movement logs are immutable.

Never edit historical movement records.

---

# **4\. Recommended Development Environment**

Operating System

Windows 11

Ubuntu 24.04 LTS

macOS

Recommended IDE

Visual Studio Code

Recommended Extensions

PHP Intelephense

Laravel Extension Pack

Laravel Blade Formatter

PHP Debug

GitLens

EditorConfig

Prettier

Laravel Pint

---

# **5\. Required Software**

Install the following:

Git

PHP 8.4+

Composer

Node.js 22 LTS

npm

MariaDB 11 (MySQL 8 compatible)

Visual Studio Code

---

# **6\. Clone the Project**

Clone the repository:

git clone https://github.com/madhanrao19/dmims.git

Enter the project folder:

cd dmims

---

# **7\. Install Dependencies**

Install PHP packages

composer install

Install frontend packages

npm install

---

# **8\. Environment Configuration**

Copy the example environment file.

cp .env.example .env

Generate the application key.

php artisan key:generate

Configure database settings.

Example:

DB\_CONNECTION=mysql  
DB\_HOST=127.0.0.1  
DB\_PORT=3306  
DB\_DATABASE=dmims  
DB\_USERNAME=root  
DB\_PASSWORD=password

---

# **9\. Database Setup**

Run migrations.

php artisan migrate

Seed default data.

php artisan db:seed

If rebuilding from scratch:

php artisan migrate:fresh \--seed

---

# **10\. Build Frontend Assets**

Development

npm run dev

Production

npm run build

---

# **11\. Run the Application**

php artisan serve

Open

http://127.0.0.1:8000

Filament Admin

http://127.0.0.1:8000/admin

---

# **12\. Project Folder Structure**

app/  
    Actions/  
    Console/  
    Events/  
    Exceptions/  
    Filament/  
    Http/  
    Jobs/  
    Mail/  
    Models/  
    Notifications/  
    Observers/  
    Policies/  
    Providers/  
    Services/  
    Traits/

bootstrap/

config/

database/  
    factories/  
    migrations/  
    seeders/

public/

resources/  
    css/  
    js/  
    views/

routes/

storage/

tests/

---

# **13\. Important Folders**

## **app/Models**

Database models.

---

## **app/Services**

Contains business logic.

Business rules should not be implemented inside controllers.

---

## **app/Filament**

Filament Resources

Pages

Widgets

Forms

Tables

---

## **database/migrations**

Database schema.

---

## **database/seeders**

Default roles

Permissions

Modules

Subscription plans

Demo users

---

## **resources/views**

Blade templates.

---

## **routes**

Application routes.

---

## **tests**

Unit Tests

Feature Tests

---

# **14\. Development Workflow**

Recommended order:

1. Pull latest code  
2. Create feature branch  
3. Implement feature  
4. Test locally  
5. Run code formatter  
6. Commit  
7. Push branch  
8. Create Pull Request  
9. Code Review  
10. Merge

---

# **15\. Git Branch Naming**

Feature

feature/customer-management

Bug Fix

bugfix/barcode-print

Hotfix

hotfix/login-error

---

# **16\. Coding Standards**

Follow PSR-12.

Use Laravel Pint.

Use strict typing where practical.

Keep controllers thin.

Use dependency injection.

Avoid duplicated logic.

Prefer Services for business logic.

Use transactions for inventory and document movements.

---

# **17\. Common Artisan Commands**

Generate model

php artisan make:model Product

Generate migration

php artisan make:migration

Generate Filament Resource

php artisan make:filament-resource Product

Generate Service

php artisan make:class Services/ProductService

Clear cache

php artisan optimize:clear

---

# **18\. Running Tests**

Run all tests

php artisan test

Run a specific test

php artisan test \--filter ProductTest

---

# **19\. Before Every Commit**

Confirm:

* Application runs  
* No PHP errors  
* No JavaScript errors  
* Migrations succeed  
* Seeder succeeds  
* Feature tested  
* No debug code left behind  
* No credentials committed

---

# **20\. Common Problems**

## **Composer Error**

composer install  
composer dump-autoload

---

## **Missing APP\_KEY**

php artisan key:generate

---

## **Permission Errors**

php artisan storage:link

Ensure storage permissions are correct.

---

## **Migration Error**

php artisan migrate:fresh \--seed

---

## **Frontend Assets Missing**

npm install

npm run dev

---

# **21\. Development Best Practices**

Always:

* Use database transactions for inventory updates.  
* Write audit logs for important actions.  
* Validate user input.  
* Check permissions before modifying data.  
* Respect customer isolation.  
* Reuse existing services whenever possible.  
* Keep methods focused and easy to understand.

Never:

* Hardcode customer IDs.  
* Trust browser-submitted customer IDs.  
* Bypass access control.  
* Delete movement history.  
* Store business logic in Blade templates.  
* Expose secrets or credentials.

---

# **22\. First Development Milestone**

The first successful milestone is achieved when:

* Datamation Super Admin can create Customer A.  
* Datamation Super Admin can create Customer B.  
* Customer A users only see Customer A data.  
* Customer B users only see Customer B data.  
* Company isolation is fully enforced.  
* Filament Resources load correctly.  
* Migrations and seeders complete without errors.

---

# **23\. Where to Go Next**

After completing this guide, developers should read the documents in the following order:

1. Developer Blueprint  
2. Database Schema & Migration Guide  
3. Developer Implementation Checklist  
4. Technical Design Document (TDD)  
5. System Architecture Document (SAD)  
6. Business Rules Document  
7. Database Dictionary

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Developer Getting Started Guide |

