# JBook - Job Placement & Social Network
# README

## Prerequisites
1.  PHP (Version 8.0 or higher)
2.  MySQL Database (via XAMPP, MAMP, or standalone)
3.  Web Browser

## Installation & Setup

1.  **Database Configuration**:
    - Open `config/db.php` and update the database credentials if necessary.
    - Default settings:
      - Host: 127.0.0.1
      - User: root
      - Password: Bipin7110@
      - Database: jbook_db

2.  **Database Setup**:
    - Ensure your MySQL server is running.
    - Import the `database.sql` file into your MySQL server to create the necessary tables.
    - Alternatively, the system attempts to create the database automatically on first run, but manual import is recommended for a complete schema.

## How to Run the Website

1.  **Open Terminal**:
    - Navigate to the project directory:
      `cd "/Users/bipin/Desktop/Capstone Project"`

2.  **Start PHP Server**:
    - Run the following command to start the built-in PHP web server:
      `php -S localhost:8000`

    - You should see output indicating the server has started (e.g., "Listening on http://localhost:8000").

3.  **Access the Website**:
    - Open your web browser.
    - Go to: **http://localhost:8000**

## Features & Usage

- **Job Seekers**: Register/Login to browse jobs, apply, build a profile, and generate a resume using the AI Chatbot (bottom right).
- **Employers**: Register/Login to post jobs, manage applications, and view candidate profiles.
- **Admin**: Access the admin panel to manage users, ban accounts, and oversee the platform.

## Troubleshooting

- **Server Address in Use**: If you get an error that port 8000 is in use, try changing the port:
  `php -S localhost:8080`
  Then access at http://localhost:8080.
- **Database Connection Error**: Double-check your MySQL password in `config/db.php` matched your XAMPP/MySQL settings.

