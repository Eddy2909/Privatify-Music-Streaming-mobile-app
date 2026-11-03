(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const appMode = document.body?.dataset?.app || 'app';
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const text = (el, value) => { if (el) el.textContent = value ?? ''; };

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

  async function getJson(url) {
    const res = await fetch(url, { headers: {'Accept': 'application/json'} });
    const json = await res.json().catch(() => ({ok:false, message:'Ungültige Serverantwort.'}));
    if (!res.ok || !json.ok) throw new Error(json.message || 'Daten konnten nicht geladen werden.');
    return json;
  }

  function button(cls, label, title) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = cls;
    b.textContent = label;
    if (title) b.title = title;
    return b;
  }

  function notify(message, ok = true) {
    const stack = $('#toastStack');
    if (stack) {
      const item = document.createElement('div');
      item.className = 'toast ' + (ok ? 'ok' : 'bad');
      item.textContent = message;
      stack.prepend(item);
      setTimeout(() => item.remove(), 5200);
      return;
    }
    const box = $('#uploadMessages');
    if (box) {
      const item = document.createElement('div');
      item.className = 'message ' + (ok ? 'ok' : 'bad');
      item.textContent = message;
      box.prepend(item);
      setTimeout(() => item.remove(), 6500);
      return;
    }
    alert(message);
  }

  function formatTime(seconds) {
    const safe = Number.isFinite(seconds) ? Math.max(0, seconds) : 0;
    const s = Math.floor(safe % 60).toString().padStart(2, '0');
    const m = Math.floor(safe / 60);
    return `${m}:${s}`;
  }

  function replaceById(list, updated) {
    if (!Array.isArray(list) || !updated) return false;
    const idx = list.findIndex(t => String(t.id) === String(updated.id));
    if (idx < 0) return false;
    list[idx] = {...list[idx], ...updated};
    return true;
  }

  function removeById(list, id) {
    if (!Array.isArray(list)) return [];
    return list.filter(t => String(t.id) !== String(id));
  }

  function mergeTracks(current, incoming) {
    const merged = [...(Array.isArray(current) ? current : [])];
    (Array.isArray(incoming) ? incoming : []).forEach(track => {
      if (!replaceById(merged, track)) merged.push(track);
    });
    return merged;
  }

  function setFavoriteInTrack(track, favorite) {
    if (!track) return track;
    track.favorite = !!favorite;
    return track;
  }

  function favoriteButton(track, title = 'Favorit') {
    const btn = button('small-btn favorite-toggle' + (track.favorite ? ' liked' : ''), track.favorite ? '♥' : '♡', title);
    btn.setAttribute('aria-pressed', track.favorite ? 'true' : 'false');
    btn.dataset.trackId = String(track.id);
    return btn;
  }

  function incrementNumber(el, delta) {
    if (!el) return;
    const current = parseInt((el.textContent || '0').replace(/[^0-9-]/g, ''), 10);
    if (!Number.isFinite(current)) return;
    text(el, String(Math.max(0, current + delta)));
  }

  function flashElement(el) {
    if (!el) return;
    el.classList.remove('live-flash');
    void el.offsetWidth;
    el.classList.add('live-flash');
    setTimeout(() => el.classList.remove('live-flash'), 900);
  }

  function markPlaylistUpdated(playlistId) {
    if (!playlistId) return;
    const item = document.querySelector(`[data-playlist-id="${CSS.escape(String(playlistId))}"]`);
    flashElement(item);
  }

  function initSidebarCollapse() {
    const shell = $('.spotify-shell') || $('.app-shell');
    const toggle = $('#sidebarToggle');
    const sidebar = $('#appSidebar');
    if (!shell || !toggle || !sidebar) return;

    const breakpoint = sidebar.classList.contains('player-side') ? 860 : 1050;
    const isMobile = window.matchMedia(`(max-width:${breakpoint}px)`).matches;
    const key = `privatefy.sidebarCollapsed.${appMode}.${isMobile ? 'mobile' : 'desktop'}`;
    const apply = (collapsed) => {
      shell.classList.toggle('sidebar-collapsed', collapsed);
      sidebar.classList.toggle('is-collapsed', collapsed);
      toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggle.setAttribute('aria-label', collapsed ? 'Navigation ausklappen' : 'Navigation einklappen');
      toggle.textContent = isMobile ? (collapsed ? '☰' : '×') : (collapsed ? '›' : '‹');
    };

    const stored = localStorage.getItem(key);
    apply(stored === null ? isMobile : stored === '1');
    toggle.addEventListener('click', () => {
      const collapsed = !shell.classList.contains('sidebar-collapsed');
      localStorage.setItem(key, collapsed ? '1' : '0');
      apply(collapsed);
    });
  }

  function createAudioController(state) {
    const audio = $('#audio');
    const player = $('#player');
    const nowTitle = $('#nowTitle');
    const nowArtist = $('#nowArtist');
    const playPause = $('#playPause');
    const seek = $('#seek');
    const timeLabel = $('#timeLabel');
    const volume = $('#volume');
    const prevControls = $$('#prevTrack');
    const nextControls = $$('#nextTrack');
    if (!audio || !player) return null;

    // Media Session: gives the OS/browser media preview (lock screen, media
    // notification, Windows/macOS media widget) metadata plus play + skip.
    const mediaArtwork = [
      {src: 'assets/icons/icon-192.png', sizes: '192x192', type: 'image/png'},
      {src: 'assets/icons/icon-512.png', sizes: '512x512', type: 'image/png'}
    ];
    const hasMediaSession = 'mediaSession' in navigator && typeof window.MediaMetadata === 'function';

    function updateMediaSession(track) {
      if (!hasMediaSession || !track) return;
      try {
        navigator.mediaSession.metadata = new MediaMetadata({
          title: track.title || 'Unbekannter Titel',
          artist: track.artist || 'Unbekannter Artist',
          album: track.album || 'Privatefy',
          artwork: mediaArtwork
        });
      } catch (_) {}
    }

    function setMediaSessionState(playing) {
      if (!('mediaSession' in navigator)) return;
      try { navigator.mediaSession.playbackState = playing ? 'playing' : 'paused'; } catch (_) {}
    }

    const api = {
      playTrack(track, queue = null) {
        if (!track) return;
        if (Array.isArray(queue) && queue.length) state.queue = queue;
        state.current = track;
        const idx = state.queue.findIndex(t => String(t.id) === String(track.id));
        state.currentIndex = idx >= 0 ? idx : 0;
        player.hidden = false;
        audio.src = track.stream_url;
        audio.play().catch(() => notify('Browser hat Autoplay blockiert. Bitte Play drücken.', false));
        text(nowTitle, track.title);
        text(nowArtist, track.artist || 'Unbekannter Artist');
        text(playPause, '❚❚');
        updateMediaSession(track);
        renderQueueState(state);
        if (typeof state.onTrackChange === 'function') state.onTrackChange(track);
      },
      next() {
        if (!state.queue.length) return;
        const nextIndex = (state.currentIndex + 1) % state.queue.length;
        api.playTrack(state.queue[nextIndex]);
      },
      prev() {
        if (!state.queue.length) return;
        if (audio.currentTime > 4) { audio.currentTime = 0; return; }
        const prevIndex = (state.currentIndex - 1 + state.queue.length) % state.queue.length;
        api.playTrack(state.queue[prevIndex]);
      }
    };

    if (hasMediaSession) {
      const mediaHandlers = {
        play: () => { audio.play().catch(() => {}); },
        pause: () => { audio.pause(); },
        previoustrack: () => api.prev(),
        nexttrack: () => api.next(),
        seekbackward: (details) => { audio.currentTime = Math.max(0, audio.currentTime - (details?.seekOffset || 10)); },
        seekforward: (details) => {
          const target = audio.currentTime + (details?.seekOffset || 10);
          audio.currentTime = Number.isFinite(audio.duration) ? Math.min(audio.duration, target) : target;
        },
        seekto: (details) => {
          if (typeof details?.seekTime === 'number') audio.currentTime = details.seekTime;
        }
      };
      Object.entries(mediaHandlers).forEach(([action, handler]) => {
        try { navigator.mediaSession.setActionHandler(action, handler); } catch (_) {}
      });
    }

    playPause?.addEventListener('click', () => {
      if (!state.current) return;
      if (audio.paused) { audio.play(); text(playPause, '❚❚'); } else { audio.pause(); text(playPause, '▶'); }
    });
    audio.addEventListener('pause', () => { text(playPause, '▶'); setMediaSessionState(false); });
    audio.addEventListener('play', () => { text(playPause, '❚❚'); setMediaSessionState(true); });
    audio.addEventListener('timeupdate', () => {
      if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
      seek.value = String(Math.round((audio.currentTime / audio.duration) * 1000));
      text(timeLabel, formatTime(audio.currentTime));
    });
    audio.addEventListener('ended', () => api.next());
    seek?.addEventListener('input', () => {
      if (Number.isFinite(audio.duration)) audio.currentTime = (Number(seek.value) / 1000) * audio.duration;
    });
    volume?.addEventListener('input', () => { audio.volume = Number(volume.value); });
    prevControls.forEach(control => control.addEventListener('click', api.prev));
    nextControls.forEach(control => control.addEventListener('click', api.next));
    if (volume) audio.volume = Number(volume.value || 0.9);
    state.audioController = api;
    return api;
  }

  function initAdmin() {
    const state = { tracks: [], playlists: [], current: null, currentIndex: 0, queue: [], favoriteOnly: false, q: '', sort: 'newest', currentPlaylistId: null, page: 1, pageSize: 100, hasMore: false, total: 0 };
    const trackList = $('#trackList');
    if (!trackList) return;
    state.tracks = JSON.parse(trackList.dataset.tracks || '[]');
    state.total = Number(trackList.dataset.total || state.tracks.length);
    state.hasMore = state.tracks.length < state.total;
    state.playlists = JSON.parse($('#playlistList')?.dataset.playlists || '[]');
    const audioCtl = createAudioController(state);
    let refreshRequest = 0;

    async function refreshTracks(append = false) {
      const requestId = ++refreshRequest;
      const page = append ? state.page + 1 : 1;
      const params = new URLSearchParams({q: state.q, sort: state.sort, favorite: state.favoriteOnly ? '1' : '0', page: String(page), limit: String(state.pageSize)});
      const json = await getJson('api/tracks.php?' + params.toString());
      if (requestId !== refreshRequest) return;
      state.currentPlaylistId = null;
      state.tracks = append ? mergeTracks(state.tracks, json.tracks) : json.tracks;
      state.page = json.pagination?.page || page;
      state.total = json.pagination?.total ?? state.tracks.length;
      state.hasMore = !!json.pagination?.has_more;
      renderTracks();
      updateStats(json.stats);
      updateAdminLoadMore();
    }

    function updateAdminLoadMore() {
      const loadMore = $('#adminLoadMore');
      if (!loadMore) return;
      loadMore.hidden = !state.hasMore || state.currentPlaylistId !== null;
      loadMore.disabled = false;
    }

    function updateStats(stats) {
      if (!stats) return;
      text($('#statStorage'), stats.storage_human);
      text($('#statPlays'), String(stats.total_plays));
      text($('#statFavorites'), String(stats.favorites));
    }

    function renderTracks(list = state.tracks) {
      trackList.replaceChildren();
      const empty = $('#emptyState');
      if (empty) empty.hidden = list.length > 0;
      list.forEach(track => {
        const row = document.createElement('article');
        row.className = 'track-row' + (track.favorite ? ' is-favorite' : '');
        row.dataset.id = track.id;

        const play = button('small-btn play', '▶', 'Abspielen');
        play.addEventListener('click', () => audioCtl?.playTrack(track, list));
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
        const fav = favoriteButton(track, 'Favorit umschalten');
        fav.addEventListener('click', () => toggleFavorite(track));
        const add = button('small-btn add-toggle', '+', 'Zu Playlist');
        add.addEventListener('click', () => openPlaylistDialog(track));
        const edit = button('small-btn', '✎', 'Bearbeiten');
        edit.addEventListener('click', () => openEdit(track));
        const del = button('small-btn danger', '×', 'Löschen');
        del.addEventListener('click', () => deleteTrack(track));
        actions.append(fav, add, edit, del); row.append(actions);
        trackList.append(row);
      });
    }

    function renderPlaylists(highlightId = null) {
      const wrap = $('#playlistList');
      if (!wrap) return;
      wrap.replaceChildren();
      const select = $('#addPlaylistForm select[name="playlist_id"]');
      if (select) select.replaceChildren();
      state.playlists.forEach(pl => {
        const item = document.createElement('button');
        item.type = 'button'; item.className = 'playlist-item';
        item.dataset.playlistId = String(pl.id);
        if (String(highlightId) === String(pl.id)) item.classList.add('live-flash');
        const left = document.createElement('div');
        const strong = document.createElement('strong'); strong.textContent = pl.name;
        const span = document.createElement('span'); span.textContent = `${pl.track_count} Track(s)`;
        left.append(strong, span);
        const arrow = document.createElement('span'); arrow.textContent = '›';
        item.append(left, arrow);
        item.addEventListener('click', () => loadPlaylist(pl.id));
        wrap.append(item);
        if (select) {
          const opt = document.createElement('option'); opt.value = pl.id; opt.textContent = `${pl.name} (${pl.track_count})`;
          select.append(opt);
        }
      });
    }

    async function loadPlaylist(id) {
      try {
        const json = await getJson('api/playlists.php?id=' + encodeURIComponent(id));
        state.currentPlaylistId = id;
        state.hasMore = false;
        state.tracks = json.tracks;
        renderTracks(json.tracks);
        updateAdminLoadMore();
        const h = $('.library-head h2'); if (h) h.textContent = json.playlist.name;
        window.scrollTo({top: document.querySelector('.library-head').offsetTop - 20, behavior: 'smooth'});
      } catch (e) { notify(e.message, false); }
    }

    async function toggleFavorite(track) {
      const previous = !!track.favorite;
      const optimistic = {...track, favorite: !previous};
      replaceById(state.tracks, optimistic);
      renderTracks();
      incrementNumber($('#statFavorites'), optimistic.favorite ? 1 : -1);
      flashElement($(`[data-id="${CSS.escape(String(track.id))}"]`));

      try {
        const json = await post('api/tracks.php', formData({action:'favorite', id:track.id}));
        replaceById(state.tracks, json.track);
        updateStats(json.stats);
        renderTracks();
      } catch (e) {
        replaceById(state.tracks, {...track, favorite: previous});
        renderTracks();
        notify(e.message, false);
      }
    }

    async function deleteTrack(track) {
      if (!confirm(`„${track.title}“ wirklich löschen? Die MP3 wird vom Server entfernt.`)) return;
      try {
        const json = await post('api/tracks.php', formData({action:'delete', id:track.id}));
        state.tracks = removeById(state.tracks, track.id);
        state.total = Math.max(0, state.total - 1);
        updateStats(json.stats); renderTracks(); notify('Track gelöscht.', true);
      } catch (e) { notify(e.message, false); }
    }

    function openEdit(track) {
      const dialog = $('#editDialog'); const form = $('#editForm');
      if (!dialog || !form) return;
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
        replaceById(state.tracks, json.track);
        updateStats(json.stats); renderTracks(); $('#editDialog')?.close(); notify('Gespeichert.', true);
      } catch (e) { notify(e.message, false); }
    });

    function openPlaylistDialog(track) {
      if (state.playlists.length === 0) { notify('Erstelle zuerst eine Playlist.', false); return; }
      const form = $('#addPlaylistForm'); if (!form) return;
      form.track_id.value = track.id; renderPlaylists(); $('#playlistDialog')?.showModal();
    }

    $('#addPlaylistForm')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget); fd.append('action', 'add');
      const playlistId = fd.get('playlist_id');
      try {
        const json = await post('api/playlist-tracks.php', fd);
        state.playlists = json.playlists;
        renderPlaylists(playlistId);
        if (String(state.currentPlaylistId) === String(playlistId)) {
          state.tracks = json.tracks;
          renderTracks(json.tracks);
        }
        $('#playlistDialog')?.close();
        markPlaylistUpdated(playlistId);
        notify('Zur Playlist hinzugefügt.', true);
      } catch (e) { notify(e.message, false); }
    });

    $('#playlistForm')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget); fd.append('action', 'create');
      try {
        const json = await post('api/playlists.php', fd);
        state.playlists = json.playlists; renderPlaylists(json.playlist?.id); ev.currentTarget.reset(); notify('Playlist erstellt.', true);
      } catch (e) { notify(e.message, false); }
    });

    initUpload(refreshTracks, updateStats);
    initCommonDialogs();
    initAdminFilters(refreshTracks, state);
    $('#adminLoadMore')?.addEventListener('click', async ev => {
      ev.currentTarget.disabled = true;
      try { await refreshTracks(true); } catch (e) { ev.currentTarget.disabled = false; notify(e.message, false); }
    });
    renderTracks();
    renderPlaylists();
    updateAdminLoadMore();
    initPlaybackSync(state);
  }

  function initUpload(refreshTracks, updateStats) {
    const uploadZone = $('#uploadForm');
    const fileInput = $('#fileInput');
    const submit = $('#uploadSubmit');
    const progress = $('#uploadProgress');
    const progressLabel = $('#uploadProgressLabel');
    const progressPercent = $('#uploadProgressPercent');
    const progressFill = $('#uploadProgressFill');
    if (!uploadZone || !fileInput) return;

    const setProgress = (pct, label = 'Upload läuft …') => {
      if (!progress) return;
      progress.hidden = false;
      const safe = Math.max(0, Math.min(100, Math.round(pct)));
      text(progressLabel, label);
      text(progressPercent, `${safe}%`);
      if (progressFill) progressFill.style.width = `${safe}%`;
    };
    const resetProgressSoon = () => setTimeout(() => { if (progress) progress.hidden = true; }, 1800);

    $('#pickFiles')?.addEventListener('click', () => fileInput.click());
    uploadZone.addEventListener('dragover', (ev) => { ev.preventDefault(); uploadZone.classList.add('drag'); });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag'));
    uploadZone.addEventListener('drop', (ev) => {
      ev.preventDefault(); uploadZone.classList.remove('drag'); fileInput.files = ev.dataTransfer.files;
      text($('#dropText'), `${fileInput.files.length} Datei(en) ausgewählt`);
    });
    fileInput.addEventListener('change', () => text($('#dropText'), `${fileInput.files.length} Datei(en) ausgewählt`));

    uploadZone.addEventListener('submit', (ev) => {
      ev.preventDefault();
      if (!fileInput.files.length) { notify('Bitte Datei auswählen.', false); return; }
      const fd = new FormData();
      [...fileInput.files].forEach(f => fd.append('tracks[]', f));
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'api/upload.php', true);
      xhr.setRequestHeader('X-CSRF-Token', csrf);
      xhr.responseType = 'json';

      if (submit) submit.disabled = true;
      uploadZone.classList.add('is-uploading');
      setProgress(1, 'Upload startet …');

      xhr.upload.addEventListener('progress', (evp) => {
        if (evp.lengthComputable) setProgress((evp.loaded / evp.total) * 100, 'Dateien werden übertragen …');
        else setProgress(25, 'Server empfängt Dateien …');
      });

      xhr.addEventListener('load', async () => {
        const json = xhr.response || {};
        if (xhr.status < 200 || xhr.status >= 300 || !json.ok) {
          renderUploadResults(json.results || []);
          notify(json.message || 'Upload fehlgeschlagen.', false);
          setProgress(100, 'Upload fehlgeschlagen');
          resetProgressSoon();
          return;
        }
        setProgress(100, 'Upload abgeschlossen');
        renderUploadResults(json.results || []);
        try { await refreshTracks(); } catch (_) {}
        updateStats(json.stats);
        uploadZone.reset();
        text($('#dropText'), 'oder hier ablegen');
        resetProgressSoon();
      });

      xhr.addEventListener('error', () => {
        notify('Upload konnte wegen eines Netzwerk- oder Serverfehlers nicht abgeschlossen werden.', false);
        setProgress(100, 'Upload abgebrochen');
        resetProgressSoon();
      });

      xhr.addEventListener('loadend', () => {
        if (submit) submit.disabled = false;
        uploadZone.classList.remove('is-uploading');
      });

      xhr.send(fd);
    });
  }

  function renderUploadResults(results) {
    const box = $('#uploadMessages');
    if (!box) return;
    box.replaceChildren();
    if (!results.length) return;
    results.forEach(r => {
      const item = document.createElement('div');
      item.className = 'message ' + (r.ok ? 'ok' : 'bad');
      item.textContent = r.ok ? `Gespeichert: ${r.track.title}` : `${r.filename || 'Datei'}: ${r.message || 'fehlgeschlagen'}`;
      box.append(item);
    });
  }

  function initAdminFilters(refreshTracks, state) {
    let searchTimer = null;
    $('#searchInput')?.addEventListener('input', ev => {
      state.q = ev.target.value;
      clearTimeout(searchTimer); searchTimer = setTimeout(() => refreshTracks().catch(e => notify(e.message, false)), 220);
    });
    $('#sortSelect')?.addEventListener('change', ev => { state.sort = ev.target.value; refreshTracks().catch(e => notify(e.message, false)); });
    $('#favoriteFilter')?.addEventListener('click', ev => { state.favoriteOnly = !state.favoriteOnly; ev.currentTarget.classList.toggle('primary', state.favoriteOnly); refreshTracks().catch(e => notify(e.message, false)); });
  }

  function initCommonDialogs() {
    $$('[data-close-dialog]').forEach(btn => btn.addEventListener('click', () => $('#' + btn.dataset.closeDialog)?.close()));
    $$('[data-scroll]').forEach(btn => btn.addEventListener('click', () => $('#' + btn.dataset.scroll)?.scrollIntoView({behavior:'smooth', block:'start'})));
  }

  // Cross-device playback sync (song + play/pause). Logged-in only. Pushes local
  // changes and polls for changes made on other devices, asking before taking over.
  function initPlaybackSync(state) {
    if (document.body?.dataset?.auth !== 'user') return;

    const storageKey = 'privatefy.deviceId';
    let deviceId = localStorage.getItem(storageKey);
    if (!deviceId) {
      deviceId = (typeof crypto !== 'undefined' && crypto.randomUUID)
        ? crypto.randomUUID()
        : ('d' + Math.random().toString(36).slice(2) + Date.now().toString(36));
      localStorage.setItem(storageKey, deviceId);
    }
    const ua = navigator.userAgent;
    const platform = /iPhone|iPad|iPod/i.test(ua) ? 'iOS' : /Android/i.test(ua) ? 'Android' : /Mac/i.test(ua) ? 'Mac' : /Win/i.test(ua) ? 'Windows' : 'Web';
    const deviceLabel = `${platform} ${/Android|iPhone|iPad|iPod|Mobile/i.test(ua) ? 'Handy' : 'Browser'}`;

    let knownTs = 0;
    let primed = false;
    let pushTimer = null;
    let warned = false;
    const audioEl = () => document.getElementById('audio');
    const warnOnce = (err) => {
      if (warned) return;
      warned = true;
      console.warn('Privatefy Sync deaktiviert (Serverfehler):', err && err.message ? err.message : err);
    };

    async function pushState() {
      const body = new FormData();
      body.append('device_id', deviceId);
      body.append('device_label', deviceLabel);
      body.append('track_id', state.current ? String(state.current.id) : '0');
      const audio = audioEl();
      body.append('is_playing', audio && !audio.paused ? '1' : '0');
      try {
        const json = await post('api/sync.php', body);
        if (json && json.updated_ts) knownTs = Number(json.updated_ts);
      } catch (err) { warnOnce(err); }
    }

    function schedulePush() {
      clearTimeout(pushTimer);
      pushTimer = setTimeout(pushState, 800);
    }

    function applyRemote(remote) {
      if (!remote || !remote.track || !state.audioController) return;
      const queue = Array.isArray(state.queue) && state.queue.length ? state.queue : [remote.track];
      state.audioController.playTrack(remote.track, queue);
      const audio = audioEl();
      if (audio && !remote.is_playing) audio.pause();
    }

    function showPrompt(remote) {
      let el = document.getElementById('syncPrompt');
      if (!el) {
        el = document.createElement('div');
        el.id = 'syncPrompt';
        el.className = 'sync-prompt';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-live', 'polite');
        document.body.append(el);
      }
      console.info('[Privatefy Sync] Nachfrage von', remote.device_label, remote.track.title);
      const verb = remote.is_playing ? 'läuft' : 'ist pausiert';
      const artist = remote.track.artist && remote.track.artist !== 'Unbekannter Artist' ? ` – ${remote.track.artist}` : '';
      el.replaceChildren();
      const p = document.createElement('p');
      p.textContent = `Auf ${remote.device_label || 'einem anderen Gerät'} ${verb}: „${remote.track.title}${artist}". Hier übernehmen?`;
      const accept = button('btn primary', 'Hier weiterhören', 'Wiedergabe auf diesem Gerät übernehmen');
      accept.addEventListener('click', () => { el.remove(); applyRemote(remote); });
      const dismiss = button('btn ghost', 'Ignorieren', 'Meldung schließen');
      dismiss.addEventListener('click', () => el.remove());
      el.append(p, accept, dismiss);
    }

    async function poll() {
      if (document.hidden) return;
      try {
        const json = await getJson('api/sync.php');
        const remote = json ? json.state : null;
        if (!remote) { primed = true; return; }
        const audio = audioEl();
        // A device that is currently playing its own music must not be interrupted
        // by another device's state - otherwise two devices would ping-pong prompts.
        const localActive = !!(state.current && audio && !audio.paused);
        if (!primed) {
          primed = true;
          knownTs = Number(remote.updated_ts || 0);
          if (!localActive && remote.device_id !== deviceId && remote.is_playing && remote.track) showPrompt(remote);
          return;
        }
        if (Number(remote.updated_ts) > knownTs && remote.device_id !== deviceId) {
          knownTs = Number(remote.updated_ts);
          const sameTrack = !!state.current && String(state.current.id) === String(remote.track_id);
          // Handoff: another device just took over the exact track we are playing
          // -> we must pause, so only one device keeps playing it.
          if (localActive && remote.is_playing && sameTrack) {
            audio.pause();
            return;
          }
          // Two devices may intentionally play different songs: never interrupt
          // a device that is currently playing its own track.
          if (localActive) return;
          if (remote.track && !sameTrack) showPrompt(remote);
        }
      } catch (err) { warnOnce(err); }
    }

    if (typeof state.onTrackChange === 'function') {
      const previous = state.onTrackChange;
      state.onTrackChange = (track) => { previous(track); schedulePush(); };
    } else {
      state.onTrackChange = () => schedulePush();
    }
    audioEl()?.addEventListener('play', schedulePush);
    audioEl()?.addEventListener('pause', schedulePush);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    // Snappy handoff: poll frequently while the tab is visible; pause when hidden.
    setInterval(poll, 2500);
    setTimeout(poll, 600);
  }

  function initPlayer() {
    const isPublic = document.body?.dataset?.auth === 'public';
    const state = { allTracks: [], tracks: [], playlists: [], current: null, currentIndex: 0, queue: [], q: '', sort: 'newest', filter: 'all', playlistId: null, page: 1, pageSize: 100, hasMore: false, libraryTotal: 0, totalFavorites: 0 };
    const trackList = $('#playerTrackList');
    if (!trackList) return;
    state.allTracks = JSON.parse(trackList.dataset.tracks || '[]');
    state.tracks = [...state.allTracks];
    state.queue = [...state.tracks];
    state.libraryTotal = Number(trackList.dataset.total || state.tracks.length);
    state.totalFavorites = Number(trackList.dataset.favorites || state.tracks.filter(t => !!t.favorite).length);
    state.hasMore = state.tracks.length < state.libraryTotal;
    state.playlists = JSON.parse($('#playerPlaylistList')?.dataset.playlists || '[]');
    const audioCtl = createAudioController(state);
    let refreshRequest = 0;

    function knownTracks() {
      return state.allTracks.length ? state.allTracks : state.tracks;
    }

    function visibleTracks() {
      if (state.filter === 'favorites') return state.tracks.filter(t => t.favorite);
      return state.tracks;
    }

    function updatePlayerCounters() {
      text($('#likedCountText'), String(state.totalFavorites));
      text($('#playerTotalTracksText'), String(state.libraryTotal));
    }

    function updatePlayerLoadMore() {
      const loadMore = $('#playerLoadMore');
      if (!loadMore) return;
      loadMore.hidden = !state.hasMore || state.playlistId !== null;
      loadMore.disabled = false;
    }

    function updateFilterControls() {
      const back = $('#backToAllBtn');
      if (!back) return;
      back.hidden = state.filter === 'all' && !state.q && !state.playlistId;
    }

    function replaceTrackEverywhere(updated) {
      replaceById(state.tracks, updated);
      replaceById(state.allTracks, updated);
      replaceById(state.queue, updated);
      if (state.current && String(state.current.id) === String(updated.id)) state.current = {...state.current, ...updated};
    }

    function renderPlayerTracks(list = visibleTracks()) {
      trackList.replaceChildren();
      $('#playerEmptyState').hidden = list.length > 0;
      list.forEach((track, index) => {
        const isCurrent = state.current && String(state.current.id) === String(track.id);
        const row = document.createElement('article');
        row.className = 'song-row' + (track.favorite ? ' is-favorite' : '') + (isCurrent ? ' is-playing' : '');
        row.dataset.id = track.id;
        row.dataset.index = String(index + 1);
        if (isCurrent) row.setAttribute('aria-current', 'true');

        const number = document.createElement('button');
        number.type = 'button';
        number.className = 'song-index';
        number.innerHTML = isCurrent ? '<span class="playing-bars" aria-label="Spielt gerade"><i></i><i></i><i></i></span>' : String(index + 1);
        number.addEventListener('click', () => audioCtl?.playTrack(track, list));
        row.append(number);

        const main = document.createElement('button');
        main.type = 'button';
        main.className = 'song-main';
        const titleWrap = document.createElement('span'); titleWrap.className = 'song-title-line';
        const title = document.createElement('strong'); title.textContent = track.title;
        titleWrap.append(title);
        if (isCurrent) {
          const nowBadge = document.createElement('em');
          nowBadge.className = 'now-badge';
          nowBadge.textContent = 'läuft';
          titleWrap.append(nowBadge);
        }
        const sub = document.createElement('span'); sub.textContent = `${track.artist || 'Unbekannter Artist'}${track.album ? ' · ' + track.album : ''}`;
        main.append(titleWrap, sub);
        main.addEventListener('click', () => audioCtl?.playTrack(track, list));
        row.append(main);

        const plays = document.createElement('span'); plays.className = 'song-meta hide-mobile'; plays.textContent = `${track.play_count} Plays`; row.append(plays);
        const genre = document.createElement('span'); genre.className = 'badge hide-mobile'; genre.textContent = track.genre || 'MP3'; row.append(genre);

        const actions = document.createElement('div'); actions.className = 'song-actions';
        if (!isPublic) {
          const fav = favoriteButton(track, 'Favorit');
          fav.addEventListener('click', () => toggleFavorite(track));
          const add = button('small-btn add-toggle', '+', 'Zu Playlist');
          add.addEventListener('click', () => openPlaylistDialog(track));
          actions.append(fav, add);
          row.append(actions);
        } else {
          row.classList.add('no-actions');
        }
        trackList.append(row);
      });
      state.queue = [...list];
      syncQueueIndexWithCurrent();
      renderQueueState(state);
      updatePlayerCounters();
    }

    function syncQueueIndexWithCurrent() {
      if (!state.current || !Array.isArray(state.queue)) return;
      const idx = state.queue.findIndex(t => String(t.id) === String(state.current.id));
      if (idx >= 0) state.currentIndex = idx;
    }

    function syncPlayingIndicators() {
      $$('.song-row', trackList).forEach(row => {
        const isCurrent = !!state.current && String(row.dataset.id) === String(state.current.id);
        row.classList.toggle('is-playing', isCurrent);
        row.toggleAttribute('aria-current', isCurrent);
        const indexButton = $('.song-index', row);
        if (indexButton) {
          indexButton.innerHTML = isCurrent
            ? '<span class="playing-bars" aria-label="Spielt gerade"><i></i><i></i><i></i></span>'
            : (row.dataset.index || '');
        }
        const main = $('.song-main', row);
        const titleLine = $('.song-title-line', row);
        const existingBadge = $('.now-badge', row);
        if (isCurrent && titleLine && !existingBadge) {
          const nowBadge = document.createElement('em');
          nowBadge.className = 'now-badge';
          nowBadge.textContent = 'läuft';
          titleLine.append(nowBadge);
        }
        if (!isCurrent && existingBadge) existingBadge.remove();
        if (main) main.setAttribute('aria-label', isCurrent ? 'Spielt gerade. Zum Neustarten klicken.' : 'Track abspielen');
      });
      renderQueueState(state);
    }

    state.onTrackChange = syncPlayingIndicators;

    function renderPlayerPlaylists(highlightId = null) {
      const wrap = $('#playerPlaylistList');
      if (!wrap) return;
      wrap.replaceChildren();
      const select = $('#addPlaylistForm select[name="playlist_id"]');
      if (select) select.replaceChildren();
      state.playlists.forEach(pl => {
        const item = document.createElement('button');
        item.type = 'button'; item.className = 'playlist-item';
        item.dataset.playlistId = String(pl.id);
        if (String(highlightId) === String(pl.id)) item.classList.add('live-flash');
        const left = document.createElement('div');
        const strong = document.createElement('strong'); strong.textContent = pl.name;
        const span = document.createElement('span'); span.textContent = `${pl.track_count} Track(s)`;
        left.append(strong, span);
        const arrow = document.createElement('span'); arrow.textContent = '›';
        item.append(left, arrow);
        item.addEventListener('click', () => loadPlaylist(pl.id));
        wrap.append(item);
        if (select) {
          const opt = document.createElement('option'); opt.value = pl.id; opt.textContent = `${pl.name} (${pl.track_count})`;
          select.append(opt);
        }
      });
    }

    async function refreshTracks(append = false) {
      const requestId = ++refreshRequest;
      const page = append ? state.page + 1 : 1;
      const params = new URLSearchParams({q: state.q, sort: state.sort, favorite: state.filter === 'favorites' ? '1' : '0', page: String(page), limit: String(state.pageSize)});
      const json = await getJson('api/tracks.php?' + params.toString());
      if (requestId !== refreshRequest) return;
      state.tracks = append ? mergeTracks(state.tracks, json.tracks) : json.tracks;
      state.page = json.pagination?.page || page;
      state.hasMore = !!json.pagination?.has_more;
      state.libraryTotal = Number(json.stats?.total_tracks ?? state.libraryTotal);
      state.totalFavorites = Number(json.stats?.favorites ?? state.totalFavorites);
      if (state.filter === 'all' && !state.q) state.allTracks = [...state.tracks];
      if (state.q) {
        text($('#playerContext'), 'Suche');
        text($('#libraryHeading'), `${json.pagination?.total ?? state.tracks.length} Treffer`);
      } else {
        text($('#playerContext'), state.filter === 'favorites' ? 'Favoriten' : 'Bibliothek');
        text($('#libraryHeading'), state.filter === 'favorites' ? 'Liked Songs' : 'Alle Songs');
      }
      updateFilterControls();
      renderPlayerTracks();
      updatePlayerLoadMore();
    }

    async function loadPlaylist(id) {
      try {
        const json = await getJson('api/playlists.php?id=' + encodeURIComponent(id));
        state.filter = 'playlist';
        state.playlistId = id;
        state.hasMore = false;
        state.tracks = json.tracks;
        text($('#playerContext'), 'Playlist');
        text($('#libraryHeading'), json.playlist.name);
        markNav(null);
        updateFilterControls();
        renderPlayerTracks(state.tracks);
        updatePlayerLoadMore();
      } catch (e) { notify(e.message, false); }
    }

    function markNav(filter) {
      $$('[data-player-filter]').forEach(btn => btn.classList.toggle('active', btn.dataset.playerFilter === filter));
    }

    function setFilter(filter) {
      state.filter = filter;
      state.playlistId = null;
      state.page = 1;
      if (filter === 'all') {
        state.q = '';
        const search = $('#playerSearchInput');
        if (search) search.value = '';
      }
      markNav(filter);
      text($('#playerContext'), filter === 'favorites' ? 'Favoriten' : 'Bibliothek');
      text($('#libraryHeading'), filter === 'favorites' ? 'Liked Songs' : 'Alle Songs');
      updateFilterControls();
      refreshTracks().catch(e => notify(e.message, false));
    }

    async function toggleFavorite(track) {
      const previous = !!track.favorite;
      const optimistic = {...track, favorite: !previous};
      replaceTrackEverywhere(optimistic);
      renderPlayerTracks();
      flashElement($(`[data-id="${CSS.escape(String(track.id))}"]`));

      try {
        const json = await post('api/tracks.php', formData({action:'favorite', id:track.id}));
        replaceTrackEverywhere(json.track);
        state.totalFavorites = Number(json.stats?.favorites ?? state.totalFavorites);
        renderPlayerTracks();
      } catch (e) {
        replaceTrackEverywhere({...track, favorite: previous});
        renderPlayerTracks();
        notify(e.message, false);
      }
    }

    function openPlaylistDialog(track) {
      if (state.playlists.length === 0) { notify('Lege im Admin-Bereich zuerst eine Playlist an.', false); return; }
      const form = $('#addPlaylistForm'); if (!form) return;
      form.track_id.value = track.id; renderPlayerPlaylists(); $('#playlistDialog')?.showModal();
    }

    $('#addPlaylistForm')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fd = new FormData(ev.currentTarget); fd.append('action', 'add');
      const playlistId = fd.get('playlist_id');
      try {
        const json = await post('api/playlist-tracks.php', fd);
        state.playlists = json.playlists;
        renderPlayerPlaylists(playlistId);
        if (String(state.playlistId) === String(playlistId)) {
          state.tracks = json.tracks;
          renderPlayerTracks(state.tracks);
        }
        $('#playlistDialog')?.close();
        markPlaylistUpdated(playlistId);
        notify('Zur Playlist hinzugefügt.', true);
      } catch (e) { notify(e.message, false); }
    });

    let searchTimer = null;
    $('#playerSearchInput')?.addEventListener('input', ev => {
      state.q = ev.target.value;
      if (state.playlistId !== null) {
        state.filter = 'all';
        state.playlistId = null;
        markNav('all');
      }
      updateFilterControls();
      clearTimeout(searchTimer); searchTimer = setTimeout(() => refreshTracks().catch(e => notify(e.message, false)), 180);
    });
    $('#playerSortSelect')?.addEventListener('change', ev => { state.sort = ev.target.value; refreshTracks().catch(e => notify(e.message, false)); });
    $$('[data-player-filter]').forEach(btn => btn.addEventListener('click', () => setFilter(btn.dataset.playerFilter)));
    $$('[data-player-filter-card]').forEach(card => {
      card.addEventListener('click', () => setFilter(card.dataset.playerFilterCard));
      card.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); setFilter(card.dataset.playerFilterCard); }
      });
    });
    $('#backToAllBtn')?.addEventListener('click', () => setFilter('all'));
    $('#heroPlayAll')?.addEventListener('click', () => { const list = visibleTracks(); if (list.length) audioCtl?.playTrack(list[0], list); });
    $('#heroPlayAll')?.addEventListener('keydown', ev => { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); const list = visibleTracks(); if (list.length) audioCtl?.playTrack(list[0], list); } });
    $('#playerLoadMore')?.addEventListener('click', async ev => {
      ev.currentTarget.disabled = true;
      try { await refreshTracks(true); } catch (e) { ev.currentTarget.disabled = false; notify(e.message, false); }
    });
    $('#clearQueue')?.addEventListener('click', () => {
      state.queue = [...visibleTracks()];
      syncQueueIndexWithCurrent();
      renderQueueState(state);
      notify('Queue aus der aktuellen Liste aktualisiert.', true);
    });
    initCommonDialogs();
    renderPlayerPlaylists();
    updateFilterControls();
    renderPlayerTracks();
    updatePlayerCounters();
    updatePlayerLoadMore();
    initPlaybackSync(state);
  }

  function moveQueueItem(state, fromIndex, toIndex) {
    if (!state || !Array.isArray(state.queue)) return false;
    if (!Number.isInteger(fromIndex) || !Number.isInteger(toIndex)) return false;
    if (fromIndex < 0 || toIndex < 0 || fromIndex >= state.queue.length || toIndex >= state.queue.length || fromIndex === toIndex) return false;
    const [moved] = state.queue.splice(fromIndex, 1);
    state.queue.splice(toIndex, 0, moved);
    if (state.current) {
      const currentIndex = state.queue.findIndex(t => String(t.id) === String(state.current.id));
      if (currentIndex >= 0) state.currentIndex = currentIndex;
    }
    return true;
  }

  function renderQueueState(state) {
    text($('#queueTitle'), state.current?.title || 'Noch nichts gestartet');
    text($('#queueArtist'), state.current?.artist || 'Wähle einen Song aus deiner Library.');
    const list = $('#queueList');
    if (!list) return;
    list.replaceChildren();

    if (!Array.isArray(state.queue) || state.queue.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'queue-empty';
      empty.textContent = 'Queue ist leer.';
      list.append(empty);
      return;
    }

    state.queue.forEach((track, idx) => {
      const isCurrent = state.current && String(state.current.id) === String(track.id);
      const item = document.createElement('div');
      item.className = 'queue-item' + (isCurrent ? ' active' : '');
      item.dataset.id = track.id;
      item.dataset.queueIndex = String(idx);
      item.setAttribute('role', 'button');
      item.setAttribute('tabindex', '0');
      item.setAttribute('draggable', 'true');
      item.setAttribute('aria-label', `${track.title} abspielen. Zum Sortieren ziehen.`);
      if (isCurrent) item.setAttribute('aria-current', 'true');

      const dragHandle = document.createElement('span');
      dragHandle.className = 'queue-drag-handle';
      dragHandle.textContent = '⋮⋮';
      dragHandle.setAttribute('aria-hidden', 'true');

      const pos = document.createElement('span');
      pos.className = 'queue-position';
      pos.innerHTML = isCurrent ? '<span class="playing-dot" aria-hidden="true"></span>' : String(idx + 1).padStart(2, '0');

      const main = document.createElement('div');
      const strong = document.createElement('strong'); strong.textContent = track.title;
      const sub = document.createElement('small'); sub.textContent = track.artist || 'Unbekannter Artist';
      main.append(strong, sub);
      item.append(dragHandle, pos, main);

      const playFromQueue = () => {
        if (state.justDropped) return;
        if (state.audioController) state.audioController.playTrack(track, state.queue);
      };

      item.addEventListener('click', playFromQueue);
      item.addEventListener('keydown', ev => {
        if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); playFromQueue(); }
      });

      item.addEventListener('dragstart', ev => {
        state.queueDragIndex = idx;
        item.classList.add('dragging');
        if (ev.dataTransfer) {
          ev.dataTransfer.effectAllowed = 'move';
          ev.dataTransfer.setData('text/plain', String(idx));
        }
      });

      item.addEventListener('dragend', () => {
        item.classList.remove('dragging');
        $$('.queue-item.drag-over', list).forEach(el => el.classList.remove('drag-over'));
        state.queueDragIndex = null;
      });

      item.addEventListener('dragover', ev => {
        ev.preventDefault();
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move';
        item.classList.add('drag-over');
      });

      item.addEventListener('dragleave', () => item.classList.remove('drag-over'));

      item.addEventListener('drop', ev => {
        ev.preventDefault();
        item.classList.remove('drag-over');
        const fromData = ev.dataTransfer?.getData('text/plain');
        const fromIndex = Number.isInteger(state.queueDragIndex) ? state.queueDragIndex : parseInt(fromData || '', 10);
        const toIndex = idx;
        if (moveQueueItem(state, fromIndex, toIndex)) {
          state.justDropped = true;
          renderQueueState(state);
          setTimeout(() => { state.justDropped = false; }, 80);
        }
      });

      list.append(item);
    });
  }

  initSidebarCollapse();
  if (appMode === 'admin') initAdmin();
  else if (appMode === 'player') initPlayer();
})();
