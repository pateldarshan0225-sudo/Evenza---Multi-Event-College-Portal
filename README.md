# 🎓 Evenza — Multi-Event College Portal & Admin Management System

![Evenza Banner](assets/images/logo.svg)

> **A modern, centralized management portal for multi-university event coordination, student registrations, and automated team rosters.**

---

## 📌 Project Overview & Purpose (क्यों बनाया गया?)

### ❓ **Problem Statement (क्यों इसकी ज़रूरत पड़ी?)**
In traditional inter-collegiate fests and multi-university competitions:
* **Fragmented Data**: Universities and colleges manage events, student lists, and team entries manually using spreadsheets, leading to data loss, duplicates, and communication gaps.
* **Manual Team Roster Conflicts**: Managing team sizes, team codes, and team leader changes manually leads to over-filled or under-sized team registrations.
* **Lack of Real-time Status Visibility**: Tracking live registration statuses (Approved, Pending Payment, Cancelled) and university/college participation counts requires manual auditing.
* **Outdated Admin Interfaces**: Admins need a fast, fluid, responsive, edge-to-edge portal to efficiently manage hundreds of universities, colleges, students, and events in real time.

### 💡 **Solution — Evenza Portal**
**Evenza** is a unified, single-file architected PHP & MySQL portal that streamlines the entire college fest ecosystem:
1. Enables administrators to manage **Universities, Colleges, and Students** seamlessly.
2. Automates **Solo & Team Event** publishing with dynamic team size boundaries (`min_team_size` to `max_team_size`).
3. Provides real-time **Registration Tracking** with participant roster view modals.
4. Includes automated **Team Roster Management** with team code generation, student leader assignment, and live validation.
5. Built on the modern **`Be.run` Design System** with fluid edge-to-edge layouts (`container-fluid`), warm pastel aesthetics, and sticky capsule navigation.

---

## 🚀 Key Modules & System Overview

### 1. 📊 **Dashboard Module (`Dashboard.php`)**
* Real-time metrics on Total Universities, Colleges, Registered Students, Events, and Active Registrations.
* Visual progress cards with live counts and quick action navigation.

### 2. 🏛️ **University Management Module (`alluniversity.php`)**
* Add, edit, and toggle active/inactive status of participating universities in real time.
* Tracks university email, phone numbers, and associated colleges.

### 3. 🏫 **College Management Module (`Colleges.php`)**
* Multi-college tracking under affiliated universities.
* Real-time status toggles, college contacts, and student distribution counts.

### 4. 👨‍🎓 **Student Directory Module (`allstudents.php`)**
* Comprehensive student database with enrollment numbers, email, phone, college affiliation, and profile photos.
* Quick student onboarding modal with dynamic college selection.

### 5. 🏷️ **Event Categories Module (`event_cat.php`)**
* Classifies events into structured categories (Technical, Cultural, E-Sports, Management, Workshops).
* Add and edit category details with dynamic event counters.

### 6. 📅 **Event Management Module (`all_events.php`)**
* Supports both **Solo** and **Team** event formats.
* Custom rules for team sizes (`min_team_size` and `max_team_size`), venue location, event date, dress code, and draft/published statuses.

### 7. 📝 **Registration Management Module (`allregistrations.php`)**
* Real-time registration monitoring with **Confirmed / Approved**, **Pending Payment**, and **Cancelled** badges.
* Roster inspection modal for both individual participants and team members.
* Quick cancel action handler with database state updates.

### 8. 👥 **Team Management & Roster Module (`allteams.php` & `teamdetails.php`)**
* Single-file multi-member team creation with automated unique Team Code generation (`TM101A`).
* Leader assignment and member selection with PDO transaction safety.
* **Team Details view (`teamdetails.php`)**: Full breakdown of team leader, event info, venue, event date, and complete member photo roster.

### 9. 🔒 **Admin Security & Authentication (`auth_check.php` & `change_password.php`)**
* Session-based access control guarding all admin endpoints.
* Password strength meter, live security requirement checklist, and password change handler.
* Floating capsule sidebar with clean `Sidebar.php` component architecture.

---

## 🛠️ Technology Stack

| Layer | Technology |
| :--- | :--- |
| **Backend Language** | PHP 8.1+ |
| **Database Engine** | MySQL (PDO Prepared Statements & Transactions) |
| **Frontend Framework** | Bootstrap 5.3.3 & Custom `Be.run` Design System |
| **Typography & Icons** | Plus Jakarta Sans, Bootstrap Icons (`bi-*`), Feather Icons |
| **Server Environment** | WAMP / XAMPP / Apache / Nginx |

---

## 📁 Project Directory Structure

```
Admin/
├── assets/                  # Frontend assets (CSS, JS, Fonts, Images, Plugins)
│   ├── css/                 # Be.run theme styles & Bootstrap 5
│   ├── fonts/               # Tabler, Phosphor, Feather, Material icons
│   ├── images/              # Logos, user avatars, widget images
│   └── js/                  # Bootstrap bundle & theme scripts
├── auth_check.php           # Admin authentication middleware
├── connection.php           # PDO database connection configuration
├── Sidebar.php              # Unified Be.run floating capsule sidebar component
├── Header.php               # Top navigation header component
├── Footer.php               # Dashboard footer component
├── Dashboard.php            # Main analytics dashboard
├── alluniversity.php        # University management page
├── Colleges.php             # College management page
├── allstudents.php          # Student management directory
├── event_cat.php            # Event categories page
├── all_events.php           # Event creation and listing page
├── allregistrations.php     # Event registration management page
├── allteams.php             # Team management list & modal creation
├── teamdetails.php          # Team details & student roster inspector
├── change_password.php      # Admin account security page
├── Logout.php               # Session destruction & sign-out endpoint
└── README.md                # Project documentation & overview
```

---

## ⚙️ Installation & Local Setup

### 1. **Prerequisites**
* Install [WAMP Server](https://www.wampserver.com/) or [XAMPP](https://www.apachefriends.org/) (PHP 8.1+ & MySQL 8.0+).

### 2. **Database Setup**
* Open PHPMyAdmin (`http://localhost/phpmyadmin`) or MySQL Command Line.
* Create a database named `evenza`:
  ```sql
  CREATE DATABASE evenza CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```
* Import the Evenza database schema table definitions (`events`, `students`, `teams`, `team_members`, `registrations`, `universities`, `colleges`, `categories`, `admins`).

### 3. **Configure Database Connection**
Verify your database credentials in `connection.php` / PHP files:
```php
$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_NAME = 'evenza';
$DB_USER = 'root';
$DB_PASS = '';
```

### 4. **Run Application**
* Place the project in `c:\wamp64\www\Admin` (WAMP) or `htdocs/Admin` (XAMPP).
* Open your browser and navigate to:
  ```
  http://localhost/Admin/Dashboard.php
  ```

---

## 📜 License & Credits

Developed with ❤️ for **Evenza Multi-Event College Portal**.
