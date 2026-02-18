/* ===================================================
   Page Visitor Tracker — fires on every page load
   Sends data to /api/track.php
   =================================================== */
(function () {
  'use strict';

  // Persistent session ID (survives page navigations)
  var SID_KEY = 'qr_sid';
  var sid = localStorage.getItem(SID_KEY);
  if (!sid) {
    sid = crypto.randomUUID ? crypto.randomUUID() : (Date.now().toString(36) + Math.random().toString(36).slice(2));
    localStorage.setItem(SID_KEY, sid);
  }

  var payload = {
    page: location.pathname + location.search,
    title: document.title,
    screen_w: screen.width,
    screen_h: screen.height,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || null,
    session_id: sid,
  };

  // Use sendBeacon if available (non-blocking), otherwise fetch
  var url = '/api/track.php';
  if (navigator.sendBeacon) {
    navigator.sendBeacon(url, JSON.stringify(payload));
  } else {
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      keepalive: true,
    }).catch(function () {});
  }
})();
