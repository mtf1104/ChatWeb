const express = require('express');
const mysql = require('mysql2');
const cors = require('cors');
const bcrypt = require('bcrypt');
const nodemailer = require('nodemailer');
const path = require('path');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());
app.use(express.static(__dirname)); // Sirve index.html desde la raíz

// Conexión a TiDB Cloud usando tus credenciales actualizadas
const db = mysql.createConnection({
    host: 'gateway01.us-east-1.prod.aws.tidbcloud.com',
    port: 4000,
    user: 'MPefCA2vQ18cTr4.root', // Usuario de tu captura nueva
    password: 'P6IKI4BtZ5q5OSGg', // Genera uno nuevo en TiDB Cloud
    database: 'chatweb',
    ssl: {
        minVersion: 'TLSv1.2',
        rejectUnauthorized: true
    }
});

db.connect(err => {
    if (err) throw err;
    console.log("Conectado a TiDB Cloud con éxito");
});

// Configuración de correo (Gmail)
const transporter = nodemailer.createTransport({
    service: 'gmail',
    auth: {
        user: 'tu_correo@gmail.com',
        pass: 'tu_clave_de_aplicacion' 
    }
});

// Ruta para cargar el HTML
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

// REGISTRO: Genera clave, guarda hash y envía correo
app.post('/registro', async (req, res) => {
    const { nombre, ap_paterno, ap_materno, telefono, correo } = req.body;
    const tempPassword = Math.random().toString(36).slice(-8); // Contraseña aleatoria
    const hash = await bcrypt.hash(tempPassword, 10);

    const query = `INSERT INTO usuarios (nombre, apellido_paterno, apellido_materno, telefono, correo, password_hash) VALUES (?, ?, ?, ?, ?, ?)`;
    
    db.query(query, [nombre, ap_paterno, ap_materno, telefono, correo, hash], (err) => {
        if (err) return res.status(500).send("Error: " + err.message);

        const mailOptions = {
            from: 'tu_correo@gmail.com',
            to: correo,
            subject: 'Tu Clave de ChatWeb',
            text: `Hola ${nombre}, tu contraseña es: ${tempPassword}`
        };

        transporter.sendMail(mailOptions, (error) => {
            if (error) return res.status(500).send("Error al enviar correo");
            res.send("Usuario registrado. Revisa tu correo.");
        });
    });
});

// LOGIN: Verifica correo y contraseña
app.post('/login', (req, res) => {
    const { correo, password } = req.body;
    db.query('SELECT * FROM usuarios WHERE correo = ?', [correo], async (err, results) => {
        if (err || results.length === 0) return res.status(401).send("No encontrado");
        const match = await bcrypt.compare(password, results[0].password_hash);
        if (match) res.json({ status: "ok", user: results[0] });
        else res.status(401).send("Clave incorrecta");
    });
});

app.listen(3000, () => console.log("Servidor corriendo en http://localhost:3000"));