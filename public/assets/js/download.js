/**
 * Siratie (سيرتي) Smart Download & Client Telemetry Module
 * Version: 1.0.0 (Production)
 * Device detection: Android, iOS/iPadOS, Desktop (QR code)
 * Pre-launch status management & GA4 event telemetry
 */

(function () {
  'use strict';

  function initSmartDownload() {
    // 1. Device & Environment Detection
    const ua = navigator.userAgent || '';
    const isAndroid = /Android/i.test(ua);
    const isIOS = /iPhone|iPad|iPod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isDesktop = !isAndroid && !isIOS;

    const pageLang = document.documentElement.lang || 'ar';
    const isArabic = pageLang.startsWith('ar');
    const pagePath = window.location.pathname;

    // 2. DOM Elements
    const downloadSections = document.querySelectorAll('[data-component="smart-download"], .smart-download-section');
    if (!downloadSections.length) return;

    downloadSections.forEach(function (section) {
      const btnApple = section.querySelector('#btnAppStore, [data-store="ios"], .btn-apple');
      const btnGoogle = section.querySelector('#btnGooglePlay, [data-store="android"], .btn-google');
      const qrBox = section.querySelector('#desktopQrBox, .qr-code-box, .desktop-qr-container');

      // Platform Tailoring
      if (isAndroid) {
        if (btnApple) btnApple.style.display = 'none';
        if (btnGoogle) {
          btnGoogle.classList.add('btn-primary-highlight');
          btnGoogle.addEventListener('click', function () {
            triggerGA4('download_click_android', {
              device: 'android',
              link_url: btnGoogle.getAttribute('href'),
              page_path: pagePath,
              page_language: pageLang
            });
          });
        }
        if (qrBox) qrBox.style.display = 'none';
      } else if (isIOS) {
        if (btnGoogle) btnGoogle.style.display = 'none';
        if (btnApple) {
          btnApple.classList.add('btn-primary-highlight');
          btnApple.addEventListener('click', function () {
            triggerGA4('download_click_ios', {
              device: 'ios',
              link_url: btnApple.getAttribute('href'),
              page_path: pagePath,
              page_language: pageLang
            });
          });
        }
        if (qrBox) qrBox.style.display = 'none';
      } else {
        // Desktop Experience: Dual badges + QR code
        if (qrBox) {
          qrBox.style.display = 'flex';
          observeQrView(qrBox, pagePath, pageLang);
        }

        if (btnApple) {
          btnApple.addEventListener('click', function () {
            triggerGA4('download_click_desktop', {
              device: 'desktop',
              store_target: 'app_store',
              link_url: btnApple.getAttribute('href'),
              page_path: pagePath,
              page_language: pageLang
            });
          });
        }

        if (btnGoogle) {
          btnGoogle.addEventListener('click', function () {
            triggerGA4('download_click_desktop', {
              device: 'desktop',
              store_target: 'google_play',
              link_url: btnGoogle.getAttribute('href'),
              page_path: pagePath,
              page_language: pageLang
            });
          });
        }
      }
    });
  }

  // GA4 Event Dispatcher Guard
  function triggerGA4(eventName, eventParams) {
    if (typeof window.gtag === 'function') {
      window.gtag('event', eventName, eventParams);
    } else if (window.dataLayer && Array.isArray(window.dataLayer)) {
      window.dataLayer.push({
        event: eventName,
        ...eventParams
      });
    }
  }

  // QR Code View Observer
  function observeQrView(element, pagePath, pageLang) {
    if (!element || !('IntersectionObserver' in window)) return;
    let viewed = false;
    const observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && !viewed) {
          viewed = true;
          triggerGA4('qr_view', {
            trigger_method: 'viewport_intersection',
            page_path: pagePath,
            page_language: pageLang
          });
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });
    observer.observe(element);
  }

  // Theme Toggle Support (Optional UI enhancement)
  function initThemeToggle() {
    const toggleButtons = document.querySelectorAll('[data-action="toggle-theme"], .theme-toggle-btn');
    const storedTheme = localStorage.getItem('siratie-theme');
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const currentTheme = storedTheme || (prefersDark ? 'dark' : 'light');

    document.documentElement.setAttribute('data-theme', currentTheme);

    toggleButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const activeTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', activeTheme);
        localStorage.setItem('siratie-theme', activeTheme);
      });
    });
  }

  // DOM Ready Execution
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initSmartDownload();
      initThemeToggle();
    });
  } else {
    initSmartDownload();
    initThemeToggle();
  }

  // Expose API for programmatic testing & inspection
  window.SiratieSmartDownload = {
    init: initSmartDownload,
    triggerGA4: triggerGA4
  };
})();
