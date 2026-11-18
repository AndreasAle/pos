{{-- Meta SEO bersama. Wajib: $seoTitle, $seoDescription. Opsional: $seoImage (path public), $seoType.
     Canonical selalu memakai APP_URL (tanpa www), supaya www.yokapos.com dan yokapos.com tidak
     dihitung Google sebagai dua halaman berbeda. --}}
@php
    $seoBase = rtrim((string) config('app.url'), '/');
    $seoPath = trim(request()->path(), '/');
    $seoUrl = $seoPath === '' ? $seoBase : $seoBase.'/'.$seoPath;
    $seoImageUrl = $seoBase.'/'.ltrim($seoImage ?? 'images/hero/slide-kasir.webp', '/');
@endphp
<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<link rel="canonical" href="{{ $seoUrl }}">
<meta name="robots" content="index,follow,max-image-preview:large">
<meta property="og:site_name" content="Yoka POS">
<meta property="og:locale" content="id_ID">
<meta property="og:type" content="{{ $seoType ?? 'website' }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:image" content="{{ $seoImageUrl }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImageUrl }}">
