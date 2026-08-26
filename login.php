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
    <title>เข้าสู่ระบบ | AI Dashboard การเงิน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/kanit@4.5.0/index.css">
    <style>
        body {
            font-family: Kanit, sans-serif;
        }

        .login-bg {
            background: linear-gradient(135deg, #312e81, #7c3aed 55%, #db2777);
        }
    </style>
</head>

<body class="login-bg min-h-screen flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-8">
            <img class="logo-icon" src="image/logo1.png" alt="FinMind logo" style="width: 80px; height: 80px; margin: 0 auto;">
            <h1 class="text-2xl font-bold text-gray-800">AI Dashboard การเงิน</h1>
            <p id="formDescription" class="text-gray-500 mt-2">เข้าสู่ระบบเพื่อจัดการข้อมูลของคุณ</p>
        </div>
        <form id="loginForm" class="space-y-5">
            <div>
                <label for="identity" class="block text-gray-700 font-medium mb-2">ชื่อผู้ใช้หรืออีเมล</label>
                <input id="identity" type="text" autocomplete="username" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="กรอกชื่อผู้ใช้หรืออีเมล">
            </div>
            <div>
                <label for="password" class="block text-gray-700 font-medium mb-2">รหัสผ่าน</label>
                <input id="password" type="password" autocomplete="current-password" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="กรอกรหัสผ่าน">
            </div>
            <p id="errorMessage" class="hidden text-red-600 text-sm"></p>
            <button id="loginButton" type="submit"
                class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg transition">เข้าสู่ระบบ</button>
        </form>
        <form id="registerForm" class="space-y-5 hidden">
            <div>
                <label for="registerUsername" class="block text-gray-700 font-medium mb-2">ชื่อผู้ใช้</label>
                <input id="registerUsername" type="text" autocomplete="username" minlength="3" maxlength="50" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="ภาษาอังกฤษ ตัวเลข หรือ _">
            </div>
            <div>
                <label for="registerEmail" class="block text-gray-700 font-medium mb-2">อีเมล</label>
                <input id="registerEmail" type="email" autocomplete="email" maxlength="100" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="you@example.com">
            </div>
            <div>
                <label for="registerFullName" class="block text-gray-700 font-medium mb-2">ชื่อ-นามสกุล <span
                        class="text-gray-400">(ไม่บังคับ)</span></label>
                <input id="registerFullName" type="text" autocomplete="name" maxlength="100"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="ชื่อที่ใช้แสดง">
            </div>
            <div>
                <label for="registerPassword" class="block text-gray-700 font-medium mb-2">รหัสผ่าน</label>
                <input id="registerPassword" type="password" autocomplete="new-password" minlength="6" maxlength="255"
                    required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    placeholder="อย่างน้อย 6 ตัวอักษร">
            </div>
            <p id="registerErrorMessage" class="hidden text-red-600 text-sm"></p>
            <button id="registerButton" type="submit"
                class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg transition">สมัครสมาชิก</button>
        </form>
        <button id="switchAuthButton" type="button"
            class="w-full mt-5 text-purple-600 hover:text-purple-800 text-sm">ยังไม่มีบัญชี? สมัครสมาชิก</button>
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