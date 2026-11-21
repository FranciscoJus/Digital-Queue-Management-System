# Digital Queue Management System

A web-based queue management system for student services. Students get a digital queue number from their phone, and staff manage the line from a dashboard.

## Team Members

- FRANCISCO, Justin 
- BAYBAY, Anabell E.
- LEGPITAN, Farhaneeza H.
- DINGLASA, Jhillian B.
- RESURRECION, Frank Cedrick L.
- FABIAN, Hark Jorald E.
- SAAVEDRA, Melissa N.
- LEGPITAN, Farhaneeza H.


## About the Project

Long lines at student service offices waste time. This system lets students join a queue from their own device, see how many people are ahead of them, and get an estimated waiting time. Staff open a queue session, call students one at a time, and review the history afterward.

## Features

**Student Portal**
- Get a queue number by entering full name, section, and transaction type (Enrollment, Clearance, Evaluation, Attendance, Department Fee, or Insurance)
- View ticket status, people ahead, and estimated waiting time
- See which number is currently being served
- Leave the queue with a confirmation prompt
- "Queue is closed" message when staff are not accepting students
- Auto-refreshing pages

**Staff Portal**
- Password-protected login with show/hide password
- Open and close queue sessions
- Call next, complete, skip, and requeue students
- Live dashboard counts (Waiting, Completed, Skipped, or Cancelled)
- History page with every session and ticket

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 7.4+ (PDO) |
| Database | MySQL |
| Frontend | HTML, CSS, a little JavaScript |
| Local server | XAMPP (Apache + MySQL) |

## Getting Started

### Prerequisites

- Windows Computer
- [XAMPP](https://www.apachefriends.org/) with Apache and MySQL
- A web browser

### Installation

1. **Download or clone** the repository.
   ```
   https://github.com/FranciscoJus/Digital-Queue-Management-System
   ```
2. **Copy the project folder** into `C:\xampp\htdocs\`.
3. **Start Apache and MySQL** in the XAMPP Control Panel.
4. **Import the database.** Open `http://localhost/phpmyadmin/`, click **Import**, choose `database/digital_queue_db.sql`, and import it.
5. **Create the staff account.** Open `http://localhost/Digital-Queue-Management-System/admin/setup.php`. Only one account can be created.

### Default URLs

| Page | URL |
|---|---|
| Student Portal | `http://localhost/Digital-Queue-Management-System/` |
| Staff Login | `http://localhost/Digital-Queue-Management-System/admin/login.php` |

## Usage

1. Staff sign in and click **Open Queue**. Students cannot get tickets until a session is open.
2. A student opens the student portal, fills in the form, and receives a ticket.
3. Staff click **Call Next**. The student's ticket changes to "Serving".
4. Staff click **Complete**, or **Skip** if the student is not present. Skipped students can be requeued.
5. When service ends, staff click **Close Queue**. Unfinished tickets are cancelled.
6. Open the **History** page to review past sessions and tickets.


## Project Structure

```
digital-queue-system/
├── admin/
│   ├── action.php        # Queue actions (open, close, call, complete, skip, requeue)
│   ├── dashboard.php     # Staff dashboard
│   ├── history.php       # Session and ticket history
│   ├── login.php         # Staff login
│   ├── logout.php        # Staff logout
│   └── setup.php         # One-time staff account creation
├── assets/
│   └── style.css         # Styles
├── config/
│   └── database.php      # Database connection and helpers
├── database/
│   └── digital_queue_db.sql
├── index.php             # Student portal
└── README.md
```

## Database

| Table | Purpose |
|---|---|
| `transaction_types` | Services students can choose |
| `staff_accounts` | Staff login (one account) |
| `queue_sessions` | Open and closed queue sessions |
| `queue_records` | Every ticket, kept permanently for history |
