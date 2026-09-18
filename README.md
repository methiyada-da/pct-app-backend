# Puean Chuay Tu API – ระบบหลังบ้านแอปพลิเคชันเพื่อนช่วยติว

## เกี่ยวกับโปรเจกต์

Backend API สำหรับ Mini Project แอปพลิเคชันค้นหาเพื่อนติวและติวเตอร์สำหรับนักศึกษา ทำงานร่วมกับ [Flutter Frontend](https://github.com/methiyada-da/pct-app-frontend) และออกแบบมาสำหรับการรันในสภาพแวดล้อม Local

## ความสามารถของระบบ

- สมัครสมาชิกและเข้าสู่ระบบ
- อ่านและแก้ไขข้อมูลผู้ใช้ รวมถึงรูปโปรไฟล์และรหัสผ่าน
- ส่งคำขอสมัครเป็นติวเตอร์และตรวจสอบสถานะคำขอ
- ค้นหาติวเตอร์และคอร์สด้วยคำค้น กลุ่มวิชา รายวิชา และช่วงราคา
- สร้าง แก้ไข เปิด/ปิด และลบคอร์สของติวเตอร์ พร้อมจัดการตารางสอน
- สร้างห้องสนทนา อ่านรายการสนทนา รับส่งข้อความ และติดตามสถานะข้อความที่อ่านแล้ว

ระบบยังไม่มี API สำหรับการจองเรียน รีวิว การชำระเงิน และการแจ้งเตือน

## เทคโนโลยีที่ใช้

- PHP 8 และ PDO
- MySQL หรือ MariaDB
- Apache (เหมาะสำหรับใช้งานผ่าน XAMPP)
- JSON over HTTP สำหรับสื่อสารกับ Frontend
- HMAC-SHA256 สำหรับลงนาม Bearer Token

## การติดตั้ง

### 1. เตรียมฐานข้อมูล

สร้างฐานข้อมูลว่าง แล้ว import โครงสร้างจาก `database/schema.sql` ซึ่งประกอบด้วยตารางสมาชิก ติวเตอร์ คอร์ส ตารางสอน ห้องสนทนา และข้อความ

### 2. ตั้งค่า Environment

ใช้ `.env.example` เป็นตัวอย่าง **เฉพาะชื่อตัวแปร** แล้วสร้างไฟล์ Environment จริงไว้นอก `htdocs` เช่น:

```text
C:\xampp\private\mini_backend.env
```

ตัวแปรที่ต้องกำหนดมีดังนี้:

```dotenv
DB_HOST=
DB_PORT=
DB_NAME=
DB_USER=
DB_PASSWORD=
AUTH_SECRET=
ALLOWED_ORIGINS=http://localhost:8080,http://127.0.0.1:8080
```

- กำหนด `AUTH_SECRET` เป็นค่าสุ่มที่คาดเดาได้ยากและมีความยาวอย่างน้อย 32 ตัวอักษร
- กำหนด `ALLOWED_ORIGINS` เป็น origin ของ Frontend ที่อนุญาตให้เรียก API โดยคั่นหลายค่าด้วย comma
- ห้ามนำ Password, Token หรือ Secret จริงใส่ใน README หรือ commit ลง repository

จากนั้นกำหนดพาธไฟล์ผ่านตัวแปร `MINI_BACKEND_ENV_FILE` ใน Apache configuration ที่อยู่นอก `htdocs`:

```apache
SetEnv MINI_BACKEND_ENV_FILE "C:/xampp/private/mini_backend.env"
```

Restart Apache หลังแก้ไขค่า Environment แล้ววางโปรเจกต์ให้ Apache เข้าถึงได้ เช่น `C:\xampp\htdocs\mini_backend`

> โค้ดยังรองรับไฟล์ `.env` ภายในโฟลเดอร์โปรเจกต์เพื่อความเข้ากันได้ แต่แนวทางที่แนะนำคือเก็บไฟล์จริงไว้นอก `htdocs`

## API Overview

### Authentication และข้อมูลตัวเลือก

| Method | Endpoint | การยืนยันตัวตน | หน้าที่ |
| --- | --- | --- | --- |
| `POST` | `create_member.php` | ไม่ต้องใช้ | สมัครสมาชิก |
| `POST` | `login.php` | ไม่ต้องใช้ | เข้าสู่ระบบและรับ Token |
| `GET` | `get_options.php` | ไม่ต้องใช้ | อ่านรายการคณะและสาขาวิชา |
| `GET` | `get_course_options.php` | ไม่ต้องใช้ | อ่านกลุ่มวิชาและรายวิชา |

### Profile และ Tutor

| Method | Endpoint | การยืนยันตัวตน | หน้าที่ |
| --- | --- | --- | --- |
| `GET` | `get_user.php` | Bearer Token | อ่านข้อมูลผู้ใช้ปัจจุบัน |
| `POST` | `update_member.php` | Bearer Token | เปลี่ยนรูปโปรไฟล์หรือรหัสผ่าน |
| `GET`, `POST` | `create_tutor.php` | Bearer Token | ตรวจสอบหรือส่งคำขอสมัครติวเตอร์ |
| `POST` | `get_tutor_list.php` | ไม่ต้องใช้ | ค้นหาและกรองคอร์ส/ติวเตอร์ |

### Course และ Schedule

| Method | Endpoint | การยืนยันตัวตน | หน้าที่ |
| --- | --- | --- | --- |
| `POST` | `get_tutor_courses.php` | ใช้เมื่ออ่านคอร์สของตนเอง | อ่านคอร์สและตารางสอน; ส่ง `tut_id` เพื่อดูคอร์สที่เปิดรับแบบสาธารณะ |
| `POST` | `create_tutor_course.php` | Bearer Token | สร้างคอร์สและตารางสอน |
| `POST` | `update_tutor_course.php` | Bearer Token | แก้ไขคอร์สและตารางสอนของตนเอง |
| `POST` | `toggle_course_status.php` | Bearer Token | เปิดหรือปิดรับผู้เรียนในคอร์สของตนเอง |
| `POST` | `delete_tutor_course.php` | Bearer Token | ลบคอร์สของตนเอง |

### Chat

| Method | Endpoint | การยืนยันตัวตน | หน้าที่ |
| --- | --- | --- | --- |
| `GET` | `get_conversations.php` | Bearer Token | อ่านรายการห้องสนทนา |
| `POST` | `get_or_create_conversation.php` | Bearer Token | ค้นหาหรือสร้างห้องสนทนาแบบหนึ่งต่อหนึ่ง |
| `POST` | `get_messages.php` | Bearer Token | อ่านข้อความและอัปเดตสถานะว่าอ่านแล้ว |
| `POST` | `send_message.php` | Bearer Token | ส่งข้อความ |

## Authentication และ Security

- Endpoint ที่ป้องกันไว้รับ Header รูปแบบ `Authorization: Bearer <token>`
- Token ลงนามด้วย `AUTH_SECRET` และมีอายุ 8 ชั่วโมง
- รหัสผ่านใหม่จัดเก็บด้วย `password_hash()` และตรวจด้วย `password_verify()`
- บัญชีเดิมที่ยังใช้รหัสผ่านแบบ plain text จะถูกอัปเกรดเป็น hash หลังเข้าสู่ระบบสำเร็จ
- คำสั่งฐานข้อมูลใช้ PDO prepared statements
- CORS อนุญาตเฉพาะ origin ที่กำหนดใน `ALLOWED_ORIGINS`
- `.htaccess` ปิด directory listing และห้าม HTTP access ไปยัง dotfile หรือไฟล์ภายใน hidden directory
- ระบบ log ปกปิด field สำคัญบางรายการก่อนบันทึก

## การทดสอบ

ตรวจ PHP syntax ของไฟล์ API ได้ด้วย PowerShell:

```powershell
Get-ChildItem -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

รัน security smoke test ที่มีอยู่ในโปรเจกต์:

```powershell
C:\xampp\php\php.exe tests\security_smoke_test.php
```

ผลลัพธ์ของคำสั่งขึ้นอยู่กับ PHP และ Environment ของเครื่องที่รัน

## Frontend Repository

[github.com/methiyada-da/pct-app-frontend](https://github.com/methiyada-da/pct-app-frontend)

## โครงสร้างโปรเจกต์

```text
mini_backend/
├── database/
│   └── schema.sql              # โครงสร้างฐานข้อมูล
├── tests/
│   └── security_smoke_test.php # ชุดตรวจสอบด้าน Authentication และ Security
├── .env.example                # ตัวอย่างชื่อตัวแปร Environment
├── .htaccess                   # กฎ Apache และการป้องกัน dotfile
├── config.php                  # การเชื่อมต่อฐานข้อมูล
├── helper.php                  # CORS, Token, Validation และ Logging
└── *.php                       # API endpoints
```

## ความปลอดภัยของ Repository

ไม่ควร commit รายการต่อไปนี้:

- `.env`, `.env.*` (ยกเว้น `.env.example`) และ `.auth_secret`
- Logs และรูปโปรไฟล์ที่ผู้ใช้อัปโหลด
- Database dump ที่มีข้อมูลจริง
- Password, API Key, Token, Secret หรือข้อมูลส่วนบุคคล

## ทีมพัฒนา

โปรเจกต์นี้เป็นงานกลุ่มจำนวน 2 คน:

- [methiyada-da](https://github.com/methiyada-da)
- [MatchaWannoi](https://github.com/MatchaWannoi)

### หน้าที่รับผิดชอบ

ร่วมกันวิเคราะห์ ออกแบบ และพัฒนาโปรเจกต์

## หมายเหตุ

โปรเจกต์นี้เป็น Mini Project สำหรับการศึกษาและใช้เป็น Portfolio ไม่ใช่ Production Application และยังไม่ได้ระบุการ Deploy สำหรับใช้งานออนไลน์
