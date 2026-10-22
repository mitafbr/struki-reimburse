<?php
// Logo Struki: ikon aplikasi gradasi biru–ungu berisi struk bergerigi + centang.
// $anim = true → struk "tercetak" lalu centang tergambar saat halaman dibuka.
function logo_mark($size = 32, $anim = false, $variant = 'grad') {
    static $n = 0;
    $id = 'lg' . (++$n);
    $bg    = $variant === 'white' ? '#fff' : "url(#$id)";
    $paper = $variant === 'white' ? '#2B59FF' : '#fff';
    $tick  = $variant === 'white' ? '#fff' : '#2B59FF';
    return '<svg class="mark' . ($anim ? ' anim' : '') . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 40 40" aria-hidden="true">'
        . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2B59FF"/><stop offset="1" stop-color="#8B5CF6"/></linearGradient></defs>'
        . '<rect width="40" height="40" rx="11" fill="' . $bg . '"/>'
        . '<path class="mk-paper" d="M13 8h14a2 2 0 0 1 2 2v21l-2.25-1.5-2.25 1.5-2.25-1.5-2.25 1.5-2.25-1.5-2.25 1.5-2.25-1.5L11 31V10a2 2 0 0 1 2-2z" fill="' . $paper . '"/>'
        . '<path class="mk-tick" d="M15.5 20.5l3 3 6-6.5" fill="none" stroke="' . $tick . '" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>'
        . '</svg>';
}
function logo($href = 'index.php', $anim = false, $sub = true) {
    return '<a class="logo" href="' . $href . '" aria-label="' . APP_NAME . ' beranda">' . logo_mark(32, $anim)
        . '<span class="logo-txt"><b>Struki</b>' . ($sub ? '<small>' . APP_COMPANY . '</small>' : '') . '</span></a>';
}
