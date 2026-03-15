<?php
// 1. Configuración de sesión para compatibilidad con HTTPS y Render
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'True');
session_start();

// 2. Validación de Seguridad: Si no hay id_usuario en la sesión, expulsar al index
if (!isset($_SESSION['id_usuario'])) { 
    header("Location: index.html"); 
    exit(); 
}

// --- CONFIGURACIÓN DE BASE DE DATOS (TiDB) ---
$host = 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port = 4000;
$user = 'MPefCA2vQ18cTr4.root';
$pass = 'P6IKI4BtZ5q5OSGg';
$db_name = 'chatweb';

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
$success = mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    die("Error de conexión a la base de datos");
}

// 3. Obtener datos de la sesión actual
$mi_id = (int)$_SESSION['id_usuario'];
$mi_nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatWeb - Sala Privada</title>
    <link rel="stylesheet" href="style.css">
</head>

<body style="display:block; margin:0; background:#f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">

<div class="chat-main-container" style="display:flex; height:100vh; width:100vw; background:white;">

    <aside style="width:300px; border-right:1px solid #ddd; display:flex; flex-direction:column;">
        <div style="padding:20px; background:#00a884; color:white;">
            <h3 style="margin:0;">ChatWeb</h3>
            <span>Conectado como: <strong><?php echo htmlspecialchars($mi_nombre); ?></strong></span>
        </div>

        <ul class="user-list" style="list-style:none; padding:0; margin:0; overflow-y:auto; flex-grow:1;">
            <li class='user-item' data-id='120001' data-nombre='Asistente IA' onclick='cambiarChat(this)' 
                style='padding:15px; cursor:pointer; border-bottom:1px solid #eee; background: #f9f9f9;'>
                <strong>🤖 Asistente IA</strong>
            </li>

            <?php
            // Lista de usuarios reales (excluyendo al usuario actual y al ID de la IA)
            $res = $conn->query("SELECT id_usuario, nombre FROM usuarios WHERE id_usuario != $mi_id AND id_usuario != 120001");
            if($res) {
                while($u = $res->fetch_assoc()){
                    $id = $u['id_usuario'];
                    $nombre = htmlspecialchars($u['nombre']);
                    echo "<li class='user-item' data-id='$id' data-nombre='$nombre' onclick='cambiarChat(this)' 
                          style='padding:15px; cursor:pointer; border-bottom:1px solid #eee;'>
                          $nombre
                          </li>";
                }
            }
            ?>
        </ul>

        <button onclick="location.href='logout.php'" 
                style="padding:15px; background:#d9534f; color:white; border:none; cursor:pointer; font-weight:bold;">
            Cerrar Sesión
        </button>
    </aside>

    <main style="flex-grow:1; display:flex; flex-direction:column; background:#efe7dd;">
        <div id="header-chat" style="padding:15px; background:#f0f2f5; font-weight:bold; border-bottom:1px solid #ddd; color:#3b4a54;">
            Selecciona un contacto para iniciar
        </div>

        <div id="box-mensajes" style="flex-grow:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;">
            </div>

        <form id="form-envio" onsubmit="enviar(event)" style="display:none; padding:15px; background:#f0f2f5; border-top:1px solid #ddd;">
            <div style="display:flex; gap:10px; align-items:center;">
                <label for="input-file" style="cursor:pointer; font-size:22px; padding:0 10px;" title="Adjuntar archivo">📎</label>
                <input type="file" id="input-file" style="display:none" onchange="enviarArchivo(this)">

                <input type="text" id="input-msj" placeholder="Escribe un mensaje..." autocomplete="off"
                       style="flex-grow:1; padding:12px; border-radius:20px; border:1px solid #ccc; outline:none;">
                
                <button type="submit" style="padding:10px 20px; background:#00a884; color:white; border:none; border-radius:25px; cursor:pointer; font-weight:bold;">
                    Enviar
                </button>
            </div>
        </form>
    </main>
</div>

<script>
let receptorActual = null;
let cronometro = null;
const ID_IA = 120001; 

function cambiarChat(el){
    receptorActual = el.getAttribute('data-id');
    const nombre = el.getAttribute('data-nombre');

    document.getElementById('header-chat').innerText = "Chat con: " + nombre;
    document.getElementById('form-envio').style.display = 'block';
    document.getElementById('box-mensajes').innerHTML = ''; 

    document.querySelectorAll('.user-item').forEach(i => i.style.background = 'white');
    el.style.background = '#e7f5f2';

    refrescar();

    if(cronometro) clearInterval(cronometro);
    if(receptorActual != ID_IA) {
        cronometro = setInterval(refrescar, 2000);
    }
}

async function refrescar(){
    if(!receptorActual || receptorActual == ID_IA) return;
    try {
        const r = await fetch('mensajes.php?action=leer&con=' + receptorActual);
        const html = await r.text();
        const box = document.getElementById('box-mensajes');
        box.innerHTML = html;
        box.scrollTop = box.scrollHeight;
    } catch (error) {
        console.error("Error al refrescar mensajes:", error);
    }
}

async function enviar(e){
    e.preventDefault();
    const input = document.getElementById('input-msj');
    const texto = input.value.trim();
    if(!texto) return;

    if(receptorActual == ID_IA){
        mostrarMensajeLocal("Tú", texto, "mi-msj");
        input.value = '';
        try {
            const r = await fetch('ia.php', {
                method: 'POST',
                headers: {"Content-Type": "application/json"},
                body: JSON.stringify({ mensaje: texto })
            });
            const data = await r.json();
            mostrarMensajeLocal("IA", data.respuesta, "otro-msj");
        } catch (error) {
            mostrarMensajeLocal("Sistema", "Error conectando con la IA", "otro-msj");
        }
        return;
    }

    try {
        await fetch('mensajes.php?action=enviar', {
            method: 'POST',
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
                receptor_id: receptorActual,
                mensaje: texto
            })
        });
        input.value = '';
        refrescar();
    } catch (error) {
        alert("No se pudo enviar el mensaje");
    }
}

async function enviarArchivo(input) {
    if (!input.files[0] || !receptorActual) return;
    const formData = new FormData();
    formData.append('archivo', input.files[0]);
    formData.append('receptor_id', receptorActual);
    try {
        await fetch('mensajes.php?action=enviar', { method: 'POST', body: formData });
        input.value = ''; 
        refrescar();
    } catch (error) {
        alert("Error al subir el archivo");
    }
}

function mostrarMensajeLocal(usuario, texto, clase){
    const box = document.getElementById('box-mensajes');
    const div = document.createElement('div');
    div.className = "mensaje " + clase;
    div.innerHTML = "<strong>" + usuario + ":</strong><br>" + texto;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
}
</script>
</body>
</html>