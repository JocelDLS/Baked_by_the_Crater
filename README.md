<div align="center">

# BAKED-BY-THE-CRATER

*Bake Innovation Into Every Slice of Success*

</div>

---

<div align="center">

## Table of Contents

[Overview](#overview) • [Getting Started](#getting-started) • [Prerequisites](#prerequisites) • [Installation](#installation) • [Usage](#usage) • [Testing](#testing)

</div>

---

<div align="center">

## Overview

</div>

**Baked-by-the-Crater** is a full-featured PHP e-commerce platform tailored for bakery websites, combining secure user authentication, dynamic content rendering, and comprehensive admin controls. Its modular architecture supports efficient data management through XML and database integrations, ensuring a smooth experience for both customers and administrators.

### Why Baked-by-the-Crater?

This project empowers developers to build secure, scalable, and engaging online bakery stores. The core features include:

* 🔐 **User Authentication**: Secure login, email verification, Google Sign-In, and password reset workflows.
* 🧾 **XML Data Handling**: Manage carts, orders, and product data efficiently with structured XML utilities.
* 📊 **Admin Dashboard**: Oversee products, orders, users, and analytics with an intuitive interface.
* ⚡ **Real-time Chat**: Enhance customer support with live messaging and conversation persistence.
* 🧩 **Modular Architecture**: Reusable PHP components for database connections, email notifications, and session management.

---

<div align="center">

## Getting Started

</div>

<div align="center">

### Prerequisites

</div>

This project requires the following dependencies:

* **Programming Language**: PHP
* **Package Manager**: Composer

---

<div align="center">

<div align="center">

### Installation

</div>

Build **Baked-by-the-Crater** from the source and install dependencies:

1. **Clone the repository**

   ```bash
   git clone https://github.com/your-username/baked-by-the-crater
   ```

2. **Navigate to the project directory**

   ```bash
   cd baked-by-the-crater
   ```

3. **Install the dependencies**

   ```bash
   composer install
   ```

---

<div align="center">

### Database Setup

</div>

The system uses a **MySQL database** to manage users, products, orders, and administrative data.

**Steps to configure the database:**

1. Create a new MySQL database (e.g., `baked_by_the_crater`).

2. Import the provided SQL file located in the `/database` directory.

3. Update database credentials in the configuration file:

   ```php
   $host = 'localhost';
   $dbname = 'baked_by_the_crater';
   $username = 'root';
   $password = '';
   ```

4. Ensure your web server (Apache/Nginx) and MySQL services are running.

The platform also integrates **XML-based data handling** for carts and order tracking, complementing the relational database structure.

---

<div align="center">

### Sample Database Tables

</div>

Below are sample database tables used in the system to support authentication, subscriptions, chat messaging, and administration.

---

#### Users Table

Stores registered user accounts and authentication details.

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    is_verified TINYINT(1) DEFAULT 0,
    provider VARCHAR(50) DEFAULT 'local',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);
```

**Fields:**

* `id` – Unique user identifier
* `email` – User email address
* `password_hash` – Encrypted password
* `first_name`, `last_name` – User name details
* `phone`, `address` – Contact information
* `is_verified` – Email verification status
* `provider` – Authentication provider (local / Google)
* `created_at`, `last_login` – Account timestamps

---

#### Subscribers Table

Stores email subscribers for newsletters and promotions.

```sql
CREATE TABLE subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Fields:**

* `id` – Subscriber identifier
* `email` – Subscriber email address
* `subscribed_at` – Subscription date

---

#### Full_Texts (Chat Messages) Table

Handles real-time chat messages between admins and customers.

```sql
CREATE TABLE full_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message_text TEXT,
    sender_type ENUM('admin', 'customer') NOT NULL,
    message_type ENUM('text', 'file') DEFAULT 'text',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Fields:**

* `id` – Message identifier
* `user_id` – Related user
* `message_text` – Chat message content
* `sender_type` – Sender role (admin or customer)
* `message_type` – Message format (text or file)
* `timestamp` – Message time

---

#### Admins Table

Stores administrative user credentials and access control data.

```sql
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**Fields:**

* `admin_id` – Admin identifier
* `email` – Admin login email
* `password` – Encrypted admin password
* `name` – Administrator name
* `created_at` – Account creation timestamp

---

<div align="center">

### Usage

</div>

After installation, configure your database and environment variables, then deploy the project on a PHP-supported server (e.g., Apache with XAMPP or LAMP). Access the application through your browser to start managing products, users, and orders.

---

<div align="center">

### Testing

</div>

Run functional and integration tests to ensure system stability. Verify authentication workflows, order processing, XML data handling, and admin dashboard operations before production deployment.

---

© 2025 Baked-by-the-Crater. All rights reserved.
