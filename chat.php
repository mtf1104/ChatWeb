<?php
session_start();
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
mysqli_real_connect($conn, $host, $user, $pass, $db_name, $port, NULL, MYSQLI_CLIENT_SSL);

$mi_id = (int)$_SESSION['id_usuario'];
$mi_nombre = $_SESSION['nombre'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ChatWeb - Sala Privada</title>
    <link rel="stylesheet" href="style.css">
</head>

<body style="display:block; margin:0; background:#f0f2f5;">

<div class="chat-main-container" style="display:flex; height:100vh; width:100vw; background:white;">

    <aside style="width:300px; border-right:1px solid #ddd; display:flex; flex-direction:column;">
        <div style="padding:20px; background:#00a884; color:white;">
            <h3 style="margin:0;">ChatWeb</h3>
            <span><?php echo htmlspecialchars($mi_nombre); ?></span>
        </div>

        <ul class="user-list" style="list-style:none; padding:0; margin:0; overflow-y:auto; flex-grow:1;">
            <?php
            // Lista de usuarios reales
            $res = $conn->query("SELECT id_usuario, nombre FROM usuarios WHERE id_usuario != $mi_id");
            while($u = $res->fetch_assoc()){
                $id = $u['id_usuario'];
                $nombre = htmlspecialchars($u['nombre']);
                echo "<li class='user-item' data-id='$id' data-nombre='$nombre' onclick='cambiarChat(this)' 
                      style='padding:15px; cursor:pointer; border-bottom:1px solid #eee;'>
                      $nombre
                      </li>";
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
            <div style="display:flex; gap:10px;">
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
const ID_IA = 120001; // ID reservado para la Inteligencia Artificial

function cambiarChat(el){
    receptorActual = el.getAttribute('data-id');
    const nombre = el.getAttribute('data-nombre');

    document.getElementById('header-chat').innerText = "Chat con: " + nombre;
    document.getElementById('form-envio').style.display = 'block';
    document.getElementById('box-mensajes').innerHTML = ''; // Limpiar al cambiar

    document.querySelectorAll('.user-item').forEach(i => i.style.background = 'white');
    el.style.background = '#e7f5f2';

    refrescar();

    if(cronometro) clearInterval(cronometro);
    // Solo activar auto-refresco si NO es la IA
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

    /* ESCENARIO A: CHAT CON IA */
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

    /* ESCENARIO B: CHAT NORMAL */
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

/**
 * Función auxiliar para mostrar mensajes de IA o errores locales
 */
function mostrarMensajeLocal(usuario, texto, clase){
    const box = document.getElementById('box-mensajes');
    const div = document.createElement('div');
    // Usamos las mismas clases de style.css: mensaje, mi-msj, otro-msj
    div.className = "mensaje " + clase;
    div.innerHTML = "<strong>" + usuario + ":</strong><br>" + texto;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
}
</script>

</body>
</html>