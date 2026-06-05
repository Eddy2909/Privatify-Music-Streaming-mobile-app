<?php

declare(strict_types=1);

final class UploadService
{
    public function handleUploadedFiles(int $userId, array $files, array $meta = []): array
    {
        $results = [];
        $normalized = $this->normalizeFiles($files);
        foreach ($normalized as $file) {
            try {
                $results[] = ['ok' => true, 'track' => $this->storeOne($userId, $file, $meta)];
            } catch (Throwable $e) {
                $results[] = ['ok' => false, 'filename' => (string) ($file['name'] ?? 'unbekannt'), 'message' => $e->getMessage()];
            }
        }
        return $results;
    }

    private function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $normalized = [];
        foreach ($files['name'] as $i => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $normalized;
    }

    private function storeOne(int $userId, array $file, array $meta): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->uploadErrorMessage((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('Upload wurde nicht als gültiger HTTP-Upload erkannt.');
        }
        $max = (int) Config::get('app.upload_max_bytes', 104857600);
        $size = (int) $file['size'];
        if ($size <= 0 || $size > $max) {
            throw new RuntimeException('Datei ist leer oder größer als das konfigurierte Limit von ' . bytes_human($max) . '.');
        }

        $original = clean_string((string) $file['name'], 255);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension !== 'mp3') {
            throw new RuntimeException('Nur MP3-Dateien sind erlaubt.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        $allowed = (array) Config::get('app.allowed_mime_types', []);
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Dateityp wurde abgelehnt: ' . $mime);
        }

        $hash = hash_file('sha256', (string) $file['tmp_name']);
        $existing = Db::pdo()->prepare('SELECT id, title FROM tracks WHERE sha256 = :sha256 AND deleted_at IS NULL LIMIT 1');
        $existing->execute(['sha256' => $hash]);
        if ($dup = $existing->fetch()) {
            throw new RuntimeException('Diese Datei existiert bereits als „' . (string) $dup['title'] . '“.');
        }

        $storageDir = rtrim((string) Config::get('app.storage_path'), '/\\');
        if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true)) {
            throw new RuntimeException('Storage-Verzeichnis konnte nicht angelegt werden.');
        }
        if (!is_writable($storageDir)) {
            throw new RuntimeException('Storage-Verzeichnis ist nicht beschreibbar.');
        }

        $storageName = bin2hex(random_bytes(24)) . '.mp3';
        $target = $storageDir . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }
        @chmod($target, 0640);

        $title = clean_string((string) ($meta['title'] ?? ''), 255);
        if ($title === '') {
            $title = clean_string(pathinfo($original, PATHINFO_FILENAME), 255);
        }
        $artist = clean_string((string) ($meta['artist'] ?? ''), 255);
        $album = clean_string((string) ($meta['album'] ?? ''), 255);
        $genre = clean_string((string) ($meta['genre'] ?? ''), 120);
        $year = null;
        $yearRaw = trim((string) ($meta['year'] ?? ''));
        if ($yearRaw !== '') {
            $year = filter_var($yearRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1800, 'max_range' => 2200]]);
            if ($year === false) {
                $year = null;
            }
        }

        try {
            $id = (new TrackService())->create([
                'user_id' => $userId,
                'title' => $title,
                'artist' => $artist,
                'album' => $album,
                'genre' => $genre,
                'year' => $year,
                'original_filename' => $original,
                'storage_filename' => $storageName,
                'mime_type' => $mime,
                'size_bytes' => $size,
                'sha256' => $hash,
            ]);
        } catch (Throwable $e) {
            @unlink($target);
            throw $e;
        }

        $track = (new TrackService())->getOwned($userId, $id);
        if (!$track) {
            throw new RuntimeException('Track wurde gespeichert, konnte aber nicht geladen werden.');
        }
        return (new TrackService())->formatTrack($track);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet das Serverlimit.',
            UPLOAD_ERR_PARTIAL => 'Upload wurde nur teilweise übertragen.',
            UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Serververzeichnis fehlt.',
            UPLOAD_ERR_CANT_WRITE => 'Server konnte die Datei nicht schreiben.',
            UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt.',
            default => 'Unbekannter Uploadfehler.',
        };
    }
}
