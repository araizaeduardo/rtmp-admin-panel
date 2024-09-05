#!/bin/bash

# Obtener la ruta absoluta del directorio del script
script_dir="/app"

# Obtener la fecha y hora actual
now=$(date +"%Y-%m-%d %H:%M:%S")

# Consultar la base de datos para ver si hay transmisiones programadas
schedules=$(sqlite3 "$script_dir/db/rtmp_manager.db" "SELECT * FROM schedules WHERE broadcast_time <= '$now' AND broadcast_time > datetime('$now', '-5 minutes') AND status != 'executed'")

# Iterar sobre las transmisiones programadas
while IFS='|' read -r id video_path broadcast_time youtube_rtmp facebook_rtmp custom_rtmp status
do
    # Verificar si el archivo de video existe
    if [ ! -f "$video_path" ]; then
        echo "Error: El archivo de video no existe: $video_path"
        sqlite3 "$script_dir/db/rtmp_manager.db" "UPDATE schedules SET status = 'error' WHERE id = $id"
        continue
    fi

    # Iniciar la transmisión RTMP para cada servicio configurado
    if [ -n "$youtube_rtmp" ]; then
        ffmpeg -re -i "$video_path" -c copy -f flv "$youtube_rtmp" &
        youtube_pid=$!
    fi
    if [ -n "$facebook_rtmp" ]; then
        # Usar stunnel para Facebook
        ffmpeg -re -i "$video_path" -c copy -f flv "rtmp://127.0.0.1:1936/rtmp/$facebook_rtmp" &
        facebook_pid=$!
    fi
    if [ -n "$custom_rtmp" ]; then
        ffmpeg -re -i "$video_path" -c copy -f flv "$custom_rtmp" &
        custom_pid=$!
    fi

    # Esperar un momento y verificar si los procesos de FFmpeg siguen en ejecución
    sleep 10
    if { [ -n "$youtube_pid" ] && ! kill -0 $youtube_pid 2>/dev/null; } || \
       { [ -n "$facebook_pid" ] && ! kill -0 $facebook_pid 2>/dev/null; } || \
       { [ -n "$custom_pid" ] && ! kill -0 $custom_pid 2>/dev/null; }; then
        echo "Error: La transmisión falló para el schedule ID: $id"
        sqlite3 "$script_dir/db/rtmp_manager.db" "UPDATE schedules SET status = 'error' WHERE id = $id"
    else
        sqlite3 "$script_dir/db/rtmp_manager.db" "UPDATE schedules SET status = 'executed' WHERE id = $id"
        echo "Transmisión iniciada para el schedule ID: $id"
    fi
done <<< "$schedules"

# Limpiar transmisiones antiguas (más de 1 día)
sqlite3 "$script_dir/db/rtmp_manager.db" "DELETE FROM schedules WHERE broadcast_time < datetime('now', '-1 day')"