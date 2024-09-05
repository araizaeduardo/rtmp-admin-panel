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

# Agregar el cronjob para ejecutar el script cada minuto con la ruta completa
(crontab -l 2>/dev/null | grep -v "$script_dir/rtmp_scheduler.sh"; echo "* * * * * $script_dir/rtmp_scheduler.sh") | crontab - || {
    echo "Error: No se pudo configurar el cronjob para RTMP Scheduler."
    exit 1
}

# Verificar si el cronjob se agregó correctamente
if crontab -l | grep -q "$script_dir/rtmp_scheduler.sh"; then
    echo "Cronjob para RTMP Scheduler configurado exitosamente."
else
    echo "Advertencia: El cronjob se agregó, pero no se pudo verificar. Por favor, revise manualmente con 'crontab -l'."
fi