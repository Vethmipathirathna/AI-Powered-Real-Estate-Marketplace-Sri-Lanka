# Intelligent Real Estate Marketplace System with AI Price Estimation

A web-based real estate marketplace developed for Sri Lanka with an integrated AI house price estimation feature.

The system supports three main user roles:

- Buyer
- Seller
- Administrator

It combines a PHP/MySQL web application with a Python Flask AI service and a trained machine-learning model.

---

## Main Features

### Buyer
- Browse available property listings
- View detailed property information
- Add or remove favourite properties
- View saved favourites
- Contact sellers through the messaging system
- View conversations
- Manage profile information

### Seller
- Seller dashboard
- Create property listings
- Edit and manage own properties
- View individual property details
- Communicate with buyers
- View conversations and messages
- View sold property history
- Manage profile information

### Administrator
- Admin dashboard
- Manage users
- Create and edit users
- Manage property listings
- Create and edit properties
- View property details
- View AI prediction records
- View individual prediction details
- View reports
- Print reports
- View sold property history
- Manage support requests
- Communicate through support conversations

### AI Price Estimation
- Python Flask API
- Trained machine-learning model
- Property price estimation
- AI price insight integration
- Prediction history
- Prediction detail view

### Additional Functions
- User registration and login
- Role-based authentication
- Profile management
- Buyer-seller messaging
- Support messaging
- Property image uploads
- Property ownership validation

---

## Technologies Used

| Component | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript, Bootstrap |
| Backend | PHP |
| Database | MySQL |
| Local Server | XAMPP |
| AI / Machine Learning | Python, Scikit-learn |
| AI API | Flask |
| Development Environment | Visual Studio Code |
| Version Control | Git and GitHub |

---

## Project Structure

```text
RealEstateAI/
├── admin/
├── ai/
│   ├── models/
│   │   └── realestate_price_model.joblib
│   ├── app.py
│   ├── estimate.php
│   ├── history.php
│   ├── prediction_view.php
│   └── requirements.txt
├── assets/
│   ├── css/
│   ├── images/
│   │   ├── profiles/
│   │   └── properties/
│   └── js/
├── auth/
│   ├── login.php
│   ├── logout.php
│   └── register.php
├── buyer/
├── config/
│   └── database.php
├── database/
│   └── realestate_ai.sql
├── includes/
├── profile/
├── properties/
├── seller/
├── support/
├── tools/
├── .gitignore
└── index.php
```

> The local Python virtual environment (`ai/venv/`) is not shown because it contains installed dependencies rather than project source code.

---

## Installation and Setup

### 1. Requirements

Install the following software before running the project:

- XAMPP
- Python 3
- A modern web browser

### 2. Place the Project in XAMPP

Copy the project folder into the XAMPP `htdocs` directory.

Example:

```text
C:\xampp\htdocs\RealEstateAI
```

### 3. Start XAMPP

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 4. Create the Database

Open phpMyAdmin:

```text
http://localhost/phpmyadmin/
```

Import the SQL file located at:

```text
database/realestate_ai.sql
```

This file contains the database structure required by the application.

### 5. Check Database Configuration

Open:

```text
config/database.php
```

Make sure the MySQL connection settings match the local XAMPP configuration.

Typical local settings are:

```text
Host: localhost
Username: root
Password: [empty by default in XAMPP]
```

Use the database name configured by the supplied SQL/database configuration.

### 6. Set Up the AI Environment

Open Command Prompt or PowerShell in the project directory and move to the AI folder:

```bash
cd C:\xampp\htdocs\RealEstateAI\ai
```

It is recommended to create a virtual environment:

```bash
python -m venv venv
```

Activate it on Windows:

```bash
venv\Scripts\activate
```

Install the required Python packages:

```bash
pip install -r requirements.txt
```

### 7. Start the Flask AI Service

While the virtual environment is active, run:

```bash
python app.py
```

Keep this terminal open while using AI price estimation.

### 8. Run the Web Application

Open a browser and visit:

```text
http://localhost/RealEstateAI/
```

The application should now connect to the MySQL database and the locally running AI service.

---

## AI Model

The trained model is stored at:

```text
ai/models/realestate_price_model.joblib
```

The Flask application in `ai/app.py` is responsible for loading the model and providing AI price-estimation functionality to the web application.

The PHP application communicates with the AI component when a property price estimate is requested.

---

## Important Notes

- Apache and MySQL must be running before using the PHP application.
- The Flask AI service must be running before using AI price estimation.
- Import `database/realestate_ai.sql` before testing database-dependent functions.
- Ensure the database settings in `config/database.php` are correct.
- Property and profile images are stored under `assets/images/`.
- Do not include the local `ai/venv/` directory in Git if it is already excluded by `.gitignore`.

---

## Main Application Modules

```text
admin/       Administrator functions
auth/        Login, logout and registration
buyer/       Buyer dashboard, favourites and messaging
seller/      Seller dashboard, property management and messaging
properties/  Public/shared property browsing and viewing
profile/     User profile management
support/     User support functions
ai/          AI price estimation and prediction history
database/    Database SQL file
config/      Database configuration
includes/    Shared PHP helpers and components
assets/      CSS, JavaScript and uploaded images
```

---

## Project Purpose

The purpose of this project is to provide a centralized real estate marketplace where buyers and sellers can interact while using artificial intelligence to support property price estimation. The system is designed around the Sri Lankan real estate context and combines normal marketplace functions with an AI-assisted estimation feature.

---

## Development

This project was developed as an academic software engineering project using PHP, MySQL, Python, Flask and Scikit-learn.
