<?php
require_once __DIR__ . '/config/auth.php';
if (getAuthenticatedUser()) {
    header('Location: index.htm');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | MoneyTrack</title>
    <link rel="stylesheet" href="style.css">
</head>

<body class="auth-page">
    <main class="auth-card">
        <div class="auth-brand">
            <img src="image/moneytrack-logo.svg" alt="MoneyTrack logo">
            <h1>MoneyTrack</h1>
            <p id="formDescription">เข้าสู่ระบบเพื่อจัดการข้อมูลของคุณ</p>
        </div>
        <form id="loginForm" class="auth-form">
            <div class="auth-field">
                <label for="identity">ชื่อผู้ใช้หรืออีเมล</label>
                <input id="identity" type="text" autocomplete="username" required placeholder="กรอกชื่อผู้ใช้หรืออีเมล">
            </div>
            <div class="auth-field">
                <label for="password">รหัสผ่าน</label>
                <input id="password" type="password" autocomplete="current-password" required placeholder="กรอกรหัสผ่าน">
            </div>
            <p id="errorMessage" class="auth-error hidden"></p>
            <button id="loginButton" class="auth-submit" type="submit">เข้าสู่ระบบ</button>
        </form>
        <form id="registerForm" class="auth-form hidden">
            <div class="auth-field">
                <label for="registerUsername">ชื่อผู้ใช้</label>
                <input id="registerUsername" type="text" autocomplete="username" minlength="3" maxlength="50" required
                    placeholder="ภาษาอังกฤษ ตัวเลข หรือ _">
            </div>
            <div class="auth-field">
                <label for="registerEmail">อีเมล</label>
                <input id="registerEmail" type="email" autocomplete="email" maxlength="100" required
                    placeholder="you@example.com">
            </div>
            <div class="auth-field">
                <label for="registerFullName">ชื่อ-นามสกุล <span>(ไม่บังคับ)</span></label>
                <input id="registerFullName" type="text" autocomplete="name" maxlength="100" placeholder="ชื่อที่ใช้แสดง">
            </div>
            <div class="auth-field">
                <label for="registerPassword">รหัสผ่าน</label>
                <input id="registerPassword" type="password" autocomplete="new-password" minlength="6" maxlength="255"
                    required placeholder="อย่างน้อย 6 ตัวอักษร">
            </div>
            <p id="registerErrorMessage" class="auth-error hidden"></p>
            <button id="registerButton" class="auth-submit" type="submit">สมัครสมาชิก</button>
        </form>
        <button id="switchAuthButton" class="auth-switch" type="button">ยังไม่มีบัญชี? สมัครสมาชิก</button>
    </main>
    <script>
        let registerMode = false;

        document.getElementById('switchAuthButton').addEventListener('click', function () {
            registerMode = !registerMode;
            document.getElementById('loginForm').classList.toggle('hidden', registerMode);
            document.getElementById('registerForm').classList.toggle('hidden', !registerMode);
            document.getElementById('formDescription').textContent = registerMode ? 'สร้างบัญชีเพื่อเริ่มจัดการการเงิน' : 'เข้าสู่ระบบเพื่อจัดการข้อมูลของคุณ';
            this.textContent = registerMode ? 'มีบัญชีแล้ว? เข้าสู่ระบบ' : 'ยังไม่มีบัญชี? สมัครสมาชิก';
        });

        document.getElementById('loginForm').addEventListener('submit', async function (event) {
            event.preventDefault();
            const button = document.getElementById('loginButton');
            const error = document.getElementById('errorMessage');
            button.disabled = true;
            button.textContent = 'กำลังตรวจสอบ...';
            error.classList.add('hidden');
            try {
                const response = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        identity: document.getElementById('identity').value,
                        password: document.getElementById('password').value
                    })
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'เข้าสู่ระบบไม่สำเร็จ');
                window.location.href = 'index.htm';
            } catch (exception) {
                error.textContent = exception.message;
                error.classList.remove('hidden');
                button.disabled = false;
                button.textContent = 'เข้าสู่ระบบ';
            }
        });

        document.getElementById('registerForm').addEventListener('submit', async function (event) {
            event.preventDefault();
            const button = document.getElementById('registerButton');
            const error = document.getElementById('registerErrorMessage');
            button.disabled = true;
            button.textContent = 'กำลังสร้างบัญชี...';
            error.classList.add('hidden');
            try {
                const response = await fetch('api/auth.php?action=register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: document.getElementById('registerUsername').value,
                        email: document.getElementById('registerEmail').value,
                        full_name: document.getElementById('registerFullName').value,
                        password: document.getElementById('registerPassword').value
                    })
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'สมัครสมาชิกไม่สำเร็จ');
                window.location.href = 'index.htm';
            } catch (exception) {
                error.textContent = exception.message;
                error.classList.remove('hidden');
                button.disabled = false;
                button.textContent = 'สมัครสมาชิก';
            }
        });
    </script>
</body>

</html>