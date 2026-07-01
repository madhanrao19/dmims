# **DMIMS Deployment, Operations & Disaster Recovery Guide**

**Datamation Inventory Management System (DMIMS)**

Version 1.0

---

# **Document Purpose**

This document defines the operational procedures for deploying, operating, monitoring, maintaining, backing up, restoring, and recovering the DMIMS production environment.

It is intended for:

* System Administrators  
* DevOps Engineers  
* IT Operations  
* Infrastructure Engineers  
* Support Engineers

---

# **1\. Production Architecture**

## **Logical Architecture**

                   Internet  
                        │  
                Cloudflare DNS  
                        │  
             Cloudflare Tunnel / HTTPS  
                        │  
                    Ubuntu 24.04  
                        │  
                     Nginx  
                        │  
                    PHP-FPM 8.3  
                        │  
                     Laravel  
                        │  
      ┌─────────────────┴──────────────────┐  
      │                                    │  
   MariaDB                          Storage  
      │                                    │  
      └────────────── Backups ─────────────┘

---

# **2\. Supported Platform**

Operating System

Ubuntu Server 24.04 LTS

Web Server

Nginx

Runtime

PHP 8.3+

Database

MariaDB 11.x (preferred)

or

MySQL 8

Process Manager

Supervisor

Queue

Laravel Database Queue

Cache

File Cache

Future

Redis

---

# **3\. Server Sizing**

## **Small Deployment**

Users

1–50

CPU

2 vCPU

RAM

4 GB

Disk

100 GB SSD

---

## **Medium Deployment**

Users

50–300

CPU

4 vCPU

RAM

8 GB

Disk

250 GB SSD

---

## **Large Deployment**

Users

300+

CPU

8+ vCPU

RAM

16–32 GB

Disk

500 GB+ SSD

---

# **4\. Directory Structure**

/var/www/dmims

├── app  
├── bootstrap  
├── config  
├── database  
├── public  
├── resources  
├── routes  
├── storage  
├── vendor  
└── .env

---

# **5\. Deployment Process**

Deployment order:

1. Provision server  
2. Install PHP  
3. Install MariaDB  
4. Install Composer  
5. Install Node.js  
6. Clone repository  
7. Install dependencies  
8. Configure .env  
9. Run migrations  
10. Seed database  
11. Build assets  
12. Configure Nginx  
13. Configure Supervisor  
14. Configure Scheduler  
15. Configure Cloudflare  
16. Verify health  
17. Go live

---

# **6\. Environment Variables**

Production requirements:

APP\_ENV=production  
APP\_DEBUG=false  
APP\_URL=https://dmims.datamationgroup.com

DB\_CONNECTION=mysql

QUEUE\_CONNECTION=database

SESSION\_DRIVER=database

CACHE\_STORE=file

LOG\_CHANNEL=stack

SESSION\_SECURE\_COOKIE=true

Never commit .env to version control.

---

# **7\. Database Deployment**

Deployment sequence:

Create database

↓

Create database user

↓

Grant least-privilege permissions

↓

Run migrations

↓

Run seeders

↓

Verify tables

↓

Create initial backup

---

# **8\. Queue Workers**

Use Supervisor.

Required worker:

php artisan queue:work \--tries=3

Automatically restart on failure.

Log worker output.

---

# **9\. Scheduler**

Cron entry:

\* \* \* \* \* php /var/www/dmims/artisan schedule:run \>\> /dev/null 2\>&1

Scheduler responsibilities:

* Notifications  
* Subscription reminders  
* License reminders  
* Cleanup  
* Future background jobs

---

# **10\. Nginx Configuration**

Requirements

* HTTPS only  
* HTTP → HTTPS redirect  
* PHP-FPM  
* Static asset caching  
* Security headers  
* File upload limits

Block access to:

* .env  
* /storage  
* /vendor  
* Hidden files

---

# **11\. SSL**

Supported:

Cloudflare Origin Certificate

Let's Encrypt

TLS 1.2+

Prefer TLS 1.3 where supported.

Automatically renew certificates.

---

# **12\. File Storage**

Store:

* Uploaded files  
* Barcode templates  
* Import files  
* Export files  
* Logs  
* Backups (temporary)

Recommended:

Separate backup storage volume.

---

# **13\. Logging**

Laravel logs

Nginx logs

PHP logs

Supervisor logs

System logs

Rotate logs regularly.

Retain according to company policy.

---

# **14\. Monitoring**

Monitor:

* CPU  
* RAM  
* Disk  
* Queue workers  
* Scheduler  
* Nginx  
* PHP-FPM  
* Database  
* Storage  
* SSL expiry  
* Backup success

---

# **15\. Health Checks**

Verify:

Application responds

↓

Database reachable

↓

Queue operational

↓

Scheduler active

↓

Storage writable

↓

Disk space sufficient

↓

SSL valid

Health endpoint (future):

/health

---

# **16\. Backup Strategy**

Backup includes:

Database

Application uploads

Configuration

Import files

Export files (optional)

Barcode templates

Retention example:

Daily

30 days

Weekly

12 weeks

Monthly

12 months

Store backups off-server whenever possible.

---

# **17\. Restore Procedure**

Steps:

1. Stop application  
2. Restore database  
3. Restore uploaded files  
4. Restore storage  
5. Restore configuration  
6. Clear caches  
7. Verify integrity  
8. Restart services  
9. Validate application

Perform restore tests regularly.

---

# **18\. Disaster Recovery**

Potential scenarios:

* Server failure  
* Database corruption  
* Disk failure  
* Accidental deletion  
* Ransomware  
* Cloudflare outage  
* Certificate expiry  
* Power outage

Each scenario should have documented recovery steps.

---

# **19\. Business Continuity**

Objectives:

Minimise downtime.

Protect customer data.

Maintain audit integrity.

Recover quickly.

Suggested targets:

RPO

≤ 24 hours

RTO

≤ 4 hours

Review these targets as business requirements evolve.

---

# **20\. Security Hardening**

Production servers should:

* Disable password SSH logins where practical.  
* Use SSH keys.  
* Restrict firewall ports.  
* Enable automatic security updates (where appropriate).  
* Remove unused packages.  
* Disable directory listing.  
* Run services with least privilege.  
* Protect secrets.

---

# **21\. Maintenance**

Regular tasks:

* Update operating system  
* Update PHP  
* Update Composer dependencies  
* Update Laravel  
* Update Filament  
* Rotate logs  
* Verify backups  
* Review disk usage  
* Renew SSL  
* Review security advisories

Schedule maintenance windows when required.

---

# **22\. Incident Response**

Incident workflow:

Detect

↓

Assess impact

↓

Contain

↓

Recover

↓

Validate

↓

Communicate

↓

Perform root cause analysis

↓

Implement corrective actions

Document all significant incidents.

---

# **23\. Performance Tuning**

Recommended practices:

* Enable OPcache.  
* Use PHP-FPM process tuning.  
* Index frequently queried columns.  
* Paginate large datasets.  
* Queue long-running tasks.  
* Optimise images and assets.  
* Monitor slow queries.

Benchmark changes before and after optimisation.

---

# **24\. Upgrade Process**

Upgrade sequence:

1. Backup  
2. Review release notes  
3. Update code  
4. Update Composer packages  
5. Run migrations  
6. Build assets  
7. Clear caches  
8. Restart queue workers  
9. Verify health checks  
10. Smoke test  
11. Monitor logs

Rollback immediately if critical issues are detected.

---

# **25\. Rollback Procedure**

If deployment fails:

1. Enable maintenance mode.  
2. Restore previous release.  
3. Restore database if schema changed.  
4. Restart services.  
5. Verify health.  
6. Disable maintenance mode.  
7. Investigate failure before redeployment.

---

# **26\. Go-Live Checklist**

Before production:

✓ Server hardened

✓ HTTPS configured

✓ APP\_DEBUG=false

✓ Queue operational

✓ Scheduler operational

✓ Database backed up

✓ Restore procedure verified

✓ Monitoring configured

✓ Backups scheduled

✓ SSL valid

✓ Health checks passing

✓ Smoke tests completed

✓ Documentation updated

---

# **27\. Operational Runbooks**

Create runbooks for:

* Application restart  
* Queue worker restart  
* Database maintenance  
* Backup verification  
* Restore execution  
* SSL renewal  
* Emergency rollback  
* Password reset  
* User lockout  
* Storage cleanup

Runbooks should be simple, repeatable, and version-controlled.

---

# **28\. Future Enhancements**

Planned operational improvements:

* Redis caching  
* Redis queues  
* Horizontal scaling  
* Read replica databases  
* Object storage (S3-compatible)  
* Centralised logging  
* Metrics dashboards  
* Automated deployment pipeline  
* Blue/Green deployments  
* Zero-downtime deployments  
* High Availability (HA)

---

# **29\. Operations Summary**

The DMIMS production environment should always prioritise:

* Availability  
* Security  
* Reliability  
* Recoverability  
* Maintainability  
* Observability

Operational excellence is achieved through repeatable processes, monitoring, backups, and tested recovery procedures.

---

# **Document History**

| Version | Date | Description |
| ----- | ----- | ----- |
| 1.0 | June 2026 | Initial Deployment, Operations & Disaster Recovery Guide |

