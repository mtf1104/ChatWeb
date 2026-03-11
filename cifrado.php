<?php
/**
 * CONFIGURACIÓN DE CIFRADO - CHATWEB
 * Este archivo maneja la seguridad de los mensajes en la base de datos.
 */

// Definición de constantes si no existen para evitar errores de re-definición
if (!defined('METODO')) {
    define('METODO', 'aes-256-cbc');
}

if (!defined('CLAVE_SECRETA')) {
    // IMPORTANTE: Mantener esta frase segura y no cambiarla una vez que haya mensajes en la BD,
    // de lo contrario, los mensajes antiguos no podrán descifrarse.
    define('CLAVE_SECRETA', 'Tu_Frase_Secreta_Para_ChatWeb_2026_UBAM'); 
}

/**
 * Cifra un texto plano para guardarlo en la base de datos.
 */
function cifrarMensaje($mensaje) {
    if (empty($mensaje)) return "";

    // Obtener longitud del Vector de Inicialización (IV)
    $iv_longitud = openssl_cipher_iv_length(METODO);
    // Generar un IV aleatorio para que el mismo mensaje cifrado dos veces se vea diferente
    $iv = openssl_random_pseudo_bytes($iv_longitud);
    
    // Cifrar los datos
    $datos_cifrados = openssl_encrypt($mensaje, METODO, CLAVE_SECRETA, 0, $iv);
    
    // Concatenar IV + Datos y codificar en base64 para almacenamiento seguro en SQL
    return base64_encode($iv . $datos_cifrados);
}

/**
 * Descifra una cadena base64 proveniente de la base de datos.
 */
function descifrarMensaje($cadena_base64) {
    if (empty($cadena_base64)) return "";

    // Decodificar el contenedor base64
    $datos_completos = base64_decode($cadena_base64);
    $iv_longitud = openssl_cipher_iv_length(METODO);
    
    // Separar el IV del contenido cifrado
    $iv = substr($datos_completos, 0, $iv_longitud);
    $datos_cifrados = substr($datos_completos, $iv_longitud);
    
    // Intentar descifrar
    $resultado = openssl_decrypt($datos_cifrados, METODO, CLAVE_SECRETA, 0, $iv);
    
    return $resultado ? $resultado : "[Error al descifrar mensaje]";
}
?>