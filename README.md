# Membership App

<p align="center">
  <img src="logo.png" width="300" alt="Membership App Logo">
</p>

## 🚀 Project Idea
A digital membership management app that allows the admin to add members, generate digital membership cards with QR codes, and verify the membership status (valid or expired) when the QR is scanned by any mobile device.

---

## 🛠️ Technologies Used
- **Backend:** Laravel 12
- **Frontend:** Vue 3 + Laravel Breeze
- **Styling:** TailwindCSS
- **QR Generation:** simplesoftwareio/simple-qrcode
- **Database:** MySQL
- **Package Manager:** Composer & NPM
- **Build Tool:** Vite

---

## ⚙️ Features
- Admin CRUD for members
- Generate unique Membership Number for each member
- Create QR Code for each membership
- Verify membership status when QR is scanned
- Export PDF for membership cards

---

## 📂 Project Structure
app/
Http/
Controllers/
Admin/
MemberController.php
Models/
Member.php
resources/
views/
admin/
dashboard.blade.php
member_card.blade.php
routes/
web.php
database/
migrations/
create_members_table.php


---

## 📦 Packages Used
- laravel/breeze
- inertiajs/inertia-laravel
- @inertiajs/vue3
- laravel-vite-plugin
- tailwindcss/forms
- simplesoftwareio/simple-qrcode
- axios
- vue-router (optional for SPA)

---

## 🔧 Installation
```bash
git clone <repo-url>
cd membership-app
composer install
npm install --legacy-peer-deps
php artisan migrate
php artisan serve
npm run dev


📌 Usage

Admin logs in
Adds new members
Generates digital membership cards for members
Sends the card or QR to the member
Anyone scanning the QR can check the membership status

