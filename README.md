# CirculAPU

CirculAPU is a sustainability and circular-economy web application developed for the Asia Pacific University (APU) community. It helps users list reusable items, borrow available assets, participate in sustainability missions, earn EcoPoints, unlock badges, and redeem rewards.

## Technology

- PHP 8.1 or later
- MySQL or MariaDB
- Apache HTTP Server
- HTML, CSS, and JavaScript
- PHPMailer for email-related features

The project is designed to run locally with XAMPP.

## User roles

- **Member** — lists and borrows assets, joins missions, submits evidence, earns EcoPoints and badges, redeems rewards, and submits reports.
- **Manager** — reviews asset submissions, manages categories and transactions, handles borrowed assets, and reviews reports.
- **Auditor** — manages missions, rewards, achievement badges, verification records, and redemption history.
- **Admin** — manages users and tiers, views analytics and system logs, handles queued reports, and can preview other roles in read-only simulation mode.

After login, `dashboard.php` sends each user to the dashboard for their assigned role.

## Local installation

1. Install XAMPP with PHP 8.1 or later.
2. Place the project at:

   ```text
   C:\xampp\htdocs\CirculAPU
   ```

3. Start **Apache** and **MySQL** from the XAMPP Control Panel.
4. Open phpMyAdmin at `http://localhost/phpmyadmin`.
5. Import `circulapu_db.sql`. The script creates and populates the `circulapu_db` database.
6. Check the database settings in `db.php`. The default local configuration is:

   ```text
   Host: localhost
   User: root
   Password: (empty)
   Database: circulapu_db
   ```

7. Open the application:

   ```text
   http://localhost/CirculAPU/
   ```

The root URL redirects to `login.php`. New member accounts can be created through `signup.php`.

## Project structure

```text
CirculAPU/
├── Admin/                 Admin dashboard and administration features
├── Auditor/               Missions, rewards, badges, and verification
├── Manager/               Asset, category, transaction, and report handling
├── Members/               Marketplace and member-facing features
├── PHPMailer/              Email delivery library
├── assets/                 Shared images and front-end assets
├── uploads/                User-uploaded asset, report, and submission files
├── circulapu_db.sql        Database schema and seed data
├── db.php                  Shared database connection
├── dashboard.php           Role-based dashboard router
├── index.php               Entry-point redirect
├── login.php               Authentication page
├── signup.php              Member registration page
└── style.css               Shared application styles
```

## Main features

- Account registration, login, password reset, and profile management
- Role-based authentication and dashboards
- Reusable-item marketplace and asset listing workflow
- Borrowing requests, approvals, returns, and transaction tracking
- Sustainability missions and evidence submission
- EcoPoints, membership tiers, achievement badges, and rewards
- Notifications and activity records
- User reports and management review workflows
- Administrative analytics, user management, and system logging
- Read-only role simulation for administrators
- Responsive layouts for desktop and mobile use

## Email configuration

Features such as OTP delivery and password reset use PHPMailer. Before using email in another environment, review the mail settings in the relevant PHP files and replace any local or development credentials with environment-specific values. Do not commit production passwords or API keys to source control.

## Upload permissions

The web server must have write access to `uploads/` and its subdirectories. Uploaded files should be treated as application data and backed up separately from the source code.

## Troubleshooting

- **Database connection failed:** confirm MySQL is running and that the values in `db.php` match the local MySQL account.
- **Page not found:** confirm the folder name is exactly `CirculAPU` under `C:\xampp\htdocs` and use `http://localhost/CirculAPU/`.
- **Images or styles are missing:** the application uses `/CirculAPU/...` absolute paths, so changing the project folder or URL base requires updating those paths.
- **Uploads fail:** check PHP upload limits and ensure Apache can write to the `uploads/` directory.
- **Email is not sent:** verify the SMTP configuration, credentials, port, and encryption settings used by PHPMailer.

## Security notes

This repository uses local XAMPP database defaults. For deployment, use a dedicated database account with a strong password, move secrets to environment configuration, enable HTTPS, restrict upload types and sizes, and disable detailed production error output.

## License

No license file is currently included. Unless the project owners specify otherwise, all rights are reserved.
