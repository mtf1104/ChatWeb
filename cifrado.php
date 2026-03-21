<?php
/**
 * ChatWeb - Motor de Cifrado AES-256
 * Seguridad de Capa de Datos para UBAM 2026
 */

// Configuración de Seguridad
if (!defined('METODO')) define('METODO', 'aes-256-cbc');

// IMPORTANTE: No cambies esta clave si ya hay mensajes guardados o se perderán.
if (!defined('CLAVE_SECRETA')) {
    define('CLAVE_SECRETA', 'Tu_Frase_Secreta_Para_ChatWeb_2026_UBAM');
}

/**
 * Cifra contenido para almacenamiento seguro en TiDB.
 * Retorna una cadena codificada en Base64 que incluye el IV.
 */
function cifrarMensaje($mensaje) {
    if (empty($mensaje)) return "";

    try {
        $iv_len = openssl_cipher_iv_length(METODO);
        $iv = openssl_random_pseudo_bytes($iv_len);
        
        $cifrado = openssl_encrypt($mensaje, METODO, CLAVE_SECRETA, 0, $iv);
        
        // El IV se adjunta al inicio para que el descifrado sea posible después
        return base64_encode($iv . $cifrado);
    } catch (Exception $e) {
        return "";
    }
}

/**
 * Descifra contenido proveniente de la base de datos.
 */
function descifrarMensaje($payload_base64) {
    if (empty($payload_base64)) return "";

    try {
        $datos = base64_decode($payload_base64);
        $iv_len = openssl_cipher_iv_length(METODO);
        
        // Extraer el IV (primeros bytes) y el cuerpo del mensaje
        $iv = substr($datos, 0, $iv_len);
        $cuerpo = substr($datos, $iv_len);
        
        $descifrado = openssl_decrypt($cuerpo, METODO, CLAVE_SECRETA, 0, $iv);
        
        return $descifrado !== false ? $descifrado : "[Contenido Cifrado]";
    } catch (Exception $e) {
        return "[Error de Integridad]";
    }
}