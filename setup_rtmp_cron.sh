#!/bin/bash

# Obtener la ruta absoluta del directorio del script
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Verificar si el script rtmp_scheduler.sh existe
if [ ! -f "$script_dir/rtmp_scheduler.sh" ]; then
    echo "Error: El archivo rtmp_scheduler.sh no existe en el directorio del script."
    exit 1
fi

# Asegurarse de que rtmp_scheduler.sh tenga permisos de ejecución
chmod +x "$script_dir/rtmp_scheduler.sh"

# Configurar stunnel
if [ ! -f "/etc/stunnel/stunnel.conf" ]; then
    echo "Configurando stunnel..."
    sudo tee /etc/stunnel/stunnel.conf > /dev/null <<EOT
[rtmp-out]
client = yes
accept = 127.0.0.1:1936
connect = rtmp-server.example.com:443
verify = 0
EOT
    sudo systemctl restart stunnel4
fi

# Agregar el cronjob para ejecutar el script cada 5 minutos con la ruta completa
(crontab -l 2>/dev/null | grep -v "$script_dir/rtmp_scheduler.sh"; echo "*/5 * * * * $script_dir/rtmp_scheduler.sh") | crontab - || {
    echo "Error: No se pudo configurar el cronjob para RTMP Scheduler."
    exit 1
}

# Verificar si el cronjob se agregó correctamente
if crontab -l | grep -q "$script_dir/rtmp_scheduler.sh"; then
    echo "Cronjob para RTMP Scheduler configurado exitosamente."
else
    echo "Advertencia: El cronjob se agregó, pero no se pudo verificar. Por favor, revise manualmente con 'crontab -l'."
fi

# Verificar la conexión con rtmp-admin-panel.php
admin_panel_url="http://localhost/rtmp-admin-panel.php"
if curl -s "$admin_panel_url" | grep -q "RTMP Admin Panel"; then
    echo "Conexión exitosa con rtmp-admin-panel.php."
else
    echo "Advertencia: No se pudo conectar con rtmp-admin-panel.php. Verifique la configuración."
fi

# Verificar la conexión stunnel
if nc -zv 127.0.0.1 1936 &>/dev/null; then
    echo "Conexión stunnel configurada correctamente en 127.0.0.1:1936."
else
    echo "Advertencia: No se pudo establecer conexión con stunnel en 127.0.0.1:1936. Verifique la configuración de stunnel."
fi

echo "Configuración completada. RTMP Scheduler se ejecutará cada 5 minutos y utilizará stunnel para conexiones seguras."