const express = require('express');
const mysql = require('mysql2');
const cors = require('cors');
const bcrypt = require('bcrypt');
const nodemailer = require('nodemailer');
const path = require('path');
require('dotenv').config();

const app = express();

// Configuración de Middlewares
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(__dirname)); // Sirve index.html y style.css desde la raíz

// 1. Conexión Segura a TiDB Cloud
const db = mysql.createConnection({
    host: 'gateway01.us-east-1.prod.aws.tidbcloud.com',
    port: 4000,
    user: 'MPefCA2vQ18cTr4.root', 
    password: 'P6IKI4BtZ5q5OSGg', // contraseña generada en TiDB
    database: 'chatweb',
    ssl: {
        minVersion: 'TLSv1.2',
        rejectUnauthorized: true
        // Si descargaste el certificado .pem, usa: ca: fs.readFileSync('./isrgrootx1.pem')
    }
});

db.connect(err => {
    if (err) {
        console.error("Error conectando a TiDB:", err.message);
        return;
    }
    console.log("Conectado a TiDB Cloud con éxito [ChatWeb]");
});

// 2. Configuración de Nodemailer (Envío de contraseñas)
const transporter = nodemailer.createTransport({
    service: 'gmail',
    auth: {
        user: 'chatweb545@gmail.com',
        pass: 'fcxghxhubjnsukjn' 
    }
});

// 3. Rutas de la Aplicación

// Cargar la página principal
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

// REGISTRO ACTUALIZADO: Manejo de duplicados y confirmación
app.post('/registro', async (req, res) => {
    const { nombre, ap_paterno, ap_materno, telefono, correo } = req.body;
    
    try {
        const tempPassword = Math.random().toString(36).slice(-8);
        const hash = await bcrypt.hash(tempPassword, 10);

        const sql = `INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, telefono, correo, password_hash) 
                     VALUES (?, ?, ?, ?, ?, ?)`;
        
        db.query(sql, [nombre, ap_paterno, ap_materno, telefono, correo, hash], (err) => {
            if (err) {
                // Si el error es por correo duplicado (Código 1062 en MySQL/TiDB)
                if (err.errno === 1062) {
                    return res.status(400).send("Este correo ya está registrado. Por favor, revisa tu bandeja de entrada para ver tus datos de acceso.");
                }
                return res.status(500).send("Error en el registro: " + err.message);
            }

            const mailOptions = {
                from: 'ChatWeb <tu_correo@gmail.com>',
                to: correo,
                subject: 'Bienvenido a ChatWeb - Tus Datos de Acceso',
                html: `
                    <h2>¡Hola ${nombre}!</h2>
                    <p>Te has registrado exitosamente en <b>ChatWeb</b>.</p>
                    <p>Tus datos de acceso son:</p>
                    <ul>
                        <li><b>Correo:</b> ${correo}</li>
                        <li><b>Contraseña Temporal:</b> ${tempPassword}</li>
                    </ul>
                    <p>Inicia sesión ahora en http://localhost:3000</p>
                `
            };

            transporter.sendMail(mailOptions, (error) => {
                if (error) return res.status(500).send("Usuario creado, pero hubo un error al enviar el correo.");
                res.send("¡Registro exitoso! Te hemos enviado un correo con tus datos de acceso.");
            });
        });
    } catch (error) {
        res.status(500).send("Error interno del servidor.");
    }
});

// LOGIN: Verifica correo y compara hash de contraseña
app.post('/login', (req, res) => {
    const { correo, password } = req.body;

    db.query('SELECT * FROM usuarios WHERE correo = ?', [correo], async (err, results) => {
        if (err) return res.status(500).send("Error en la base de datos");
        
        if (results.length === 0) {
            return res.status(401).send("El correo no está registrado.");
        }

        const user = results[0];
        const match = await bcrypt.compare(password, user.password_hash);

        if (match) {
            // Login exitoso: enviamos los datos básicos (sin el hash)
            res.json({ 
                status: "success", 
                message: "Bienvenido",
                user: {
                    id: user.id_usuario,
                    nombre: user.nombre,
                    correo: user.correo
                }
            });
        } else {
            res.status(401).send("Contraseña incorrecta.");
        }
    });
});

// Iniciar el servidor en el puerto 3000
const PORT = 3000;
app.listen(PORT, () => {
    console.log(`Servidor ChatWeb activo en: http://localhost:${PORT}`);
});