(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const state = {
    tracks: [], playlists: [], current: null, favoriteOnly: false, q: '', sort: 'newest'
  };
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const text = (el, value) => { if (el) el.textContent = value ?? ''; };

  const trackList = $('#trackList');
  if (!trackList) return;
  const audio = $('#audio');
  const player = $('#player');
  const nowTitle = $('#nowTitle');
  const nowArtist = $('#nowArtist');
  const playPause = $('#playPause');
  const seek = $('#seek');
  const timeLabel = $('#timeLabel');
  const volume = $('#volume');

  state.tracks = JSON.parse(trackList.dataset.tracks || '[]');
  state.playlists = JSON.parse($('#playlistList')?.dataset.playlists || '[]');

  function formData(obj) {
    const fd = new FormData();
    Object.entries(obj).forEach(([k, v]) => fd.append(k, v));
    return fd;
  }

  async function post(url, data) {
    const res = await fetch(url, { method: 'POST', headers: {'X-CSRF-Token': csrf}, body: data });
    const json = await res.json().catch(() => ({ok:false, message:'Ungültige Serverantwort.'}));
    if (!res.ok || !json.ok) throw new Error(json.message || 'Aktion fehlgeschlagen.');
    return json;
  }

  async function refreshTracks() {
    const params = new URLSearchParams({q: state.q, sort: state.sort, favorite: state.favoriteOnly ? '1' : '0'});
    const res = await fetch('api/tracks.php?' + params.toString());
    const json = await res.json();
    if (json.ok) {
      state.tracks = json.tracks;
      renderTracks();
      updateStats(json.stats);
    }
  }

  function updateStats(stats) {
    if (!stats) return;
    text($('#statStorage'), stats.storage_human);
    text($('#statPlays'), String(stats.total_plays));
    text($('#statFavorites'), String(stats.favorites));
  }

  function renderTracks(list = state.tracks) {
    trackList.replaceChildren();
    $('#emptyState').hidden = list.length > 0;
    list.forEach(track => {
      const row = document.createElement('article');
      row.className = 'track-row';
      row.dataset.id = track.id;

      const play = button('small-btn play', '▶', 'Abspielen');
      play.addEventListener('click', () => playTrack(track));
      row.append(play);

      const main = document.createElement('div');
      main.className = 'track-main';
      const title = document.createElement('div'); title.className = 'track-title'; title.textContent = track.title;
      const sub = document.createElement('div'); sub.className = 'track-sub'; sub.textContent = `${track.artist || 'Unbekannter Artist'}${track.album ? ' · ' + track.album : ''}`;
      main.append(title, sub); row.append(main);

      const genre = document.createElement('div'); genre.className = 'hide-mobile';
      const badge = document.createElement('span'); badge.className = 'badge'; badge.textContent = track.genre || 'MP3';
      genre.append(badge); row.append(genre);

      const plays = document.createElement('div'); plays.className = 'hide-mobile'; plays.textContent = `${track.play_count} Plays`; row.append(plays);
      const size = document.createElement('div'); size.className = 'hide-mobile'; size.textContent = track.size_human; row.append(size);

      const actions = document.createElement('div'); actions.className = 'row-actions';
      const fav = button('small-btn', track.favorite ? '♥' : '♡', 'Favorit umschalten');
      fav.addEventListener('click', () => toggleFavorite(track));
      const add = button('small-btn', '+', 'Zu Playlist');
      add.addEventListener('click', () => openPlaylistDialog(track));
      const edit = button('small-btn', '✎', 'Bearbeiten');
      edit.addEventListener('click', () => openEdit(track));
      const del = button('small-btn danger', '×', 'Löschen');
      del.addEventListener('click', () => deleteTrack(track));
      actions.append(fav, add, edit, del); row.append(actions);
      trackList.append(row);
    });
  }

  function renderPlaylists() {
    const wrap = $('#playlistList');
    if (!wrap) return;
    wrap.replaceChildren();
    const select = $('#addPlaylistForm select[name="playlist_id"]');
    if (select) select.replaceChildren();
    state.playlists.forEach(pl => {
      const item = document.createElement('button');
      item.type = 'button'; item.className = 'playlist-item';
      const left = document.createElement('div');
      const strong = document.createElement('strong'); strong.textContent = pl.name;
      const span = document.createElement('span'); span.textContent = `${pl.track_count} Track(s)`;
      left.append(strong, span);
      const arrow = document.createElement('span'); arrow.textContent = '›';
      item.append(left, arrow);
      item.addEventListener('click', () => loadPlaylist(pl.id));
      wrap.append(item);
      if (select) {
        const opt = document.createElement('option'); opt.value = pl.id; opt.textContent = pl.name;
        select.append(opt);
      }
    });
  }

  async function loadPlaylist(id) {
    const res = await fetch('api/playlists.php?id=' + encodeURIComponent(id));
    const json = await res.json();
    if (json.ok) {
      renderTracks(json.tracks);
      $('.library-head h2').textContent = json.playlist.name;
      window.scrollTo({top: document.querySelector('.library-head').offsetTop - 20, behavior: 'smooth'});
    }
  }

  function button(cls, label, title) {
    const b = document.createElement('button'); b.type = 'button'; b.className = cls; b.textContent = label; b.title = title; return b;
  }

  function playTrack(track) {
    state.current = track;
    player.hidden = false;
    audio.src = track.stream_url;
    audio.play().catch(() => {});
    text(nowTitle, track.title); text(nowArtist, track.artist || 'Unbekannter Artist');
    text(playPause, '❚❚');
  }

  playPause?.addEventListener('click', () => {
    if (!state.current) return;
    if (audio.paused) { audio.play(); text(playPause, '❚❚'); } else { audio.pause(); text(playPause, '▶'); }
  });
  audio?.addEventListener('timeupdate', () => {
    if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
    seek.value = Math.round((audio.currentTime / audio.duration) * 1000);
    text(timeLabel, formatTime(audio.currentTime));
  });
  audio?.addEventListener('ended', () => text(playPause, '▶'));
  seek?.addEventListener('input', () => {
    if (Number.isFinite(audio.duration)) audio.currentTime = (Number(seek.value) / 1000) * audio.duration;
  });
  volume?.addEventListener('input', () => { audio.volume = Number(volume.value); });

  function formatTime(seconds) {
    const s = Math.floor(seconds % 60).toString().padStart(2, '0');
    const m = Math.floor(seconds / 60);
    return `${m}:${s}`;
  }

  async function toggleFavorite(track) {
    try {
      const json = await post('api/tracks.php', formData({action:'favorite', id:track.id}));
      const idx = state.tracks.findIndex(t => t.id === track.id);
      if (idx >= 0) state.tracks[idx] = json.track;
      updateStats(json.stats); renderTracks();
    } catch (e) { notify(e.message, false); }
  }

  async function deleteTrack(track) {
    if (!confirm(`„${track.title}“ wirklich löschen? Die MP3 wird vom Server entfernt.`)) return;
    try {
      const json = await post('api/tracks.php', formData({action:'delete', id:track.id}));
      state.tracks = state.tracks.filter(t => t.id !== track.id);
      updateStats(json.stats); renderTracks(); notify('Track gelöscht.', true);
    } catch (e) { notify(e.message, false); }
  }

  function openEdit(track) {
    const dialog = $('#editDialog'); const form = $('#editForm');
    form.id.value = track.id; form.title.value = track.title; form.artist.value = track.artist === 'Unbekannter Artist' ? '' : track.artist;
    form.album.value = track.album || ''; form.genre.value = track.genre || ''; form.year.value = track.year || ''; form.favorite.checked = !!track.favorite;
    dialog.showModal();
  }

  $('#editForm')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const fd = new FormData(ev.currentTarget); fd.append('action', 'update');
    fd.set('favorite', ev.currentTarget.favorite.checked ? '1' : '0');
    try {
      const json = await post('api/tracks.php', fd);
      const idx = state.tracks.findIndex(t => t.id === json.track.id);
      if (idx >= 0) state.tracks[idx] = json.track;
      updateStats(json.stats); renderTracks(); $('#editDialog').close(); notify('Gespeichert.', true);
    } catch (e) { notify(e.message, false); }
  });

  function openPlaylistDialog(track) {
    if (state.playlists.length === 0) { notify('Erstelle zuerst eine Playlist.', false); return; }
    const form = $('#addPlaylistForm'); form.track_id.value = track.id; renderPlaylists(); $('#playlistDialog').showModal();
  }

  $('#addPlaylistForm')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const fd = new FormData(ev.currentTarget); fd.append('action', 'add');
    try {
      const json = await post('api/playlist-tracks.php', fd);
      state.playlists = json.playlists; renderPlaylists(); $('#playlistDialog').close(); notify('Zur Playlist hinzugefügt.', true);
    } catch (e) { notify(e.message, false); }
  });

  $('#playlistForm')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const fd = new FormData(ev.currentTarget); fd.append('action', 'create');
    try {
      const json = await post('api/playlists.php', fd);
      state.playlists = json.playlists; renderPlaylists(); ev.currentTarget.reset(); notify('Playlist erstellt.', true);
    } catch (e) { notify(e.message, false); }
  });

  const uploadZone = $('#uploadForm'); const fileInput = $('#fileInput');
  $('#pickFiles')?.addEventListener('click', () => fileInput.click());
  uploadZone?.addEventListener('dragover', (ev) => { ev.preventDefault(); uploadZone.classList.add('drag'); });
  uploadZone?.addEventListener('dragleave', () => uploadZone.classList.remove('drag'));
  uploadZone?.addEventListener('drop', (ev) => { ev.preventDefault(); uploadZone.classList.remove('drag'); fileInput.files = ev.dataTransfer.files; text($('#dropText'), `${fileInput.files.length} Datei(en) ausgewählt`); });
  fileInput?.addEventListener('change', () => text($('#dropText'), `${fileInput.files.length} Datei(en) ausgewählt`));
  uploadZone?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (!fileInput.files.length) { notify('Bitte Datei auswählen.', false); return; }
    const fd = new FormData();
    [...fileInput.files].forEach(f => fd.append('tracks[]', f));
    try {
      const json = await post('api/upload.php', fd);
      renderUploadResults(json.results || []);
      await refreshTracks();
      updateStats(json.stats);
      uploadZone.reset(); text($('#dropText'), 'oder hier ablegen');
    } catch (e) { notify(e.message, false); }
  });

  function renderUploadResults(results) {
    const box = $('#uploadMessages'); box.replaceChildren();
    results.forEach(r => {
      const item = document.createElement('div'); item.className = 'message ' + (r.ok ? 'ok' : 'bad');
      item.textContent = r.ok ? `Gespeichert: ${r.track.title}` : `${r.filename}: ${r.message}`;
      box.append(item);
    });
  }

  let searchTimer = null;
  $('#searchInput')?.addEventListener('input', ev => {
    state.q = ev.target.value;
    clearTimeout(searchTimer); searchTimer = setTimeout(refreshTracks, 220);
  });
  $('#sortSelect')?.addEventListener('change', ev => { state.sort = ev.target.value; refreshTracks(); });
  $('#favoriteFilter')?.addEventListener('click', ev => { state.favoriteOnly = !state.favoriteOnly; ev.currentTarget.classList.toggle('primary', state.favoriteOnly); refreshTracks(); });
  $$('[data-close-dialog]').forEach(btn => btn.addEventListener('click', () => $('#' + btn.dataset.closeDialog)?.close()));
  $$('[data-scroll]').forEach(btn => btn.addEventListener('click', () => $('#' + btn.dataset.scroll)?.scrollIntoView({behavior:'smooth', block:'start'})));

  function notify(message, ok) {
    const box = $('#uploadMessages');
    if (!box) return alert(message);
    const item = document.createElement('div'); item.className = 'message ' + (ok ? 'ok' : 'bad'); item.textContent = message;
    box.prepend(item); setTimeout(() => item.remove(), 6000);
  }

  renderTracks(); renderPlaylists();
})();
