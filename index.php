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

    if(!num || !datos.nombre || !datos.correo) return alert("Llena los campos");

    try {
        // Usamos ruta relativa para que funcione en cualquier dominio de Render
        const res = await fetch('./index.php?action=registro', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(datos)
        });

        if (!res.ok) throw new Error("Error en la respuesta del servidor");

        const result = await res.json();

        if (result.status === 'success') {
            alert(`¡Registro Exitoso!\nTu clave es: ${result.temp_pass}`);

            // ABRIR WHATSAPP
            const msg = `Hola ${result.nombre}, mi contraseña es: ${result.temp_pass}`;
            const urlWA = `https://wa.me/${result.telefono}?text=${encodeURIComponent(msg)}`;
            
            // Forzar apertura
            const win = window.open(urlWA, '_blank');
            if (!win) {
                alert("Permite las ventanas emergentes para recibir tu contraseña por WhatsApp.");
                window.location.href = urlWA; // Redirección directa si falla el popup
            }
        } else {
            alert("Error: " + result.message);
        }
    } catch (e) {
        console.error(e);
        alert("No se pudo conectar con el servidor. Revisa si subiste el index.php correctamente.");
    }
}