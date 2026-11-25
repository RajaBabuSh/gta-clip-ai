// assets/js/main.js - GTA CLIP AI ULTIMATE Frontend Engine

function showToast(message, type = 'info') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  
  const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : type === 'warning' ? '⚠' : 'ℹ';
  toast.innerHTML = `<span style="font-weight:bold; font-size:16px;">${icon}</span> <span>${message}</span>`;
  
  if (type === 'success') toast.style.borderColor = 'rgba(16, 185, 129, 0.4)';
  if (type === 'error') toast.style.borderColor = 'rgba(239, 68, 68, 0.4)';
  if (type === 'warning') toast.style.borderColor = 'rgba(245, 158, 11, 0.4)';

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(100%)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

function copyText(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      showToast('Copied to clipboard!', 'success');
    }).catch(() => {
      showToast('Failed to copy', 'error');
    });
  } else {
    const input = document.createElement('input');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();
    showToast('Copied to clipboard!', 'success');
  }
}

// Real-Time Job Progress Polling
function pollJobProgress(jobId, onProgress, onComplete, onError) {
  const base = window.APP_URL ? window.APP_URL.replace(/\/+$/, '') : '';
  const pollUrl = base ? `${base}/api/jobs.php?id=${encodeURIComponent(jobId)}` : `api/jobs.php?id=${encodeURIComponent(jobId)}`;

  const interval = setInterval(async () => {
    try {
      const res = await fetch(pollUrl);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      if (data.status === 'completed' || data.status === 'partially_completed') {
        clearInterval(interval);
        if (onComplete) onComplete(data);
      } else if (data.status === 'failed') {
        clearInterval(interval);
        if (onError) onError(data.error_message || 'Processing failed', data);
      } else {
        if (onProgress) onProgress(data.progress || 0, data.current_step || 'Processing...', data);
      }
    } catch (err) {
      console.warn('Job poll error:', err);
    }
  }, 2000);
  return interval;
}

// Auto preview clips on hover
document.addEventListener('DOMContentLoaded', () => {
  const previewVideos = document.querySelectorAll('.clip-card video');
  previewVideos.forEach(vid => {
    vid.addEventListener('mouseenter', () => {
      vid.play().catch(() => {});
    });
    vid.addEventListener('mouseleave', () => {
      vid.pause();
      vid.currentTime = 0;
    });
  });
});
