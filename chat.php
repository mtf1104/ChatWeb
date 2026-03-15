<?php
// 1. Configuración de sesión para compatibilidad con HTTPS y Render
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'True');
session_start();

// 2. Validación de Seguridad
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

$mi_id = (int)$_SESSION['id_usuario'];
$mi_nombre = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';

// Consultamos nuestra propia foto para el modal
$res_mia = $conn->query("SELECT foto_perfil FROM usuarios WHERE id_usuario = $mi_id");
$mi_foto = ($res_mia && $row_yo = $res_mia->fetch_assoc()) ? $row_yo['foto_perfil'] : 'default_avatar.png';
if(empty($mi_foto)) $mi_foto = 'default_avatar.png';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatWeb - Sala Privada</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .user-item { transition: background 0.3s; display: flex; align-items: center; gap: 12px; padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #eee; }
        .user-item:hover { background: #f5f5f5; }
        .avatar-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; background: #ddd; }
        .header-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        /* Estilo para el botón de ajustes */
        .mi-perfil-header:hover { background: #008f6f !important; }
    </style>
</head>

<body style="display:block; margin:0; background:#f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">

<div class="chat-main-container" style="display:flex; height:100vh; width:100vw; background:white;">

    <aside style="width:320px; border-right:1px solid #ddd; display:flex; flex-direction:column;">
        <div class="mi-perfil-header" onclick="abrirAjustes()" style="padding:20px; background:#00a884; color:white; cursor:pointer; transition: 0.3s;">
            <h3 style="margin:0;">ChatWeb ⚙️</h3>
            <span>Conectado como: <strong><?php echo htmlspecialchars($mi_nombre); ?></strong></span>
        </div>

        <ul class="user-list" style="list-style:none; padding:0; margin:0; overflow-y:auto; flex-grow:1;">
            <li class='user-item' data-id='120001' data-nombre='Asistente IA' data-foto='bot_avatar.png' onclick='cambiarChat(this)' style='background: #f9f9f9;'>
                <img src="https://cdn-icons-png.flaticon.com/512/4712/4712035.png" class="avatar-img">
                <div style="flex-grow:1;">
                    <strong>Asistente IA</strong>
                    <div style="font-size: 12px; color: #00a884;">En línea</div>
                </div>
            </li>

            <?php
            $res = $conn->query("SELECT id_usuario, nombre, foto_perfil FROM usuarios WHERE id_usuario != $mi_id AND id_usuario != 120001");
            if($res) {
                while($u = $res->fetch_assoc()){
                    $id = $u['id_usuario'];
                    $nombre = htmlspecialchars($u['nombre']);
                    $foto = !empty($u['foto_perfil']) ? $u['foto_perfil'] : 'default_avatar.png';
                    
                    echo "<li class='user-item' data-id='$id' data-nombre='$nombre' data-foto='$foto' onclick='cambiarChat(this)'>
                            <img src='uploads/$foto' class='avatar-img' onerror=\"this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'\">
                            <div style='flex-grow:1;'>
                                <strong>$nombre</strong>
                                <div style='font-size: 12px; color: gray;'>Usuario</div>
                            </div>
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
        <div id="header-chat" style="padding:10px 15px; background:#f0f2f5; display:flex; align-items:center; gap:12px; font-weight:bold; border-bottom:1px solid #ddd; color:#3b4a54; min-height: 50px;">
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

<div id="modal-ajustes" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:white; padding:30px; border-radius:10px; width:350px; text-align:center; position:relative;">
        <span onclick="cerrarAjustes()" style="position:absolute; top:10px; right:15px; cursor:pointer; font-size:20px;">&times;</span>
        <h3>Mi Perfil</h3>
        
        <div style="margin-bottom:20px;">
            <img id="img-previa-ajustes" src="uploads/<?php echo $mi_foto; ?>" style="width:100px; height:100px; border-radius:50%; object-fit:cover; border:2px solid #00a884;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
        </div>

        <form id="form-update-perfil" onsubmit="actualizarPerfil(event)">
            <label for="nueva-foto" style="display:block; margin-bottom:10px; color:#00a884; cursor:pointer; font-weight:bold;">
                📷 Cambiar Foto de Perfil
            </label>
            <input type="file" id="nueva-foto" accept="image/*" style="display:none;" onchange="previsualizar(this)">
            
            <input type="text" id="nuevo-nombre" value="<?php echo htmlspecialchars($mi_nombre); ?>" placeholder="Tu nombre" 
                   style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:5px;">
            
            <button type="submit" style="width:100%; padding:10px; background:#00a884; color:white; border:none; border-radius:5px; cursor:pointer; font-weight:bold;">
                Guardar Cambios
            </button>
        </form>
    </div>
</div>

<script>
let receptorActual = null;
let cronometro = null;
const ID_IA = 120001; 

function cambiarChat(el){
    receptorActual = el.getAttribute('data-id');
    const nombre = el.getAttribute('data-nombre');
    const foto = el.querySelector('img').src;

    document.getElementById('header-chat').innerHTML = `
        <img src="${foto}" class="header-avatar">
        <span>${nombre}</span>
    `;
    
    document.getElementById('form-envio').style.display = 'block';
    document.getElementById('box-mensajes').innerHTML = ''; 

    document.querySelectorAll('.user-item').forEach(i => i.style.background = 'white');
    if(receptorActual != ID_IA) el.style.background = '#e7f5f2';

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

// FUNCIONES DE AJUSTES
function abrirAjustes() {
    document.getElementById('modal-ajustes').style.display = 'flex';
}

function cerrarAjustes() {
    document.getElementById('modal-ajustes').style.display = 'none';
}

function previsualizar(input) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('img-previa-ajustes').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

async function actualizarPerfil(e) {
    e.preventDefault();
    const formData = new FormData();
    const foto = document.getElementById('nueva-foto').files[0];
    const nombre = document.getElementById('nuevo-nombre').value;

    formData.append('nombre', nombre);
    if (foto) formData.append('foto_perfil', foto);

    try {
        const r = await fetch('index.php?action=actualizar_perfil', {
            method: 'POST',
            body: formData
        });
        const res = await r.json();
        if (res.status === 'success') {
            alert("Perfil actualizado correctamente.");
            location.reload(); 
        } else {
            alert("Error: " + res.message);
        }
    } catch (error) {
        console.error("Error:", error);
    }
}
</script>

</body>
</html>