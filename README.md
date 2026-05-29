# WaMark - White Label WhatsApp Marketing Platform

**Version:** 1.0.0  
**Technology:** Core PHP 8.2+ | MySQL 5.7+ | Bootstrap 5 | Responsive Design

WaMark is a production-ready, white-label WhatsApp Marketing & Automation SaaS platform designed for direct deployment on shared hosting (cPanel) or VPS servers.

---

## Features

### WhatsApp Integration
- WhatsApp Business Cloud API support
- Template messaging & broadcast
- Chat automation & auto-reply
- Multi-device session management
- Webhook processing for delivery receipts

### Campaign Management
- Broadcast campaigns to contact groups
- Scheduled & drip campaigns
- Custom variable support ({name}, {company}, etc.)
- Real-time delivery tracking
- Pause, resume, cancel controls

### Automation Engine
- Welcome sequences
- Follow-up workflows
- Birthday & anniversary triggers
- Lead nurturing drip campaigns
- Cart recovery workflows
- Keyword-based triggers
- Multi-step with delays

### SaaS Billing
- Free trial, monthly, yearly, lifetime plans
- Stripe, Razorpay, PayPal, PayU gateways
- Automatic subscription management
- Invoice generation
- Usage-based limits

### White Label
- Custom branding (logo, colors, name)
- Custom domain support
- Custom login URL
- Remove developer branding
- Custom CSS injection
- Custom SMTP per reseller

### Security
- CSRF protection on all forms
- XSS input filtering
- SQL injection prevention (PDO prepared statements)
- 2FA (TOTP) authentication
- Role-based access control
- Audit logging
- Rate limiting
- Session timeout

### User Roles
- **Super Admin** - Full system control
- **Reseller** - Client management, custom branding
- **Client** - WhatsApp, contacts, campaigns, automation

---

## Requirements

| Requirement | Minimum |
|------------|---------|
| PHP | 8.0+ |
| MySQL | 5.7+ / MariaDB 10.3+ |
| Extensions | PDO, pdo_mysql, mbstring, json, curl, openssl, gd, fileinfo, zip |
| Web Server | Apache with mod_rewrite |
| Storage | 100MB+ |

---

## Quick Install

1. Upload the `WaMark/` folder to your `public_html/` directory
2. Set permissions: `chmod -R 755 storage/ uploads/ config/`
3. Navigate to `https://yourdomain.com/WaMark/`
4. The installation wizard will launch automatically
5. Follow the 5-step setup process

---

## Folder Structure

```
WaMark/
├── admin/          # Admin panel (Super Admin & Reseller)
├── user/           # Client dashboard
├── api/            # REST API & webhooks
├── assets/         # CSS, JS, images
│   ├── css/
│   ├── js/
│   └── templates/  # Shared PHP templates
├── config/         # Core configuration & classes
├── cron/           # Scheduled task scripts
├── database/       # SQL schema & migrations
├── installer/      # Installation wizard
├── modules/        # Engine classes (WhatsApp, Billing, etc.)
├── storage/        # Logs, backups, cache (writable)
├── uploads/        # User uploaded files (writable)
├── vendor/         # Third-party libraries (if any)
├── index.php       # Main entry point
├── .env            # Environment configuration (auto-generated)
├── .env.example    # Example environment file
└── .htaccess       # Apache security & rewrite rules
```

---

## Cron Setup

Add this single cron job to run all automated tasks:

```bash
* * * * * php /home/username/public_html/WaMark/cron/run.php >> /dev/null 2>&1
```

Or via URL (with secret key from .env):
```
https://yourdomain.com/WaMark/cron/run.php?key=YOUR_CRON_SECRET_KEY
```

---

## API Documentation

Base URL: `https://yourdomain.com/WaMark/api/`

### Authentication
Include `X-API-Key` header with every request.

### Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/status | Health check |
| GET | /api/contacts | List contacts |
| POST | /api/contacts | Create contact |
| PUT | /api/contacts/{id} | Update contact |
| DELETE | /api/contacts/{id} | Delete contact |
| GET | /api/messages | List messages |
| POST | /api/messages | Send/queue message |
| GET | /api/campaigns | List campaigns |
| POST | /api/campaigns | Create campaign |
| POST | /api/campaigns/{id}/start | Start campaign |
| GET | /api/templates | List templates |
| POST | /api/whatsapp/send | Direct send message |
| GET | /api/whatsapp | List WA accounts |

### Example: Send Message
```bash
curl -X POST https://yourdomain.com/WaMark/api/messages \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phone": "+1234567890", "type": "text", "body": "Hello World!"}'
```

---

## WhatsApp Setup

### Cloud API (Recommended)
1. Create a Meta Developer account
2. Create a WhatsApp Business app
3. Get Access Token & Phone Number ID
4. Configure webhook URL: `https://yourdomain.com/WaMark/api/webhook.php`
5. Set Verify Token in admin Settings > WhatsApp

### Webhook Events
Subscribe to: `messages`, `message_status_updates`

---

## License

Commercial software. See LICENSE file for terms.
