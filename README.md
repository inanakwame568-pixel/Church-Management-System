# Church Management System

A web-based Church Management System built with **PHP, MySQL, HTML, CSS, and JavaScript**. The system helps churches manage members, attendance, donations, ministries, events, announcements, and administrative reports through a secure web interface.

---

## Features

- Secure Admin Authentication
- Dashboard Overview
- Member Registration and Management
- Attendance Management
- Donation and Tithe Records
- Ministry Management
- Church Event Management
- Church Announcements
- Reports Generation
- User Profile Management
- Responsive Design

---

## Technologies Used

- PHP 8+
- MySQL
- HTML5
- CSS3
- JavaScript
- Bootstrap (Optional)
- Apache (XAMPP, WAMP, or LAMP)

---

## Project Structure

```
church-management-system/
│
├── admin/
├── assets/
├── database/
├── includes/
├── uploads/
├── reports/
├── api/
├── index.php
├── login.php
├── logout.php
├── register.php
├── README.md
├── LICENSE
├── .gitignore
└── .env.example
```

---

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/yourusername/church-management-system.git
```

### 2. Move Project

Copy the project folder into your web server directory.

For XAMPP:

```
htdocs/church-management-system
```

---

### 3. Create Database

Create a database named:

```
church_management
```

---

### 4. Import Database

Import the SQL file located in:

```
database/church_management.sql
```

using phpMyAdmin.

---

### 5. Configure Database

Update your database credentials inside:

```
includes/config.php
```

or use the values in your `.env` file.

---

### 6. Start Server

Open:

```
http://localhost/church-management-system
```

---

## Default Administrator

Create your first administrator through registration or insert one directly into the database.

---

## Security

This project implements:

- Password Hashing
- Session Authentication
- Prepared Statements
- Input Validation
- SQL Injection Prevention
- XSS Protection

---

## Future Improvements

- SMS Notifications
- Email Notifications
- Online Donations
- PDF Report Generation
- Member Portal
- Mobile Application
- Multi-Church Support
- Backup and Restore

---

## Contributing

Contributions are welcome.

1. Fork the repository.
2. Create a new branch.
3. Commit your changes.
4. Push your branch.
5. Open a Pull Request.

---

## License

This project is licensed under the MIT License.

---

## Author

Developed using PHP, MySQL, HTML, CSS, and JavaScript.

© 2026