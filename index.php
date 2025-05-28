<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido - Iniciar Sesión o Registrarse</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .auth-container h2 {
            text-align: center;
            color: #0056b3;
            margin-bottom: 20px;
        }
        .auth-container form {
            margin-bottom: 30px;
        }
        .auth-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .auth-container input[type="text"],
        .auth-container input[type="email"],
        .auth-container input[type="password"],
        .auth-container input[type="date"] { 
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .auth-container button {
            background-color: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        .auth-container button:hover {
            background-color: #0056b3;
        }
        .message {
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
        }
        .message.success {
            color: #28a745;
        }
        .message.error {
            color: #dc3545;
        }
        .toggle-form {
            text-align: center;
            margin-top: 20px;
        }
        .toggle-form a {
            color: #007bff;
            text-decoration: none;
            cursor: pointer;
        }
        .toggle-form a:hover {
            text-decoration: underline;
        }
        #loginClienteForm, #registerClienteForm {
            display: none; 
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Bienvenido</h1>
        <div id="authMessage" class="message"></div>

        <div class="toggle-form">
            <a id="showLoginAdmin" href="#">Iniciar Sesión Admin</a> |
            <a id="showLoginCliente" href="#">Iniciar Sesión Cliente</a> |
            <a id="showRegisterCliente" href="#">Registrarse como Cliente</a>
        </div>
        <hr>

        <div id="adminLoginForm">
            <h2>Iniciar Sesión (Administrador)</h2>
            <form id="formAdminLogin">
                <label for="admin_username">Usuario:</label>
                <input type="text" id="admin_username" name="username" value="admin" required>

                <label for="admin_password">Contraseña:</label>
                <input type="password" id="admin_password" name="password" value="12345678" required>

                <button type="submit">Iniciar Sesión Admin</button>
            </form>
        </div>

        <div id="loginClienteForm" style="display: none;">
            <h2>Iniciar Sesión (Cliente)</h2>
            <form id="formClienteLogin">
                <label for="cliente_email_login">Email:</label>
                <input type="email" id="cliente_email_login" name="email" required>

                <label for="cliente_password_login">Contraseña:</label>
                <input type="password" id="cliente_password_login" name="password" required>

                <button type="submit">Iniciar Sesión Cliente</button>
            </form>
        </div>

        <div id="registerClienteForm" style="display: none;">
            <h2>Registrarse (Cliente)</h2>
            <form id="formClienteRegister">
                <label for="reg_nombre">Nombre:</label>
                <input type="text" id="reg_nombre" name="nombre" required>

                <label for="reg_apellido_paterno">Apellido Paterno:</label>
                <input type="text" id="reg_apellido_paterno" name="apellido_paterno" required>

                <label for="reg_apellido_materno">Apellido Materno (Opcional):</label>
                <input type="text" id="reg_apellido_materno" name="apellido_materno">

                <label for="reg_email">Email:</label>
                <input type="email" id="reg_email" name="email" required>

                <label for="reg_password">Contraseña (mínimo 8 caracteres):</label>
                <input type="password" id="reg_password" name="password" required>

                <label for="reg_fecha_nacimiento">Fecha de Nacimiento (Opcional):</label>
                <input type="date" id="reg_fecha_nacimiento" name="fecha_nacimiento">

                <label for="reg_telefono">Teléfono (Opcional):</label>
                <input type="text" id="reg_telefono" name="telefono">

                <button type="submit">Registrarse</button>
            </form>
        </div>
    </div>

    <script src="js/auth.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const authMessage = document.getElementById('authMessage');
            const adminLoginForm = document.getElementById('adminLoginForm');
            const loginClienteForm = document.getElementById('loginClienteForm');
            const registerClienteForm = document.getElementById('registerClienteForm');

            const showLoginAdminBtn = document.getElementById('showLoginAdmin');
            const showLoginClienteBtn = document.getElementById('showLoginCliente');
            const showRegisterClienteBtn = document.getElementById('showRegisterCliente');

            function hideAllForms() {
                adminLoginForm.style.display = 'none';
                loginClienteForm.style.display = 'none';
                registerClienteForm.style.display = 'none';
            }

            adminLoginForm.style.display = 'block';

            showLoginAdminBtn.addEventListener('click', function(e) {
                e.preventDefault();
                hideAllForms();
                adminLoginForm.style.display = 'block';
            });

            showLoginClienteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                hideAllForms();
                loginClienteForm.style.display = 'block';
            });

            showRegisterClienteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                hideAllForms();
                registerClienteForm.style.display = 'block';
            });

            function handleAuthResponse(data, defaultUserName) {
                if (data.success) {
                    authMessage.className = 'message success';
                    authMessage.textContent = 'Inicio de sesión/Registro exitoso. Redirigiendo...';
                    localStorage.setItem('loggedIn', 'true');
                    localStorage.setItem('userRole', data.role);
                    localStorage.setItem('userName', data.userName || defaultUserName); 
                    setTimeout(() => {
                        if (data.role === 'admin') {
                            window.location.href = 'dashboard_admin.php';
                        } else if (data.role === 'cliente') {
                            window.location.href = 'dashboard_cliente.php';
                        }
                    }, 1000);
                } else {
                    authMessage.className = 'message error';
                    authMessage.textContent = data.message || 'Error en la operación. Intenta de nuevo.';
                }
            }

            document.getElementById('formAdminLogin').addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                formData.append('login_admin', '1');

                fetch('auth.php', {
                    method: 'POST',
                    body: formData 
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => handleAuthResponse(data, 'Administrador'))
                .catch(error => {
                    console.error('Error:', error);
                    authMessage.className = 'message error';
                    authMessage.textContent = 'Error de comunicación con el servidor o respuesta inválida.';
                });
            });

            document.getElementById('formClienteLogin').addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                formData.append('login_cliente', '1');

                fetch('auth.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => handleAuthResponse(data, 'Cliente')) 
                .catch(error => {
                    console.error('Error:', error);
                    authMessage.className = 'message error';
                    authMessage.textContent = 'Error de comunicación con el servidor o respuesta inválida.';
                });
            });

            document.getElementById('formClienteRegister').addEventListener('submit', function(event) {
                event.preventDefault();
                const formData = new FormData(this);
                formData.append('register_cliente', '1');

                fetch('register.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => handleAuthResponse(data, document.getElementById('reg_nombre').value))
                .catch(error => {
                    console.error('Error:', error);
                    authMessage.className = 'message error';
                    authMessage.textContent = 'Error de comunicación con el servidor o respuesta inválida.';
                });
            });
        });
    </script>
</body>
</html>