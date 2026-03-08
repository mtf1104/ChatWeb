<?php
// Configuración del cifrado
define('METODO', 'aes-256-cbc');
// IMPORTANTE: Cambia esta clave por una frase secreta larga y segura
define('CLAVE_SECRETA', 'Tu_Frase_Secreta_Para_ChatWeb_2026_UBAM'); 

/**
 * Cifra un texto plano
 */
function cifrarMensaje($mensaje) {
    // Generar un IV (Vector de Inicialización) aleatorio para mayor seguridad
    $iv_longitud = openssl_cipher_iv_length(METODO);
    $iv = openssl_random_pseudo_bytes($iv_longitud);
    
    // Cifrar los datos
    $datos_cifrados = openssl_encrypt($mensaje, METODO, CLAVE_SECRETA, 0, $iv);
    
    // Unimos el IV con los datos cifrados (codificado en base64 para que quepa en la BD)
    return base64_encode($iv . $datos_cifrados);
}

/**
 * Descifra un texto que viene de la BD
 */
function descifrarMensaje($mensaje_completo) {
    $mensaje_completo = base64_decode($mensaje_completo);
    $iv_longitud = openssl_cipher_iv_length(METODO);
    
    // Extraer el IV y el contenido cifrado
    $iv = substr($mensaje_completo, 0, $iv_longitud);
    $datos_cifrados = substr($mensaje_completo, $iv_longitud);
    
    return openssl_decrypt($datos_cifrados, METODO, CLAVE_SECRETA, 0, $iv);
}
?>