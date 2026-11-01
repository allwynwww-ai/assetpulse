<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
{{-- Theme + desktop sidebar state applied before first paint (no flash) --}}
<script>try{var d=document.documentElement,t=localStorage.getItem('ap.theme');if(t==='dark')d.classList.add('dark');if(localStorage.getItem('ap.sidebar')==='collapsed')d.classList.add('sb-collapsed')}catch(e){}</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
