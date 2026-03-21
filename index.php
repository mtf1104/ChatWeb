<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatWeb - Acceso</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .phone-group { display: flex; gap: 5px; margin-bottom: 15px; }
        .phone-group select { width: 35%; border-radius: 8px; border: 1px solid #ddd; padding: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div id="login-section">
            <h2>Bienvenido a ChatWeb</h2>
            <input type="email" id="log_cor" placeholder="Correo">
            <input type="password" id="log_pass" placeholder="Contraseña">
            <button onclick="login()">Iniciar Sesión</button>
            <p class="toggle-text">¿No tienes cuenta? <span onclick="toggleAuth()">Regístrate</span></p>
        </div>

        <div id="register-section" style="display:none;">
            <h2>Crea tu cuenta</h2>
            <input type="text" id="reg_nom" placeholder="Nombre">
            <input type="text" id="reg_apa" placeholder="Apellido Paterno">
            <input type="text" id="reg_ama" placeholder="Apellido Materno">
            <div class="phone-group">
                <select id="country_code">
                    <option value="52">🇲🇽 +52</option>
                    <option value="1">🇺🇸 +1</option>
                    <option value="34">🇪🇸 +34</option>
                </select>
                <input type="text" id="reg_tel" placeholder="Teléfono">
            </div>
            <input type="email" id="reg_cor" placeholder="Correo">
            <button onclick="registro()">Registrarme</button>
            <p class="toggle-text">¿Ya tienes? <span onclick="toggleAuth()">Inicia sesión</span></p>
        </div>
    </div>

    <script>
        function toggleAuth() {
            document.getElementById('login-section').style.display = (document.getElementById('login-section').style.display === 'none') ? 'block' : 'none';
            document.getElementById('register-section').style.display = (document.getElementById('register-section').style.display === 'none') ? 'block' : 'none';
        }

        async function registro() {
            const cod = document.getElementById('country_code').value;
            const num = document.getElementById('reg_tel').value.replace(/\D/g, '');
            const datos = {
                nombre: document.getElementById('reg_nom').value,
                ap_paterno: document.getElementById('reg_apa').value,
                ap_materno: document.getElementById('reg_ama').value,
                telefono: cod + num,
                correo: document.getElementById('reg_cor').value
            };

            try {
                const res = await fetch('./index.php?action=registro', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(datos)
                });
                const result = await res.json();

                if (result.status === 'success') {
                    alert(`¡Éxito! Tu clave es: ${result.temp_pass}`);
                    const urlWA = `https://wa.me/${result.telefono}?text=${encodeURIComponent('Hola ' + result.nombre + ', mi clave es: ' + result.temp_pass)}`;
                    
                    const win = window.open(urlWA, '_blank');
                    if (!win) window.location.href = urlWA; // Redirección si falla el popup

                    toggleAuth();
                } else { alert(result.message); }
            } catch (e) { alert("Error de conexión con Render."); }
        }

        async function login() {
            const correo = document.getElementById('log_cor').value;
            const password = document.getElementById('log_pass').value;
            try {
                const res = await fetch('./index.php?action=login', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({correo, password})
                });
                const result = await res.json();
                if (result.status === 'success') { window.location.href = result.redirect; }
                else { alert(result.message); }
            } catch (e) { alert("Error de login."); }
        }
    </script>
</body>
</html>