<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Rendered on the server: a crawler that does not run scripts sees an
         empty application shell and whatever the head tells it. --}}
    <title>{{ $meta->title }}</title>
    @if ($meta->description)
        <meta name="description" content="{{ $meta->description }}">
    @endif
    @unless ($meta->indexable)
        <meta name="robots" content="noindex, follow">
    @endunless
    @if ($meta->canonical)
        <link rel="canonical" href="{{ $meta->canonical }}">
        <meta property="og:url" content="{{ $meta->canonical }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('shop.brand') }}">
    <meta property="og:title" content="{{ $meta->title }}">
    @if ($meta->description)
        <meta property="og:description" content="{{ $meta->description }}">
    @endif
    @if ($meta->imageUrl)
        <meta property="og:image" content="{{ url($meta->imageUrl) }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">

    {{-- The browser takes over the title once the application mounts; these
         are the fallbacks it uses for pages that carry no title of their own. --}}
    <meta name="site-title" content="{{ $meta->siteTitle }}">
    <meta name="site-brand" content="{{ config('shop.brand') }}">
    @if ($meta->structuredData)
        <script type="application/ld+json">{!! json_encode($meta->structuredData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-ink-50 text-ink-900 antialiased">
<div id="app" class="h-full"></div>
</body>
</html>
