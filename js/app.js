/* ===================================================
   QR Code Generator — Vue 3 Application
   Full-featured QR generation with customization
   =================================================== */

const { createApp, ref, reactive, watch, onMounted, nextTick, computed } = Vue;

const app = createApp({
  setup() {
    // ─── Dark Mode ───────────────────────────────
    const darkMode = ref(
      localStorage.getItem('qr-darkmode') === 'true' ||
      (!localStorage.getItem('qr-darkmode') && window.matchMedia('(prefers-color-scheme: dark)').matches)
    );

    watch(darkMode, (val) => {
      document.documentElement.classList.toggle('dark', val);
      localStorage.setItem('qr-darkmode', val);
    }, { immediate: true });

    // ─── QR Type Definitions ─────────────────────
    const qrTypes = [
      { id: 'url',   label: 'URL',     icon: '🔗' },
      { id: 'text',  label: 'Text',    icon: '📝' },
      { id: 'email', label: 'Email',   icon: '✉️' },
      { id: 'phone', label: 'Phone',   icon: '📱' },
      { id: 'wifi',  label: 'WiFi',    icon: '📶' },
      { id: 'vcard', label: 'vCard',   icon: '👤' },
    ];

    const qrType = ref('url');

    // ─── Content Fields ──────────────────────────
    const content = reactive({
      // URL
      url: '',
      // Text
      text: '',
      // Email
      email: '',
      emailSubject: '',
      emailBody: '',
      // Phone
      phone: '',
      // WiFi
      wifiSsid: '',
      wifiPassword: '',
      wifiEncryption: 'WPA',
      wifiHidden: false,
      // vCard
      vcardFirstName: '',
      vcardLastName: '',
      vcardOrg: '',
      vcardTitle: '',
      vcardPhone: '',
      vcardEmail: '',
      vcardUrl: '',
      vcardAddress: '',
    });

    // ─── Options ─────────────────────────────────
    const options = reactive({
      width: 320,
      margin: 2,
      colorDark: '#1e1b4b',
      colorLight: '#ffffff',
      errorCorrectionLevel: 'M',
    });

    const downloadFormat = ref('png');
    const showAdvanced = ref(false);
    const showWifiPass = ref(false);

    // ─── Logo ────────────────────────────────────
    const logoDataUrl = ref(null);
    const logoImage = ref(null);

    function handleLogoUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      if (!file.type.startsWith('image/')) {
        showToast('Please select an image file', 'error');
        return;
      }
      const reader = new FileReader();
      reader.onload = (e) => {
        logoDataUrl.value = e.target.result;
        const img = new Image();
        img.onload = () => {
          logoImage.value = img;
          // Auto-switch to High error correction for logo
          options.errorCorrectionLevel = 'H';
          generateQR();
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }

    function removeLogo() {
      logoDataUrl.value = null;
      logoImage.value = null;
      generateQR();
    }

    // ─── QR Data ─────────────────────────────────
    const qrDataUrl = ref('');
    const qrWithLogoUrl = ref('');
    const qrCanvas = ref(null);
    const shortUrl = ref('');        // The shortened redirect URL
    const useShortUrl = ref(true);   // Whether to use shortened URLs
    const isShortening = ref(false); // Loading state for API call

    // API base — uses relative path (works on same domain)
    const API_BASE = window.location.origin;

    // Debounce helper
    let debounceTimer = null;

    // Build the data string based on type
    function buildQRData() {
      switch (qrType.value) {
        case 'url':
          return content.url.trim();

        case 'text':
          return content.text.trim();

        case 'email': {
          if (!content.email.trim()) return '';
          let mailto = `mailto:${content.email.trim()}`;
          const params = [];
          if (content.emailSubject.trim()) params.push(`subject=${encodeURIComponent(content.emailSubject.trim())}`);
          if (content.emailBody.trim()) params.push(`body=${encodeURIComponent(content.emailBody.trim())}`);
          if (params.length) mailto += '?' + params.join('&');
          return mailto;
        }

        case 'phone':
          return content.phone.trim() ? `tel:${content.phone.trim()}` : '';

        case 'wifi': {
          if (!content.wifiSsid.trim()) return '';
          const enc = content.wifiEncryption;
          const ssid = escapeWifiField(content.wifiSsid);
          const pass = escapeWifiField(content.wifiPassword);
          const hidden = content.wifiHidden ? 'H:true' : '';
          return `WIFI:T:${enc};S:${ssid};P:${pass};${hidden};`;
        }

        case 'vcard': {
          if (!content.vcardFirstName.trim() && !content.vcardLastName.trim()) return '';
          let vcard = 'BEGIN:VCARD\nVERSION:3.0\n';
          vcard += `N:${content.vcardLastName};${content.vcardFirstName};;;\n`;
          vcard += `FN:${content.vcardFirstName} ${content.vcardLastName}\n`;
          if (content.vcardOrg.trim()) vcard += `ORG:${content.vcardOrg}\n`;
          if (content.vcardTitle.trim()) vcard += `TITLE:${content.vcardTitle}\n`;
          if (content.vcardPhone.trim()) vcard += `TEL;TYPE=CELL:${content.vcardPhone}\n`;
          if (content.vcardEmail.trim()) vcard += `EMAIL:${content.vcardEmail}\n`;
          if (content.vcardUrl.trim()) vcard += `URL:${content.vcardUrl}\n`;
          if (content.vcardAddress.trim()) vcard += `ADR:;;${content.vcardAddress};;;;\n`;
          vcard += 'END:VCARD';
          return vcard;
        }

        default:
          return '';
      }
    }

    function escapeWifiField(str) {
      return str.replace(/([\\;,":])/, '\\$1');
    }

    // Generate QR Code
    function generateQR() {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(async () => {
        const rawData = buildQRData();
        if (!rawData) {
          qrDataUrl.value = '';
          qrWithLogoUrl.value = '';
          shortUrl.value = '';
          return;
        }

        // For URL type with shortening enabled, call the API
        let data = rawData;
        if (qrType.value === 'url' && useShortUrl.value && content.url.trim()) {
          try {
            isShortening.value = true;
            const resp = await fetch(API_BASE + '/api/shorten.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ url: content.url.trim() }),
            });
            if (resp.ok) {
              const result = await resp.json();
              if (result.short_url) {
                shortUrl.value = result.short_url;
                data = result.short_url;  // QR encodes the short URL
              }
            } else {
              // API not available — fall back to raw URL
              shortUrl.value = '';
              console.warn('Shorten API not available, using raw URL');
            }
          } catch (e) {
            // API unavailable (local dev, etc.) — fall back silently
            shortUrl.value = '';
            console.warn('Shorten API not reachable, using raw URL');
          } finally {
            isShortening.value = false;
          }
        } else {
          shortUrl.value = '';
        }

        try {
          // Always use a fresh offscreen canvas for generation
          const offscreen = document.createElement('canvas');

          await QRCode.toCanvas(offscreen, data, {
            width: options.width,
            margin: options.margin,
            color: {
              dark: options.colorDark,
              light: options.colorLight,
            },
            errorCorrectionLevel: options.errorCorrectionLevel,
          });

          // Copy to the visible canvas ref if available
          if (qrCanvas.value) {
            const visibleCtx = qrCanvas.value.getContext('2d');
            qrCanvas.value.width = offscreen.width;
            qrCanvas.value.height = offscreen.height;
            visibleCtx.drawImage(offscreen, 0, 0);
          }

          qrDataUrl.value = offscreen.toDataURL('image/png');

          // If logo provided, composite it
          if (logoDataUrl.value && logoImage.value) {
            drawLogoOnQR(offscreen);
          } else {
            qrWithLogoUrl.value = '';
          }

          // Save to history
          addToHistory(data);
        } catch (err) {
          console.error('QR Generation error:', err);
          if (err && err.message && err.message.includes('too long')) {
            showToast('Content is too long for a QR code. Try shortening it.', 'error');
          } else {
            showToast('Failed to generate QR code: ' + (err.message || 'Unknown error'), 'error');
          }
        }
      }, 300);
    }

    function drawLogoOnQR(sourceCanvas) {
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      const size = sourceCanvas.width;
      canvas.width = size;
      canvas.height = size;

      // Draw QR code
      ctx.drawImage(sourceCanvas, 0, 0);

      // Calculate logo size (20% of QR)
      const logoSize = Math.floor(size * 0.2);
      const logoX = Math.floor((size - logoSize) / 2);
      const logoY = Math.floor((size - logoSize) / 2);
      const radius = Math.floor(logoSize * 0.15);

      // Draw white rounded background
      ctx.fillStyle = options.colorLight;
      const padding = 6;
      roundedRect(ctx, logoX - padding, logoY - padding, logoSize + padding * 2, logoSize + padding * 2, radius + 2);
      ctx.fill();

      // Clip rounded rect and draw logo
      ctx.save();
      ctx.beginPath();
      roundedRect(ctx, logoX, logoY, logoSize, logoSize, radius);
      ctx.clip();
      ctx.drawImage(logoImage.value, logoX, logoY, logoSize, logoSize);
      ctx.restore();

      qrWithLogoUrl.value = canvas.toDataURL('image/png');
    }

    function roundedRect(ctx, x, y, w, h, r) {
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.lineTo(x + w - r, y);
      ctx.quadraticCurveTo(x + w, y, x + w, y + r);
      ctx.lineTo(x + w, y + h - r);
      ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
      ctx.lineTo(x + r, y + h);
      ctx.quadraticCurveTo(x, y + h, x, y + h - r);
      ctx.lineTo(x, y + r);
      ctx.quadraticCurveTo(x, y, x + r, y);
      ctx.closePath();
    }

    // Watch for type change to regenerate
    watch(qrType, () => {
      generateQR();
    });

    // ─── Download ────────────────────────────────
    async function downloadQR(format) {
      const data = buildQRData();
      if (!data) {
        showToast('Generate a QR code first', 'error');
        return;
      }

      const fileName = `qrcode-${Date.now()}`;

      try {
        if (format === 'svg') {
          const svgString = await QRCode.toString(data, {
            type: 'svg',
            width: options.width,
            margin: options.margin,
            color: {
              dark: options.colorDark,
              light: options.colorLight,
            },
            errorCorrectionLevel: options.errorCorrectionLevel,
          });

          const blob = new Blob([svgString], { type: 'image/svg+xml' });
          downloadBlob(blob, `${fileName}.svg`);
        } else {
          // Use the logo version if available
          const imgSrc = (logoDataUrl.value && qrWithLogoUrl.value) ? qrWithLogoUrl.value : qrDataUrl.value;

          // Create high-res canvas for download
          const img = new Image();
          img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = options.width;
            canvas.height = options.width;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, options.width, options.width);

            let mimeType = 'image/png';
            let ext = 'png';
            if (format === 'jpeg') { mimeType = 'image/jpeg'; ext = 'jpg'; }
            if (format === 'webp') { mimeType = 'image/webp'; ext = 'webp'; }

            canvas.toBlob((blob) => {
              downloadBlob(blob, `${fileName}.${ext}`);
            }, mimeType, 0.95);
          };
          img.src = imgSrc;
        }

        showToast(`Downloaded as ${format.toUpperCase()}`, 'success');
      } catch (err) {
        console.error('Download error:', err);
        showToast('Download failed', 'error');
      }
    }

    function downloadBlob(blob, filename) {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    // ─── Copy to Clipboard ───────────────────────
    const copied = ref(false);

    async function copyToClipboard() {
      const imgSrc = (logoDataUrl.value && qrWithLogoUrl.value) ? qrWithLogoUrl.value : qrDataUrl.value;
      if (!imgSrc) return;

      try {
        const response = await fetch(imgSrc);
        const blob = await response.blob();
        await navigator.clipboard.write([
          new ClipboardItem({ 'image/png': blob })
        ]);
        copied.value = true;
        showToast('Copied to clipboard!', 'success');
        setTimeout(() => { copied.value = false; }, 2000);
      } catch (err) {
        // Fallback: copy data URL as text
        try {
          await navigator.clipboard.writeText(imgSrc);
          copied.value = true;
          showToast('Copied image data to clipboard!', 'success');
          setTimeout(() => { copied.value = false; }, 2000);
        } catch (e) {
          showToast('Copy failed — try downloading instead', 'error');
        }
      }
    }

    // ─── Color Presets ───────────────────────────
    const colorPresets = [
      { name: 'Classic',      fg: '#000000', bg: '#ffffff' },
      { name: 'Indigo',       fg: '#1e1b4b', bg: '#eef2ff' },
      { name: 'Ocean',        fg: '#0c4a6e', bg: '#e0f2fe' },
      { name: 'Forest',       fg: '#14532d', bg: '#f0fdf4' },
      { name: 'Sunset',       fg: '#7c2d12', bg: '#fff7ed' },
      { name: 'Berry',        fg: '#701a75', bg: '#fdf4ff' },
      { name: 'Midnight',     fg: '#e0e7ff', bg: '#1e1b4b' },
      { name: 'Dark Teal',    fg: '#5eead4', bg: '#042f2e' },
      { name: 'Neon',         fg: '#22d3ee', bg: '#0f172a' },
      { name: 'Rose Gold',    fg: '#9f1239', bg: '#fff1f2' },
    ];

    function applyPreset(preset) {
      options.colorDark = preset.fg;
      options.colorLight = preset.bg;
      generateQR();
    }

    // ─── Batch Generate ──────────────────────────
    const batchInput = ref('');

    async function generateBatch() {
      const lines = batchInput.value.trim().split('\n').filter(l => l.trim());
      if (!lines.length) return;

      showToast(`Generating ${lines.length} QR codes...`, 'success');

      for (let i = 0; i < lines.length; i++) {
        const data = lines[i].trim();
        if (!data) continue;

        try {
          const canvas = document.createElement('canvas');
          await QRCode.toCanvas(canvas, data, {
            width: options.width,
            margin: options.margin,
            color: {
              dark: options.colorDark,
              light: options.colorLight,
            },
            errorCorrectionLevel: options.errorCorrectionLevel,
          });

          canvas.toBlob((blob) => {
            downloadBlob(blob, `qrcode-batch-${i + 1}.png`);
          }, 'image/png');

          // Small delay between downloads
          await new Promise(r => setTimeout(r, 300));
        } catch (err) {
          console.error(`Batch #${i + 1} error:`, err);
        }
      }
    }

    // ─── History ─────────────────────────────────
    const history = ref([]);
    const MAX_HISTORY = 20;

    function loadHistory() {
      try {
        const saved = localStorage.getItem('qr-history');
        if (saved) history.value = JSON.parse(saved);
      } catch (e) {
        history.value = [];
      }
    }

    function saveHistory() {
      try {
        localStorage.setItem('qr-history', JSON.stringify(history.value));
      } catch (e) { /* storage full */ }
    }

    function addToHistory(data) {
      const imgSrc = (logoDataUrl.value && qrWithLogoUrl.value) ? qrWithLogoUrl.value : qrDataUrl.value;
      if (!imgSrc) return;

      // Remove duplicate
      history.value = history.value.filter(h => h.data !== data);

      // Add to front
      history.value.unshift({
        data,
        type: qrType.value,
        label: data.length > 60 ? data.substring(0, 57) + '...' : data,
        dataUrl: imgSrc,
        timestamp: new Date().toLocaleTimeString(),
        options: { ...options },
      });

      // Trim
      if (history.value.length > MAX_HISTORY) {
        history.value = history.value.slice(0, MAX_HISTORY);
      }

      saveHistory();
    }

    function loadFromHistory(item) {
      // Restore type and data
      qrType.value = item.type;
      switch (item.type) {
        case 'url': content.url = item.data; break;
        case 'text': content.text = item.data; break;
        case 'phone': content.phone = item.data.replace('tel:', ''); break;
        default: break; // Complex types just regenerate
      }

      // Restore options
      if (item.options) {
        Object.assign(options, item.options);
      }

      generateQR();
    }

    function clearHistory() {
      history.value = [];
      localStorage.removeItem('qr-history');
    }

    // ─── Toast Notifications ─────────────────────
    const toast = reactive({
      show: false,
      message: '',
      type: 'success',
    });

    let toastTimer = null;

    function showToast(message, type = 'success') {
      clearTimeout(toastTimer);
      toast.show = true;
      toast.message = message;
      toast.type = type;
      toastTimer = setTimeout(() => {
        toast.show = false;
      }, 3000);
    }

    // ─── Lifecycle ───────────────────────────────
    onMounted(() => {
      loadHistory();
    });

    // ─── Return ──────────────────────────────────
    return {
      // Dark mode
      darkMode,
      // QR types
      qrTypes,
      qrType,
      // Content
      content,
      // Options
      options,
      downloadFormat,
      showAdvanced,
      showWifiPass,
      // Logo
      logoDataUrl,
      handleLogoUpload,
      removeLogo,
      // QR output
      qrDataUrl,
      qrWithLogoUrl,
      qrCanvas,
      generateQR,
      shortUrl,
      useShortUrl,
      isShortening,
      // Download
      downloadQR,
      // Clipboard
      copied,
      copyToClipboard,
      // Presets
      colorPresets,
      applyPreset,
      // Batch
      batchInput,
      generateBatch,
      // History
      history,
      loadFromHistory,
      clearHistory,
      // Toast
      toast,
      showToast,
    };
  },
});

app.mount('#app');
