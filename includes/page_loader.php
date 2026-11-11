<?php
/**
 * Page Loading Screen
 * Included right after <body> on every page (public, admin, login).
 *
 * - Visible while the page loads; main.js fades it out when the page is ready.
 * - Shown again when the visitor clicks a link to another page.
 * - Safe without JavaScript: CSS hides it automatically after a few seconds,
 *   and <noscript> hides it immediately.
 * - Uses no relative URLs, so it works from any folder.
 * - Carries its own essential styles below, so it is ALWAYS a full-screen
 *   overlay that hides itself, even if the main stylesheet is an old cached
 *   copy or fails to load (it can never appear as a bar on the page).
 */
?>
<style>
  #page-loader {
    position: fixed; top: 0; right: 0; bottom: 0; left: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
    background-color: #0A1E3C; color: #FFFFFF;
    animation: page-loader-autohide 0.35s ease 6s forwards;
  }
  #page-loader.is-hidden { display: none; }
  @keyframes page-loader-autohide { to { opacity: 0; visibility: hidden; pointer-events: none; } }
</style>
<div class="page-loader" id="page-loader" role="status" aria-live="polite" aria-label="Loading page">
  <div class="page-loader-inner">
    <div class="page-loader-mark">
      <span class="page-loader-ring" aria-hidden="true"></span>
      <span class="page-loader-logo" aria-hidden="true"><i class="fa-solid fa-graduation-cap"></i></span>
    </div>
    <div class="page-loader-brand">UDOM Student Materials</div>
    <div class="page-loader-dots" aria-hidden="true"><span></span><span></span><span></span></div>
    <?php /* Filled by main.js when needed, e.g. "Uploading 3 files (12.4 MB)" */ ?>
    <div class="page-loader-message" id="page-loader-message"></div>
    <span class="visually-hidden">Loading, please wait.</span>
  </div>
</div>
<noscript>
  <style>.page-loader { display: none !important; }</style>
</noscript>
