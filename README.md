# Library API

Laravel REST API project for managing a library system.

## Project Description

This project is a Library Management API built with Laravel.
It provides APIs for managing members, authors, books, borrowings, reservations, fines, categories, publishers, and admin approval for publisher books.

## Main Features

- Authentication
- Members Management
- Authors Management
- Books Management
- Borrowings Management
- Reservations Management
- Reports
- Member Portal
- Fines Management
- Categories Management
- Publisher Portal
- Admin Books Approval

## User Roles

The system supports the following roles:

- Admin
- Member
- Publisher

## Base URL

```text
http://127.0.0.1:8000/api/v1
```

## Authentication

Protected routes require Bearer Token authentication.

Headers example:

```text
Accept: application/json
Authorization: Bearer YOUR_TOKEN
```

## API Modules

### Auth

- Register
- Login
- Logout
- Profile

### Members

- Get all members
- Create member
- Get one member
- Update member
- Delete member

### Authors

- Get all authors
- Create author
- Get one author
- Update author
- Delete author

### Books

- Get all books
- Create book
- Get one book
- Update book
- Delete book

### Borrowings

- Get all borrowings
- Create borrowing
- Get one borrowing
- Return book

### Reservations

- Get all reservations
- Create reservation
- Get one reservation
- Cancel reservation
- Fulfill reservation

### Reports

- Books report
- Borrowings report
- Reservations report
- Fines report

### Member Portal

- My borrowings
- My reservations
- My fines
- Cancel my reservation

### Fines

- Get all fines
- Get one fine
- Pay fine
- Waive fine

### Categories

- Get all categories
- Create category
- Get one category
- Update category
- Delete category

### Publisher Portal

- Publisher dashboard
- My books
- Create publisher book
- Get one publisher book
- Update publisher book
- Delete publisher book

### Admin Books Approval

- Get pending books
- Approve book
- Reject book

## Postman Collection

All API routes were tested successfully using Postman.

The Postman collection is included in the project inside the `postman` folder.

## How to Run the Project

1. Install dependencies:

```bash
composer install
```

2. Copy environment file:

```bash
copy .env.example .env
```

3. Generate application key:

```bash
php artisan key:generate
```

4. Run migrations:

```bash
php artisan migrate
```

5. Start the development server:

```bash
php artisan serve
```

## Testing Status

All API requests were tested successfully using Postman.

## Technologies Used

- Laravel
- PHP
- MySQL
- Postman
- GitHub
