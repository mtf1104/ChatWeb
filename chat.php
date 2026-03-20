<?php
/**
 * ChatWeb - Interfaz de Usuario
 * Optimización de UI y JS para UBAM 2026
 */
ini_set('session.cookie_samesite', 'None');
ini_set('session.cookie_secure', 'True');
session_start();

if (!isset($_SESSION['id_usuario'])) { 
    header("Location: index.html"); 
    exit(); 
}

require 'vendor/autoload.php';

// --- CONEXIÓN ---
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL); 
mysqli_real_connect($conn, 'gateway01.us-east-1.prod.aws.tidbcloud.com', 'MPefCA2vQ18cTr4.root', 'P6IKI4BtZ5q5OSGg', 'chatweb', 4000, NULL, MYSQLI_CLIENT_SSL);

$mi_id = (int)$_SESSION['id_usuario'];
$mi_nombre = $_SESSION['nombre'] ?? 'Usuario';

// Obtener foto actual
$stmt_foto = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
$stmt_foto->bind_param("i", $mi_id);
$stmt_foto->execute();
$mi_foto = $stmt_foto->get_result()->fetch_assoc()['foto_perfil'] ?? 'default_avatar.png';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ChatWeb - Sala Privada</title>
    <link rel="manifest" href="index.php?action=manifest">
    <link rel="stylesheet" href="style.css">
</head>

<body class="chat-body">

<div class="chat-main-container">
    <aside class="sidebar">
        <div class="mi-perfil-header" onclick="abrirAjustes()">
            <h3>ChatWeb ⚙️</h3>
            <span class="status-top">ID: <?php echo $mi_id; ?> | <strong><?php echo htmlspecialchars($mi_nombre); ?></strong></span>
        </div>

        <ul class="user-list" id="lista-contactos">
            <li class="user-item ia-item" data-id="120001" data-nombre="Asistente IA" onclick="cambiarChat(this)">
                <img src="https://cdn-icons-png.flaticon.com/512/4712/4712035.png" class="avatar-img">
                <div class="user-info">
                    <strong>Asistente IA</strong>
                    <small>Soporte Inteligente</small>
                </div>
            </li>

            <?php
            $res = $conn->query("SELECT id_usuario, nombre, foto_perfil FROM usuarios WHERE id_usuario != $mi_id AND id_usuario != 120001 ORDER BY nombre ASC");
            while($u = $res->fetch_assoc()):
                $foto = !empty($u['foto_perfil']) ? $u['foto_perfil'] : 'default_avatar.png';
            ?>
                <li class="user-item" data-id="<?= $u['id_usuario'] ?>" data-nombre="<?= htmlspecialchars($u['nombre']) ?>" onclick="cambiarChat(this)">
                    <img src="uploads/<?= $foto ?>" class="avatar-img" onerror="this.src='https://cdn-icons-png.flaticon.com/512/149/149071.png'">
                    <div class="user-info">
                        <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                        <small>Usuario ChatWeb</small>
                    </div>
                </li>
            <?php endwhile; ?>
        </ul>

        <button class="btn-logout" onclick="location.href='logout.php'">Cerrar Sesión</button>
    </aside>

    <main class="chat-area">
        <div id="header-chat" class="chat-header">Selecciona un contacto para iniciar</div>
        
        <div id="box-mensajes" class="messages-box">
            <div class="empty-state">Inicia una conversación segura y cifrada.</div>
        </div>

        <form id="form-envio" onsubmit="enviarMensaje(event)" class="message-form" style="display:none;">
            <label for="input-file" class="attach-btn">📎</label>
            <input type="file" id="input-file" hidden onchange="enviarArchivo(this)">
            
            <input type="text" id="input-msj" placeholder="Escribe un mensaje..." autocomplete="off">
            <button type="submit" id="btn-send">Enviar</button>
        </form>
    </main>
</div>

<div id="modal-ajustes" class="modal-overlay">
    <div class="modal-content">
        <span class="close-modal" onclick="cerrarAjustes()">&times;</span>
        <h3>Configuración de Perfil</h3>
        
        <div class="preview-container">
            <img id="img-previa-ajustes" src="uploads/<?= $mi_foto ?>" class="profile-preview-img">
            <video id="video-camara" autoplay></video>
            <canvas id="canvas-foto" hidden></canvas>
        </div>

        <div class="cam-controls">
            <button type="button" onclick="activarCamara()" id="btn-abrir-cam">Usar Cámara</button>
            <button type="button" onclick="tomarFoto()" id="btn-capturar" style="display:none;">Tomar Foto</button>
        </div>

        <form onsubmit="actualizarPerfil(event)">
            <label for="nueva-foto" class="file-label-link">Cambiar imagen desde archivo</label>
            <input type="file" id="nueva-foto" hidden onchange="previsualizar(this)">
            
            <input type="text" id="nuevo-nombre" value="<?= htmlspecialchars($mi_nombre) ?>" required>
            <button type="submit" id="btn-save-profile" class="btn-primary">Guardar Cambios</button>
        </form>
    </div>
</div>

<script src="sw-register.js"></script>
<script>
/** * GLOBALES Y CHAT
 */
let receptorActual = null;
let cronometro = null;
let streamCamara = null;
let fotoCapturadaBlob = null;
const ID_IA = 120001;

function cambiarChat(el) {
    receptorActual = el.getAttribute('data-id');
    const nombre = el.getAttribute('data-nombre');
    const foto = el.querySelector('img').src;

    document.getElementById('header-chat').innerHTML = `<img src="${foto}" class="header-avatar"> <span>${nombre}</span>`;
    document.getElementById('form-envio').style.display = 'flex';
    document.getElementById('box-mensajes').innerHTML = '';

    document.querySelectorAll('.user-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');

    refrescar();
    if(cronometro) clearInterval(cronometro);
    if(receptorActual != ID_IA) cronometro = setInterval(refrescar, 2000);
}

async function refrescar() {
    if(!receptorActual || receptorActual == ID_IA) return;
    try {
        const r = await fetch(`mensajes.php?action=leer&con=${receptorActual}`);
        const html = await r.text();
        const box = document.getElementById('box-mensajes');
        box.innerHTML = html;
        box.scrollTop = box.scrollHeight;
    } catch (e) { console.error("Error sync", e); }
}

/** * ENVÍOS
 */
async function enviarMensaje(e) {
    e.preventDefault();
    const input = document.getElementById('input-msj');
    const texto = input.value.trim();
    if(!texto) return;

    if(receptorActual == ID_IA) {
        mostrarLocal("Tú", texto, "mi-msj");
        input.value = '';
        const r = await fetch('ia.php', { method: 'POST', body: JSON.stringify({mensaje: texto}) });
        const d = await r.json();
        mostrarLocal("IA", d.respuesta, "otro-msj");
        return;
    }

    await fetch('mensajes.php?action=enviar', {
        method: 'POST',
        body: JSON.stringify({receptor_id: receptorActual, mensaje: texto})
    });
    input.value = '';
    refrescar();
}

async function enviarArchivo(input) {
    if (!input.files[0] || !receptorActual) return;
    const fd = new FormData();
    fd.append('archivo', input.files[0]);
    fd.append('receptor_id', receptorActual);
    await fetch('mensajes.php?action=enviar', { method: 'POST', body: fd });
    input.value = '';
    refrescar();
}

function mostrarLocal(u, t, c) {
    const box = document.getElementById('box-mensajes');
    box.innerHTML += `<div class="mensaje ${c}"><strong>${u}:</strong><br>${t}</div>`;
    box.scrollTop = box.scrollHeight;
}

/** * PERFIL Y CÁMARA
 */
function abrirAjustes() { document.getElementById('modal-ajustes').style.display = 'flex'; }
function cerrarAjustes() { detenerCamara(); document.getElementById('modal-ajustes').style.display = 'none'; }

async function activarCamara() {
    try {
        streamCamara = await navigator.mediaDevices.getUserMedia({ video: true });
        const v = document.getElementById('video-camara');
        v.srcObject = streamCamara;
        v.style.display = 'block';
        document.getElementById('img-previa-ajustes').style.display = 'none';
        document.getElementById('btn-abrir-cam').style.display = 'none';
        document.getElementById('btn-capturar').style.display = 'inline-block';
    } catch (e) { alert("Cámara no disponible"); }
}

function tomarFoto() {
    const v = document.getElementById('video-camara');
    const c = document.getElementById('canvas-foto');
    c.width = v.videoWidth; c.height = v.videoHeight;
    c.getContext('2d').drawImage(v, 0, 0);
    c.toBlob(b => {
        fotoCapturadaBlob = b;
        document.getElementById('img-previa-ajustes').src = URL.createObjectURL(b);
        detenerCamara();
    }, 'image/jpeg');
}

function detenerCamara() {
    if(streamCamara) streamCamara.getTracks().forEach(t => t.stop());
    document.getElementById('video-camara').style.display = 'none';
    document.getElementById('img-previa-ajustes').style.display = 'block';
    document.getElementById('btn-abrir-cam').style.display = 'inline-block';
    document.getElementById('btn-capturar').style.display = 'none';
}

function previsualizar(i) {
    if (i.files[0]) {
        fotoCapturadaBlob = null;
        let r = new FileReader();
        r.onload = e => document.getElementById('img-previa-ajustes').src = e.target.result;
        r.readAsDataURL(i.files[0]);
    }
}

async function actualizarPerfil(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-save-profile');
    btn.disabled = true; btn.innerText = "Guardando...";

    const fd = new FormData();
    const fArch = document.getElementById('nueva-foto').files[0];
    fd.append('nombre', document.getElementById('nuevo-nombre').value);
    
    if (fArch) fd.append('foto_perfil', fArch);
    else if (fotoCapturadaBlob) fd.append('foto_perfil', fotoCapturadaBlob, 'perfil.jpg');

    const r = await fetch('index.php?action=actualizar_perfil', { method: 'POST', body: fd });
    const res = await r.json();
    if(res.status === 'success') location.reload();
}
</script>
</body>
</html>