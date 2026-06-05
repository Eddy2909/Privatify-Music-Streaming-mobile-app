<?php

declare(strict_types=1);

final class PlaylistService
{
    public function list(int $userId): array
    {
        $stmt = Db::pdo()->prepare('SELECT p.id, p.name, p.description, p.created_at, COUNT(pt.track_id) AS track_count
            FROM playlists p
            LEFT JOIN playlist_tracks pt ON pt.playlist_id = p.id
            WHERE p.user_id = :user_id
            GROUP BY p.id
            ORDER BY p.created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return array_map([$this, 'formatPlaylist'], $stmt->fetchAll());
    }

    public function create(int $userId, string $name, string $description = ''): array
    {
        $name = clean_string($name, 160);
        $description = clean_string($description, 500);
        if ($name === '') {
            throw new InvalidArgumentException('Playlist-Name darf nicht leer sein.');
        }
        $stmt = Db::pdo()->prepare('INSERT INTO playlists (user_id, name, description) VALUES (:user_id, :name, :description)');
        $stmt->execute(['user_id' => $userId, 'name' => $name, 'description' => $description ?: null]);
        return $this->get($userId, (int) Db::pdo()->lastInsertId());
    }

    public function get(int $userId, int $playlistId): array
    {
        $stmt = Db::pdo()->prepare('SELECT p.id, p.name, p.description, p.created_at, COUNT(pt.track_id) AS track_count
            FROM playlists p LEFT JOIN playlist_tracks pt ON pt.playlist_id = p.id
            WHERE p.id = :id AND p.user_id = :user_id GROUP BY p.id LIMIT 1');
        $stmt->execute(['id' => $playlistId, 'user_id' => $userId]);
        $playlist = $stmt->fetch();
        if (!$playlist) {
            throw new RuntimeException('Playlist nicht gefunden.');
        }
        return $this->formatPlaylist($playlist);
    }

    public function tracks(int $userId, int $playlistId): array
    {
        $this->get($userId, $playlistId);
        $stmt = Db::pdo()->prepare('SELECT t.id, t.title, t.artist, t.album, t.genre, t.year, t.original_filename, t.size_bytes, t.duration_seconds, t.favorite, t.play_count, t.last_played_at, t.created_at
            FROM playlist_tracks pt
            INNER JOIN tracks t ON t.id = pt.track_id AND t.deleted_at IS NULL
            INNER JOIN playlists p ON p.id = pt.playlist_id AND p.user_id = :user_id
            WHERE pt.playlist_id = :playlist_id
            ORDER BY pt.position ASC, pt.added_at ASC');
        $stmt->execute(['user_id' => $userId, 'playlist_id' => $playlistId]);
        $service = new TrackService();
        return array_map([$service, 'formatTrack'], $stmt->fetchAll());
    }

    public function addTrack(int $userId, int $playlistId, int $trackId): void
    {
        $this->get($userId, $playlistId);
        if (!(new TrackService())->getOwned($userId, $trackId)) {
            throw new RuntimeException('Track nicht gefunden.');
        }
        $posStmt = Db::pdo()->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_tracks WHERE playlist_id = :playlist_id');
        $posStmt->execute(['playlist_id' => $playlistId]);
        $position = (int) $posStmt->fetchColumn();
        $stmt = Db::pdo()->prepare('INSERT IGNORE INTO playlist_tracks (playlist_id, track_id, position) VALUES (:playlist_id, :track_id, :position)');
        $stmt->execute(['playlist_id' => $playlistId, 'track_id' => $trackId, 'position' => $position]);
    }

    public function removeTrack(int $userId, int $playlistId, int $trackId): void
    {
        $this->get($userId, $playlistId);
        $stmt = Db::pdo()->prepare('DELETE FROM playlist_tracks WHERE playlist_id = :playlist_id AND track_id = :track_id');
        $stmt->execute(['playlist_id' => $playlistId, 'track_id' => $trackId]);
    }

    public function delete(int $userId, int $playlistId): void
    {
        $stmt = Db::pdo()->prepare('DELETE FROM playlists WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $playlistId, 'user_id' => $userId]);
    }

    private function formatPlaylist(array $playlist): array
    {
        return [
            'id' => (int) $playlist['id'],
            'name' => (string) $playlist['name'],
            'description' => $playlist['description'] ?: '',
            'track_count' => (int) ($playlist['track_count'] ?? 0),
            'created_at' => $playlist['created_at'],
        ];
    }
}
