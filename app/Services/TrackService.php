<?php

declare(strict_types=1);

final class TrackService
{
    public function list(int $userId, array $filters = []): array
    {
        $where = ['user_id = :user_id', 'deleted_at IS NULL'];
        $params = ['user_id' => $userId];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(title LIKE :q OR artist LIKE :q OR album LIKE :q OR genre LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        if (!empty($filters['favorite'])) {
            $where[] = 'favorite = 1';
        }

        $sort = (string) ($filters['sort'] ?? 'newest');
        $orderBy = match ($sort) {
            'title' => 'title ASC, artist ASC, created_at DESC',
            'artist' => 'artist ASC, title ASC, created_at DESC',
            'popular' => 'play_count DESC, last_played_at DESC, created_at DESC',
            'played' => 'last_played_at DESC, created_at DESC',
            default => 'created_at DESC',
        };

        $limit = min(max((int) ($filters['limit'] ?? 200), 1), 500);
        $sql = 'SELECT id, title, artist, album, genre, year, original_filename, size_bytes, duration_seconds, favorite, play_count, last_played_at, created_at
                FROM tracks WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT ' . $limit;
        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getOwned(int $userId, int $trackId): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM tracks WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $trackId, 'user_id' => $userId]);
        $track = $stmt->fetch();
        return $track ?: null;
    }

    public function stats(int $userId): array
    {
        $stmt = Db::pdo()->prepare('SELECT COUNT(*) AS total_tracks, COALESCE(SUM(size_bytes),0) AS storage_bytes, COALESCE(SUM(play_count),0) AS total_plays, COALESCE(SUM(favorite),0) AS favorites FROM tracks WHERE user_id = :user_id AND deleted_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
        $stats = $stmt->fetch() ?: [];
        return [
            'total_tracks' => (int) ($stats['total_tracks'] ?? 0),
            'storage_bytes' => (int) ($stats['storage_bytes'] ?? 0),
            'storage_human' => bytes_human((int) ($stats['storage_bytes'] ?? 0)),
            'total_plays' => (int) ($stats['total_plays'] ?? 0),
            'favorites' => (int) ($stats['favorites'] ?? 0),
        ];
    }

    public function create(array $data): int
    {
        $stmt = Db::pdo()->prepare('INSERT INTO tracks (user_id, title, artist, album, genre, year, original_filename, storage_filename, mime_type, size_bytes, sha256)
            VALUES (:user_id, :title, :artist, :album, :genre, :year, :original_filename, :storage_filename, :mime_type, :size_bytes, :sha256)');
        $stmt->execute([
            'user_id' => (int) $data['user_id'],
            'title' => $data['title'],
            'artist' => $data['artist'] ?: null,
            'album' => $data['album'] ?: null,
            'genre' => $data['genre'] ?: null,
            'year' => $data['year'] ?: null,
            'original_filename' => $data['original_filename'],
            'storage_filename' => $data['storage_filename'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => (int) $data['size_bytes'],
            'sha256' => $data['sha256'],
        ]);
        return (int) Db::pdo()->lastInsertId();
    }

    public function update(int $userId, int $trackId, array $data): array
    {
        $title = clean_string((string) ($data['title'] ?? ''), 255);
        if ($title === '') {
            throw new InvalidArgumentException('Titel darf nicht leer sein.');
        }
        $artist = clean_string((string) ($data['artist'] ?? ''), 255);
        $album = clean_string((string) ($data['album'] ?? ''), 255);
        $genre = clean_string((string) ($data['genre'] ?? ''), 120);
        $yearRaw = trim((string) ($data['year'] ?? ''));
        $year = null;
        if ($yearRaw !== '') {
            $year = filter_var($yearRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1800, 'max_range' => 2200]]);
            if ($year === false) {
                throw new InvalidArgumentException('Jahr ist ungültig.');
            }
        }
        $favorite = !empty($data['favorite']) ? 1 : 0;

        $stmt = Db::pdo()->prepare('UPDATE tracks SET title = :title, artist = :artist, album = :album, genre = :genre, year = :year, favorite = :favorite WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
        $stmt->execute([
            'title' => $title,
            'artist' => $artist !== '' ? $artist : null,
            'album' => $album !== '' ? $album : null,
            'genre' => $genre !== '' ? $genre : null,
            'year' => $year,
            'favorite' => $favorite,
            'id' => $trackId,
            'user_id' => $userId,
        ]);

        $track = $this->getOwned($userId, $trackId);
        if (!$track) {
            throw new RuntimeException('Track nicht gefunden.');
        }
        return $track;
    }

    public function toggleFavorite(int $userId, int $trackId): array
    {
        $track = $this->getOwned($userId, $trackId);
        if (!$track) {
            throw new RuntimeException('Track nicht gefunden.');
        }
        $new = (int) $track['favorite'] === 1 ? 0 : 1;
        $stmt = Db::pdo()->prepare('UPDATE tracks SET favorite = :favorite WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['favorite' => $new, 'id' => $trackId, 'user_id' => $userId]);
        $track['favorite'] = $new;
        return $track;
    }

    public function delete(int $userId, int $trackId): void
    {
        $track = $this->getOwned($userId, $trackId);
        if (!$track) {
            throw new RuntimeException('Track nicht gefunden.');
        }

        Db::pdo()->beginTransaction();
        try {
            $stmt = Db::pdo()->prepare('UPDATE tracks SET deleted_at = NOW() WHERE id = :id AND user_id = :user_id');
            $stmt->execute(['id' => $trackId, 'user_id' => $userId]);
            Db::pdo()->commit();
        } catch (Throwable $e) {
            Db::pdo()->rollBack();
            throw $e;
        }

        $path = rtrim((string) Config::get('app.storage_path'), '/\\') . DIRECTORY_SEPARATOR . $track['storage_filename'];
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function markPlayed(int $userId, int $trackId): void
    {
        $key = 'played_' . $trackId;
        $last = (int) ($_SESSION[$key] ?? 0);
        if (time() - $last < 900) {
            return;
        }
        $_SESSION[$key] = time();
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE tracks SET play_count = play_count + 1, last_played_at = NOW() WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL');
            $stmt->execute(['id' => $trackId, 'user_id' => $userId]);
            $event = $pdo->prepare('INSERT INTO playback_events (user_id, track_id, ip_hash, user_agent_hash) VALUES (:user_id, :track_id, :ip_hash, :ua_hash)');
            $event->execute(['user_id' => $userId, 'track_id' => $trackId, 'ip_hash' => client_ip_hash(), 'ua_hash' => user_agent_hash()]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function formatTrack(array $track): array
    {
        return [
            'id' => (int) $track['id'],
            'title' => (string) $track['title'],
            'artist' => $track['artist'] ?: 'Unbekannter Artist',
            'album' => $track['album'] ?: '',
            'genre' => $track['genre'] ?: '',
            'year' => $track['year'] ? (int) $track['year'] : null,
            'size_bytes' => (int) $track['size_bytes'],
            'size_human' => bytes_human((int) $track['size_bytes']),
            'favorite' => (int) $track['favorite'] === 1,
            'play_count' => (int) $track['play_count'],
            'last_played_at' => $track['last_played_at'],
            'created_at' => $track['created_at'],
            'stream_url' => 'stream.php?id=' . (int) $track['id'],
        ];
    }
}
