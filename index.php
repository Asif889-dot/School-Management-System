<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS | Smart Choice Academy Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: url('assets/images/background.jpg');
            background-size: cover;
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('assets/images/background.jpg') center/cover no-repeat;
            filter: blur(15px);
            z-index: -1;
            transform: scale(1.05);
        }

        .login-container {
            max-width: 500px;
            width: 100%;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .login-header h2 {
            margin-bottom: 5px;
            font-weight: 700;
        }

        .login-header p {
            opacity: 0.8;
            font-size: 14px;
        }

        .login-body {
            padding: 30px;
        }

        .role-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .role-badge {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            background: #f0f2f5;
            color: #6c757d;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .role-badge i {
            display: block;
            font-size: 20px;
            margin-bottom: 5px;
        }

        .role-badge:hover {
            background: #e0e0e0;
        }

        .role-badge.active {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            color: white;
        }

        .form-floating {
            position: relative;
            margin-bottom: 15px;
        }

        .form-control:focus {
            border-color: #1a3a5c;
            box-shadow: 0 0 0 0.2rem rgba(26, 58, 92, 0.25);
        }

        .btn-login {
            background: linear-gradient(135deg, #1a3a5c 0%, #0d2b46 100%);
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 58, 92, 0.4);
        }

        .btn-login:disabled {
            opacity: 0.7;
            transform: none;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 10;
            color: #6c757d;
        }

        .toggle-password:hover {
            color: #1a3a5c;
        }

        .alert-custom {
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .footer-links {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .footer-links a {
            color: #1a3a5c;
            text-decoration: none;
            font-size: 13px;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-mortarboard-fill" style="font-size: 48px;"></i>
                <h2>Smart Choice Academy</h2>
                <p>Login to access your dashboard</p>
            </div>
            <div class="login-body">
                <div class="role-selector">
                    <div class="role-badge" data-role="student">
                        <i class="bi bi-person-fill"></i>
                        <span>Student</span>
                    </div>
                    <div class="role-badge" data-role="teacher">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>Teacher</span>
                    </div>
                    <div class="role-badge" data-role="admin">
                        <i class="bi bi-shield-lock-fill"></i>
                        <span>Admin</span>
                    </div>
                    <div class="role-badge" data-role="guardian">
                        <i class="bi bi-people-fill"></i>
                        <span>Guardian</span>
                    </div>
                </div>

                <div id="alertMessage" class="alert-custom" style="display: none;"></div>

                <form id="loginForm">
                    <input type="hidden" name="role" id="roleInput" value="student">

                    <div class="form-floating">
                        <input type="text" class="form-control" id="loginInput" name="reg_id" placeholder="Email or Registration ID" required>
                        <label>Email or Registration ID</label>
                    </div>

                    <div class="form-floating password-wrapper">
                        <input type="password" class="form-control" id="passwordInput" name="password" placeholder="Password" required>
                        <label>Password</label>
                        <i class="bi bi-eye-slash toggle-password" id="togglePassword"></i>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="rememberCheck" name="remember">
                            <label class="form-check-label small" for="rememberCheck">Keep me logged in</label>
                        </div>
                        <a href="#" class="small text-decoration-none">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn btn-login" id="loginButton">
                        <i class="bi bi-box-arrow-in-right"></i> Login to Portal
                    </button>
                </form>

                <div class="footer-links">
                    <a href="#">Help</a>
                    <span class="mx-2">|</span>
                    <a href="#">Contact Support</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('passwordInput');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });

        // Role selection
        const roleBadges = document.querySelectorAll('.role-badge');
        const roleInput = document.getElementById('roleInput');

        roleBadges.forEach(badge => {
            badge.addEventListener('click', function() {
                roleBadges.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                roleInput.value = this.dataset.role;
            });
        });

        // Form submission
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const alertMessage = document.getElementById('alertMessage');

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            // Disable button
            loginButton.disabled = true;
            loginButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
            alertMessage.style.display = 'none';

            // Get form data
            const formData = new FormData(loginForm);

            try {
                const response = await fetch('backend/login.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alertMessage.className = 'alert-custom alert alert-success';
                    alertMessage.innerHTML = data.message;
                    alertMessage.style.display = 'block';

                    // Redirect
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    alertMessage.className = 'alert-custom alert alert-danger';
                    alertMessage.innerHTML = data.message;
                    alertMessage.style.display = 'block';
                    loginButton.disabled = false;
                    loginButton.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Login to Portal';
                }
            } catch (error) {
                console.error('Login error:', error);
                alertMessage.className = 'alert-custom alert alert-danger';
                alertMessage.innerHTML = 'Connection error. Please try again.';
                alertMessage.style.display = 'block';
                loginButton.disabled = false;
                loginButton.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> Login to Portal';
            }
        });

        // Check URL parameters for messages
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('message') === 'logged_out') {
            alertMessage.className = 'alert-custom alert alert-info';
            alertMessage.innerHTML = 'You have been logged out successfully.';
            alertMessage.style.display = 'block';
        }

        if (urlParams.get('error') === 'session_expired') {
            alertMessage.className = 'alert-custom alert alert-warning';
            alertMessage.innerHTML = 'Your session has expired. Please login again.';
            alertMessage.style.display = 'block';
        }

        if (urlParams.get('error') === 'access_denied') {
            alertMessage.className = 'alert-custom alert alert-danger';
            alertMessage.innerHTML = 'Access denied. Please login with appropriate credentials.';
            alertMessage.style.display = 'block';
        }
    </script>
</body>

</html>