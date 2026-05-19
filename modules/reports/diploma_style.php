<?php
/**
 * BENDRAS diplomas stilius – VEIKIA IR HTML, IR PDF (TCPDF)
 * 
 * LOGOTIPAS: keisk čia
 */
$logo_text = 'OL'; // ← KEISK ČIA: 'LT', 'MOK', 'ABC'

// SVG logotipas (veikia TCPDF)
$logo_svg = '
<svg width="90" height="90" viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg" style="margin:0 auto 25px;display:block;">
  <defs>
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#007bff;" />
      <stop offset="100%" style="stop-color:#0056b3;" />
    </linearGradient>
  </defs>
  <circle cx="45" cy="45" r="45" fill="url(#grad)" />
  <text x="45" y="58" font-family="Arial, sans-serif" font-size="28" font-weight="bold" fill="white" text-anchor="middle">OL</text>
</svg>';

// Jei nori savo logotipą – pakeisk $logo_svg į:
// $logo_svg = '<img src="' . $root . '/images/logo.png" width="90" height="90" style="border-radius:50%;margin:0 auto 25px;display:block;">';

// Šriftas
$font_name = 'dejavusans';

// CSS (PDF + HTML)
$style_css = '
<style>
    @page { margin: 0; size: A4; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #f9f9f9; font-family: \'' . $font_name . '\', Arial, sans-serif; }
    .diplomas { width: 210mm; height: 297mm; margin: 20mm auto; background: white; position: relative; overflow: hidden; box-shadow: 0 0 25px rgba(0,0,0,0.15); }
    .bg-pattern { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url(\'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect width="100" height="100" fill="%23fff"/><path d="M0,50 Q25,30 50,50 T100,50" stroke="%23f0f0f0" stroke-width="2" fill="none"/></svg>\') repeat; opacity: 0.3; z-index: 0; }
    .content { position: relative; z-index: 2; padding: 60px 80px; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; }
    h1 { font-size: 38px; color: #1a1a1a; margin: 25px 0; font-weight: 300; letter-spacing: 1px; }
    .subtitle { font-size: 21px; color: #555; margin-bottom: 45px; font-style: italic; }
    .laureatas { font-size: 52px; font-weight: bold; color: #d4af37; margin: 35px 0; }
    .vardas { font-size: 44px; color: #2c3e50; margin: 25px 0; font-weight: bold; }
    .mokykla { font-size: 26px; color: #444; margin: 18px 0; line-height: 1.3; }
    .olimpiada { font-size: 24px; color: #666; margin: 35px 0; font-style: italic; }
    .data { font-size: 19px; color: #888; margin-top: 60px; }
    .dip-nr { position: absolute; top: 30px; right: 40px; font-size: 14px; color: #999; font-weight: bold; }
    .footer { position: absolute; bottom: 35px; left: 0; right: 0; text-align: center; font-size: 14px; color: #bbb; }
    .no-print { text-align: center; margin: 20px; }
    @media print { body, .diplomas { background: white !important; margin: 0 !important; } .no-print { display: none; } }
</style>';
?>