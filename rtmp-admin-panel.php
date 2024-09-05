<?php
session_start();
// Aquí iría la lógica de autenticación

// Conexión a la base de datos
$db_path = __DIR__ . '/rtmp_manager.db';
$db = new SQLite3($db_path);

// Crear tablas si no existen
$db->exec('CREATE TABLE IF NOT EXISTS schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    video_path TEXT,
    broadcast_time DATETIME,
    youtube_rtmp TEXT,
    facebook_rtmp TEXT,
    custom_rtmp TEXT,
    status TEXT DEFAULT "pending"
)');

$db->exec('CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT,
    upload_time DATETIME
)');

// Función para agregar un nuevo schedule
function addSchedule($video_path, $broadcast_time, $youtube_rtmp, $facebook_rtmp, $custom_rtmp) {
    global $db;
    $stmt = $db->prepare('INSERT INTO schedules (video_path, broadcast_time, youtube_rtmp, facebook_rtmp, custom_rtmp) VALUES (:video_path, :broadcast_time, :youtube_rtmp, :facebook_rtmp, :custom_rtmp)');
    $stmt->bindValue(':video_path', validateInput($video_path), SQLITE3_TEXT);
    $stmt->bindValue(':broadcast_time', validateInput($broadcast_time), SQLITE3_TEXT);
    $stmt->bindValue(':youtube_rtmp', validateInput($youtube_rtmp), SQLITE3_TEXT);
    $stmt->bindValue(':facebook_rtmp', validateInput($facebook_rtmp), SQLITE3_TEXT);
    $stmt->bindValue(':custom_rtmp', validateInput($custom_rtmp), SQLITE3_TEXT);
    return $stmt->execute();
}

// Función para obtener todos los schedules
function getSchedules() {
    global $db;
    $result = $db->query('SELECT * FROM schedules ORDER BY broadcast_time');
    $schedules = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $schedules[] = $row;
    }
    return $schedules;
}

// Función para subir un video
function uploadVideo($filename) {
    global $db;
    $upload_time = date('Y-m-d H:i:s');
    $stmt = $db->prepare('INSERT INTO videos (filename, upload_time) VALUES (:filename, :upload_time)');
    $stmt->bindValue(':filename', $filename, SQLITE3_TEXT);
    $stmt->bindValue(':upload_time', $upload_time, SQLITE3_TEXT);
    $stmt->execute();
}

// Función para obtener todos los videos
function getVideos() {
    global $db;
    $result = $db->query('SELECT * FROM videos ORDER BY upload_time DESC');
    $videos = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $videos[] = $row;
    }
    return $videos;
}

// Función para eliminar un video
function deleteVideo($id) {
    global $db;
    $stmt = $db->prepare('DELETE FROM videos WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
    
    // También eliminamos el archivo físico
    $video = $db->querySingle("SELECT filename FROM videos WHERE id = $id", true);
    if ($video) {
        $file_path = __DIR__ . '/upload/videos/' . $video['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
}

// Función para obtener un schedule específico
function getSchedule($id) {
    global $db;
    $stmt = $db->prepare('SELECT * FROM schedules WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

// Función para actualizar un schedule
function updateSchedule($id, $video_path, $broadcast_time, $youtube_rtmp, $facebook_rtmp, $custom_rtmp) {
    global $db;
    $stmt = $db->prepare('UPDATE schedules SET video_path = :video_path, broadcast_time = :broadcast_time, youtube_rtmp = :youtube_rtmp, facebook_rtmp = :facebook_rtmp, custom_rtmp = :custom_rtmp WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':video_path', $video_path, SQLITE3_TEXT);
    $stmt->bindValue(':broadcast_time', $broadcast_time, SQLITE3_TEXT);
    $stmt->bindValue(':youtube_rtmp', $youtube_rtmp, SQLITE3_TEXT);
    $stmt->bindValue(':facebook_rtmp', $facebook_rtmp, SQLITE3_TEXT);
    $stmt->bindValue(':custom_rtmp', $custom_rtmp, SQLITE3_TEXT);
    $stmt->execute();
}

// Función para eliminar un schedule
function deleteSchedule($id) {
    global $db;
    $stmt = $db->prepare('DELETE FROM schedules WHERE id = :id');
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->execute();
}

// Función para ejecutar el video manualmente
function executeVideo($scheduleId) {
    global $db;
    $schedule = getSchedule($scheduleId);
    if ($schedule) {
        // Aquí iría la lógica para ejecutar el video
        // Por ejemplo, podrías usar un sistema de colas o iniciar un proceso en segundo plano
        $command = "ffmpeg -re -i " . escapeshellarg($schedule['video_path']) . " -c copy -f flv " . escapeshellarg($schedule['youtube_rtmp']) . " &";
        exec($command);
        
        $stmt = $db->prepare('UPDATE schedules SET status = "executed" WHERE id = :id');
        $stmt->bindValue(':id', $scheduleId, SQLITE3_INTEGER);
        return $stmt->execute();
    }
    return false;
}

// Añadir validación de entradas
function validateInput($input) {
    return htmlspecialchars(strip_tags($input));
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_schedule'])) {
            $video_path = __DIR__ . '/upload/videos/' . validateInput($_POST['video_path']);
            if (addSchedule($video_path, $_POST['broadcast_time'], $_POST['youtube_rtmp'], $_POST['facebook_rtmp'], $_POST['custom_rtmp'])) {
                $_SESSION['message'] = "Programación añadida con éxito.";
            } else {
                throw new Exception("Error al añadir la programación.");
            }
        } elseif (isset($_FILES['video'])) {
            $upload_dir = __DIR__ . '/upload/videos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $filename = basename($_FILES['video']['name']);
            $upload_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['video']['tmp_name'], $upload_file)) {
                uploadVideo($filename);
            }
        } elseif (isset($_POST['delete_video'])) {
            deleteVideo($_POST['video_id']);
        } elseif (isset($_POST['update_schedule'])) {
            $video_path = __DIR__ . '/upload/videos/' . $_POST['video_path'];
            updateSchedule($_POST['schedule_id'], $video_path, $_POST['broadcast_time'], $_POST['youtube_rtmp'], $_POST['facebook_rtmp'], $_POST['custom_rtmp']);
        } elseif (isset($_POST['delete_schedule'])) {
            deleteSchedule($_POST['schedule_id']);
        } elseif (isset($_POST['execute_video'])) {
            executeVideo($_POST['schedule_id']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

$schedules = getSchedules();
$videos = getVideos();

// Si se está editando un schedule, obtenerlo
$editing_schedule = null;
if (isset($_GET['edit_schedule'])) {
    $editing_schedule = getSchedule($_GET['edit_schedule']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración RTMP</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
    <style>
        body { padding-top: 5rem; }
        .form-container { max-width: 500px; margin: auto; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
        <a class="navbar-brand" href="#">Panel RTMP</a>
    </nav>

    <main role="main" class="container">
        <h1 class="mb-4">Panel de Administración RTMP</h1>

        <div class="row">
            <div class="col-md-6">
                <h2><?= $editing_schedule ? 'Editar' : 'Agregar' ?> Programación</h2>
                <form method="post" class="form-container">
                    <input type="hidden" name="<?= $editing_schedule ? 'update_schedule' : 'add_schedule' ?>" value="1">
                    <?php if ($editing_schedule): ?>
                        <input type="hidden" name="schedule_id" value="<?= $editing_schedule['id'] ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <select class="form-control" name="video_path" required>
                            <option value="">Seleccione un video</option>
                            <?php foreach ($videos as $video): ?>
                                <option value="<?= htmlspecialchars($video['filename']) ?>" <?= ($editing_schedule && $editing_schedule['video_path'] == __DIR__ . '/upload/videos/' . $video['filename']) ? 'selected' : '' ?>><?= htmlspecialchars($video['filename']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="datetime-local" class="form-control" name="broadcast_time" required value="<?= $editing_schedule ? date('Y-m-d\TH:i', strtotime($editing_schedule['broadcast_time'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" name="youtube_rtmp" placeholder="RTMP de YouTube" value="<?= $editing_schedule ? htmlspecialchars($editing_schedule['youtube_rtmp']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" name="facebook_rtmp" placeholder="RTMP de Facebook" value="<?= $editing_schedule ? htmlspecialchars($editing_schedule['facebook_rtmp']) : '' ?>">
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" name="custom_rtmp" placeholder="RTMP personalizado" value="<?= $editing_schedule ? htmlspecialchars($editing_schedule['custom_rtmp']) : '' ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= $editing_schedule ? 'Actualizar' : 'Agregar' ?> Programación</button>
                    <?php if ($editing_schedule): ?>
                        <a href="?" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="col-md-6">
                <h2>Subir Video</h2>
                <form method="post" enctype="multipart/form-data" class="form-container">
                    <div class="form-group">
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="customFile" name="video" accept="video/*" required>
                            <label class="custom-file-label" for="customFile">Elegir archivo</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">Subir Video</button>
                </form>
            </div>
        </div>

        <h2 class="mt-5">Programaciones</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>Video</th>
                        <th>Fecha de Transmisión</th>
                        <th>YouTube RTMP</th>
                        <th>Facebook RTMP</th>
                        <th>RTMP Personalizado</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars(basename($schedule['video_path'])) ?>
                            <button class="btn btn-sm btn-info" onclick="showVideo('<?= htmlspecialchars(basename($schedule['video_path'])) ?>')"><i class="fas fa-play"></i></button>
                        </td>
                        <td><?= htmlspecialchars($schedule['broadcast_time']) ?></td>
                        <td><?= htmlspecialchars($schedule['youtube_rtmp']) ?></td>
                        <td><?= htmlspecialchars($schedule['facebook_rtmp']) ?></td>
                        <td><?= htmlspecialchars($schedule['custom_rtmp']) ?></td>
                        <td><?= isset($schedule['status']) ? htmlspecialchars($schedule['status']) : 'Pendiente' ?></td>
                        <td>
                            <a href="?edit_schedule=<?= $schedule['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="delete_schedule" value="1">
                                <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres eliminar esta programación?')"><i class="fas fa-trash"></i></button>
                            </form>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="execute_video" value="1">
                                <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm" <?= isset($schedule['status']) && $schedule['status'] == 'executed' ? 'disabled' : '' ?>><i class="fas fa-rocket"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="mt-5">Videos Subidos</h2>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="thead-dark">
                    <tr>
                        <th>Nombre del Archivo</th>
                        <th>Fecha de Subida</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $video): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($video['filename']) ?>
                            <button class="btn btn-sm btn-info" onclick="showVideo('<?= htmlspecialchars($video['filename']) ?>')"><i class="fas fa-play"></i></button>
                        </td>
                        <td><?= htmlspecialchars($video['upload_time']) ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="delete_video" value="1">
                                <input type="hidden" name="video_id" value="<?= $video['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres eliminar este video?')"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Modal para reproducir video -->
        <div class="modal fade" id="videoModal" tabindex="-1" role="dialog" aria-labelledby="videoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="videoModalLabel">Reproducir Video</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <video id="videoPlayer" width="100%" controls>
                            Su navegador no soporta el tag de video HTML5.
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Actualizar el nombre del archivo seleccionado en el input de subida
        $(".custom-file-input").on("change", function() {
            var fileName = $(this).val().split("\\").pop();
            $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
        });

        function showVideo(filename) {
            var videoPlayer = document.getElementById('videoPlayer');
            videoPlayer.src = 'upload/videos/' + filename;
            $('#videoModal').modal('show');
        }

        $('#videoModal').on('hidden.bs.modal', function (e) {
            var videoPlayer = document.getElementById('videoPlayer');
            videoPlayer.pause();
            videoPlayer.currentTime = 0;
        });
    </script>
</body>
</html>
