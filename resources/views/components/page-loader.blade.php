{{-- Loader layar penuh: logo Y Yoka + titik beranimasi. Tampil saat halaman dimuat & saat pindah halaman. --}}
<style>
    #yoka-loader{position:fixed;inset:0;z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:18px;background:rgba(255,255,255,.96);opacity:1;visibility:visible;transition:opacity .25s ease,visibility .25s ease}
    #yoka-loader.is-hidden{opacity:0;visibility:hidden;pointer-events:none}
    #yoka-loader img{width:64px;height:64px;object-fit:contain;animation:yoka-bob 1.2s ease-in-out infinite}
    #yoka-loader .dots{display:flex;gap:10px}
    #yoka-loader .dots i{width:12px;height:12px;border-radius:9999px;background:#10b981;animation:yoka-dot 1.2s ease-in-out infinite}
    #yoka-loader .dots i:nth-child(2){animation-delay:.1s}#yoka-loader .dots i:nth-child(3){animation-delay:.2s}
    #yoka-loader .dots i:nth-child(4){animation-delay:.3s}#yoka-loader .dots i:nth-child(5){animation-delay:.4s}
    #yoka-loader p{font-size:14px;font-weight:600;color:#0f172a}
    @keyframes yoka-bob{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-6px) scale(1.06)}}
    @keyframes yoka-dot{0%,80%,100%{background:#e5e7eb;transform:scale(.8)}40%{background:#10b981;transform:scale(1.15)}}
    @media (prefers-reduced-motion:reduce){#yoka-loader img,#yoka-loader .dots i{animation:none}}
</style>
<div id="yoka-loader" role="status" aria-live="polite">
    <img src="{{ asset('images/brand/yoka-icon.png') }}" alt="Yoka">
    <div class="dots"><i></i><i></i><i></i><i></i><i></i></div>
    <p>Tunggu sebentar, ya...</p>
</div>
<script>
(function(){
    var el=document.getElementById('yoka-loader');
    var hide=function(){el.classList.add('is-hidden')};
    var show=function(){el.classList.remove('is-hidden')};
    window.addEventListener('load',function(){setTimeout(hide,150)});
    window.addEventListener('pageshow',function(e){if(e.persisted)hide()});
    setTimeout(hide,8000);
    document.addEventListener('click',function(e){
        var a=e.target.closest&&e.target.closest('a[href]');
        if(!a||e.defaultPrevented||e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.button!==0)return;
        if(a.target&&a.target!=='_self')return;
        if(a.hasAttribute('download')||/export|pdf|print|struk/i.test(a.pathname))return;
        var h=a.getAttribute('href');
        if(!h||h.charAt(0)==='#'||/^(javascript|mailto|tel):/i.test(h))return;
        if(a.origin!==location.origin)return;
        if(a.pathname===location.pathname&&a.search===location.search)return;
        show();setTimeout(hide,8000);
    });
    document.addEventListener('submit',function(e){
        var f=e.target;if(e.defaultPrevented||(f.target&&f.target!=='_self'))return;
        setTimeout(function(){if(!e.defaultPrevented)show()},0);setTimeout(hide,8000);
    });
})();
</script>
