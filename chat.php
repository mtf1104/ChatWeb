<?php
session_start();
if (!isset($_SESSION['id_usuario'])) { 
    header("Location: index.html"); 
    exit(); 
}

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
<title>ChatWeb</title>
<link rel="stylesheet" href="style.css">
</head>

<body style="display:block;margin:0;background:#f0f2f5;">

<div style="display:flex;height:100vh;width:100vw;background:white;">

<aside style="width:300px;border-right:1px solid #ddd;display:flex;flex-direction:column;">

<div style="padding:20px;background:#00a884;color:white;">
<h3 style="margin:0;">ChatWeb</h3>
<span><?php echo htmlspecialchars($mi_nombre); ?></span>
</div>

<ul style="list-style:none;padding:0;margin:0;overflow-y:auto;flex-grow:1;">

<?php

$res = $conn->query("SELECT id_usuario,nombre FROM usuarios WHERE id_usuario != $mi_id");

while($u = $res->fetch_assoc()){

$id=$u['id_usuario'];
$nombre=htmlspecialchars($u['nombre']);

echo "<li class='user-item'
data-id='$id'
data-nombre='$nombre'
onclick='cambiarChat(this)'
style='padding:15px;cursor:pointer;border-bottom:1px solid #eee;'>
$nombre
</li>";

}
?>

</ul>

<button onclick="location.href='logout.php'"
style="padding:15px;background:#d9534f;color:white;border:none;cursor:pointer;">
Cerrar Sesión
</button>

</aside>


<main style="flex-grow:1;display:flex;flex-direction:column;background:#efe7dd;">

<div id="header-chat"
style="padding:15px;background:#f0f2f5;font-weight:bold;border-bottom:1px solid #ddd;">
Selecciona un contacto
</div>

<div id="box-mensajes"
style="flex-grow:1;padding:20px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;">
</div>

<form id="form-envio" onsubmit="enviar(event)"
style="display:none;padding:15px;background:#f0f2f5;">

<input type="text"
id="input-msj"
placeholder="Escribe un mensaje..."
style="width:85%;padding:12px;border-radius:20px;border:1px solid #ccc;">

<button type="submit"
style="width:10%;padding:10px;background:#00a884;color:white;border:none;border-radius:5px;margin-left:5px;">
Enviar
</button>

</form>

</main>
</div>


<script>

let receptorActual=null;
let cronometro=null;

const ID_IA = 120001;


function cambiarChat(el){

receptorActual=el.getAttribute('data-id');
const nombre=el.getAttribute('data-nombre');

document.getElementById('header-chat').innerText="Chat con: "+nombre;
document.getElementById('form-envio').style.display='block';

document.querySelectorAll('.user-item').forEach(i=>i.style.background='white');
el.style.background='#e7f5f2';

refrescar();

if(cronometro) clearInterval(cronometro);
cronometro=setInterval(refrescar,2000);
}


async function refrescar(){

if(!receptorActual) return;

if(receptorActual == ID_IA){
return;
}

const r=await fetch('mensajes.php?action=leer&con='+receptorActual);
const html=await r.text();

const box=document.getElementById('box-mensajes');

box.innerHTML=html;
box.scrollTop=box.scrollHeight;

}


async function enviar(e){

e.preventDefault();

const input=document.getElementById('input-msj');
const texto=input.value.trim();

if(!texto) return;


/* SI HABLAN CON IA */

if(receptorActual == ID_IA){

mostrarMensaje("Tú",texto);

const r = await fetch('ia.php',{

method:'POST',
headers:{
"Content-Type":"application/json"
},

body:JSON.stringify({
mensaje:texto
})

});

const data = await r.json();

mostrarMensaje("IA",data.respuesta);

input.value='';
return;

}


/* CHAT NORMAL */

await fetch('mensajes.php?action=enviar',{

method:'POST',
body:JSON.stringify({
receptor_id:receptorActual,
mensaje:texto
})

});

input.value='';
refrescar();

}


/* MOSTRAR MENSAJE IA */

function mostrarMensaje(usuario,texto){

const box=document.getElementById('box-mensajes');

const div=document.createElement('div');

div.style.padding="10px";
div.style.background="#fff";
div.style.borderRadius="10px";
div.style.maxWidth="60%";

div.innerHTML="<b>"+usuario+":</b><br>"+texto;

box.appendChild(div);

box.scrollTop=box.scrollHeight;

}

</script>

</body>
</html>