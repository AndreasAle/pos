import React, { Suspense, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { Canvas, useFrame, useThree } from '@react-three/fiber';
import { ContactShadows, Environment, OrbitControls, useGLTF } from '@react-three/drei';
import * as THREE from 'three';

const PRODUCTS = [
    { id: 1, name: 'Es Kopi Susu', price: 24000, image: '/images/menu/es-kopi-susu.jpg', category: 'Kopi' },
    { id: 2, name: 'Cappuccino', price: 28000, image: '/images/menu/cappuccino.jpg', category: 'Kopi' },
    { id: 3, name: 'Matcha Latte', price: 30000, image: '/images/menu/matcha-latte.jpg', category: 'Non Kopi' },
    { id: 4, name: 'Teh Tarik', price: 20000, image: '/images/menu/teh-tarik.jpg', category: 'Non Kopi' },
    { id: 5, name: 'Croissant', price: 22000, image: '/images/menu/croissant.jpg', category: 'Makanan' },
    { id: 6, name: 'Roti Bakar', price: 26000, image: '/images/menu/roti-bakar.jpg', category: 'Makanan' },
];

const formatRupiah = (amount) => `Rp${new Intl.NumberFormat('id-ID').format(amount)}`;
const emit = (name, detail = {}) => {
    window.dispatchEvent(new CustomEvent(`yoka:${name}`, { detail }));
    window.dataLayer?.push({ event: name, ...detail });
};

function webglAvailable() {
    try {
        const canvas = document.createElement('canvas');
        return Boolean(window.WebGLRenderingContext && (canvas.getContext('webgl2') || canvas.getContext('webgl')));
    } catch { return false; }
}

function CameraRig({ mode, reducedMotion, receiptInteractive, focusStage }) {
    const { camera, size } = useThree();
    const cameraTarget = useMemo(() => new THREE.Vector3(), []);
    const cameraGuide = useMemo(() => new THREE.PerspectiveCamera(), []);
    const portrait = size.width / Math.max(size.height, 1) < 1;
    const desktopPositions = [
        [0, 0.34, 0.72],
        [0, 0.325, 0.63],
        [0, 0.315, 0.59],
        [-0.075, 0.31, 0.555],
        [0.065, 0.31, 0.565],
        [0, 0.325, 0.625],
        [0.045, 0.315, 0.57],
        [0.16, 0.3, 0.58],
    ];
    const portraitPositions = [
        [0, 0.34, 1.18], [0, 0.33, 1.08], [0, 0.32, 1.02], [-0.04, 0.32, .98],
        [0.04, 0.32, 1], [0, 0.33, 1.08], [0.03, 0.32, 1.01], [0.1, 0.3, .82],
    ];
    const target = (portrait ? portraitPositions : desktopPositions)[focusStage] || (portrait ? portraitPositions[0] : desktopPositions[0]);
    const lookTargets = [
        [0, 0.25, 0], [0, 0.31, 0.03], [0, 0.31, 0.03], [-0.07, 0.3, 0.035],
        [0.065, 0.3, 0.035], [0, 0.3, 0.025], [0.045, 0.3, 0.03], [-0.27, 0.18, 0.05],
    ];
    useFrame((_, delta) => {
        if (mode === 'EXPLORE' || (mode === 'RECEIPT' && receiptInteractive)) return;
        const alpha = reducedMotion ? 1 : 1 - Math.exp(-delta * 3.2);
        camera.position.lerp(new THREE.Vector3(...target), alpha);
        const look = lookTargets[focusStage] || lookTargets[0];
        cameraTarget.set(...look);
        cameraGuide.position.copy(camera.position);
        cameraGuide.lookAt(cameraTarget);
        camera.quaternion.slerp(cameraGuide.quaternion, alpha);
    });
    return null;
}

function interactiveParent(object) {
    let current = object;
    while (current && current.name !== 'YokaPOS_Full_Set') {
        if (['Terminal_Screen', 'Drawer_Inner', 'Printer_Paper', 'Printer_Body', 'Printer_Top', 'Scanner_Trigger', 'Scanner_Body', 'Scanner_Head'].includes(current.name)) return current;
        current = current.parent;
    }
    return object;
}

function receiptTexture(state) {
    const canvas = document.createElement('canvas');
    canvas.width = 512;
    canvas.height = 640;
    const context = canvas.getContext('2d');
    context.fillStyle = '#fffdf4';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.fillStyle = '#172033';
    context.textAlign = 'center';
    context.font = '700 34px ui-monospace, monospace';
    context.fillText('YOKA COFFEE', 256, 58);
    context.font = '20px ui-monospace, monospace';
    context.fillText('STRUK DEMO', 256, 91);
    context.fillText('13 NOV 2026  •  DEMO-001', 256, 121);
    context.setLineDash([8, 7]);
    context.strokeStyle = '#536071';
    context.beginPath(); context.moveTo(35, 148); context.lineTo(477, 148); context.stroke();
    context.setLineDash([]);
    context.textAlign = 'left';
    context.font = '22px ui-monospace, monospace';
    let y = 190;
    state.cart.slice(0, 6).forEach((item) => {
        context.fillText(`${item.qty}x ${item.name}`.slice(0, 24), 38, y);
        context.textAlign = 'right';
        context.fillText(new Intl.NumberFormat('id-ID').format(item.price * item.qty), 474, y);
        context.textAlign = 'left';
        y += 39;
    });
    context.setLineDash([8, 7]);
    context.beginPath(); context.moveTo(35, y + 4); context.lineTo(477, y + 4); context.stroke();
    context.setLineDash([]);
    const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    context.font = '700 27px ui-monospace, monospace';
    context.fillText('TOTAL', 38, y + 50);
    context.textAlign = 'right';
    context.fillText(formatRupiah(total), 474, y + 50);
    context.textAlign = 'left';
    context.font = '21px ui-monospace, monospace';
    context.fillText(`BAYAR  ${state.payment.toUpperCase()}`, 38, y + 88);
    context.textAlign = 'center';
    context.font = '19px ui-monospace, monospace';
    context.fillText('TERIMA KASIH', 256, 584);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;
    return texture;
}

function posPreviewTexture(state) {
    const canvas = document.createElement('canvas');
    canvas.width = 1024;
    canvas.height = 576;
    const context = canvas.getContext('2d');
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;

    if (state.mode === 'CHECKOUT') {
        const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
        context.fillStyle = '#087b43'; context.fillRect(0, 0, 430, 576);
        context.fillStyle = '#e8f7ef'; context.fillRect(430, 0, 594, 576);
        context.fillStyle = '#ffffff'; context.font = '700 22px system-ui'; context.fillText('← Kembali', 45, 60);
        context.fillStyle = '#bbf7d0'; context.font = '700 18px system-ui'; context.fillText('TOTAL PEMBAYARAN', 45, 148);
        context.fillStyle = '#ffffff'; context.font = '800 55px system-ui'; context.fillText(formatRupiah(total), 45, 220);
        context.fillStyle = '#d1fae5'; context.font = '19px system-ui'; context.fillText('Pilih metode pembayaran', 45, 267);
        context.fillStyle = '#172033'; context.font = '700 25px system-ui'; context.fillText('Metode pembayaran', 485, 100);
        [['Tunai', 500], ['QRIS', 750]].forEach(([payment, x]) => {
            context.fillStyle = state.payment === payment ? '#dcfce7' : '#ffffff'; context.beginPath(); context.roundRect(x, 150, 210, 150, 18); context.fill();
            context.strokeStyle = state.payment === payment ? '#16a34a' : '#cbd5e1'; context.lineWidth = 3; context.stroke();
            context.fillStyle = state.payment === payment ? '#15803d' : '#334155'; context.font = '700 26px system-ui'; context.fillText(payment, x + 66, 237);
        });
        context.fillStyle = state.payment ? '#16a34a' : '#cbd5e1'; context.beginPath(); context.roundRect(500, 355, 460, 74, 16); context.fill();
        context.fillStyle = '#ffffff'; context.font = '700 22px system-ui'; context.fillText('Selesaikan pembayaran', 610, 402);
        texture.needsUpdate = true;
        return texture;
    }

    if (state.mode === 'RECEIPT') {
        const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
        context.fillStyle = '#e8f7ef'; context.fillRect(0, 0, 1024, 576);
        context.fillStyle = '#16a34a'; context.beginPath(); context.arc(512, 195, 72, 0, Math.PI * 2); context.fill();
        context.strokeStyle = '#ffffff'; context.lineWidth = 14; context.beginPath(); context.moveTo(475, 195); context.lineTo(502, 222); context.lineTo(555, 162); context.stroke();
        context.fillStyle = '#15803d'; context.textAlign = 'center'; context.font = '700 22px system-ui'; context.fillText(`PEMBAYARAN ${state.payment.toUpperCase()} BERHASIL`, 512, 310);
        context.fillStyle = '#0B1B3F'; context.font = '800 46px system-ui'; context.fillText(formatRupiah(total), 512, 375);
        context.fillStyle = '#64748b'; context.font = '20px system-ui'; context.fillText('Struk sedang keluar dari printer', 512, 420);
        context.textAlign = 'left'; texture.needsUpdate = true;
        return texture;
    }

    context.fillStyle = '#e1f3e8'; context.fillRect(0, 0, 1024, 576);
    context.fillStyle = '#078548'; context.fillRect(0, 0, 1024, 78);
    context.fillStyle = '#ffffff'; context.font = '700 31px system-ui'; context.fillText('yoka POS', 34, 50);
    const tabs = ['Semua', 'Kopi', 'Non Kopi', 'Makanan'];
    tabs.forEach((tab, index) => {
        context.fillStyle = state.category === tab ? '#059669' : '#f1f5f9';
        const x = 28 + index * 132;
        context.beginPath(); context.roundRect(x, 95, 116, 45, 22); context.fill();
        context.fillStyle = state.category === tab ? '#ffffff' : '#475569'; context.font = '700 18px system-ui'; context.fillText(tab, x + 20, 124);
    });
    const colors = ['#d7a36b', '#9a633c', '#8bbd72', '#dfc7a5', '#d8ad7b', '#d29b62'];
    const visible = state.category === 'Semua' ? PRODUCTS : PRODUCTS.filter((product) => product.category === state.category);
    for (let index = 0; index < visible.length; index += 1) {
        const product = visible[index];
        const column = index % 3; const row = Math.floor(index / 3); const x = 30 + column * 205; const y = 162 + row * 182;
        context.fillStyle = '#ffffff'; context.beginPath(); context.roundRect(x, y, 180, 158, 14); context.fill();
        context.fillStyle = colors[product.id - 1]; context.beginPath(); context.roundRect(x, y, 180, 88, [14, 14, 0, 0]); context.fill();
        const image = new Image(); image.src = product.image; image.onload = () => { context.save(); context.beginPath(); context.roundRect(x, y, 180, 88, [14, 14, 0, 0]); context.clip(); context.drawImage(image, x, y, 180, 88); context.restore(); texture.needsUpdate = true; };
        context.fillStyle = '#172033'; context.font = '700 17px system-ui'; context.fillText(product.name, x + 13, y + 116);
        context.fillStyle = '#078548'; context.font = '700 16px system-ui'; context.fillText(formatRupiah(product.price), x + 13, y + 141);
    }
    context.fillStyle = '#ffffff'; context.fillRect(650, 78, 374, 498);
    context.strokeStyle = '#e2e8f0'; context.beginPath(); context.moveTo(650, 78); context.lineTo(650, 576); context.stroke();
    context.fillStyle = '#172033'; context.font = '700 24px system-ui'; context.fillText('Pesanan', 684, 125);
    context.fillStyle = '#64748b'; context.font = '17px system-ui'; context.fillText(`${state.cart.reduce((sum, item) => sum + item.qty, 0)} item`, 684, 155);
    if (!state.cart.length) {
        context.setLineDash([8, 7]); context.strokeStyle = '#cbd5e1'; context.strokeRect(684, 184, 304, 242); context.setLineDash([]);
        context.fillStyle = '#64748b'; context.textAlign = 'center'; context.font = '18px system-ui'; context.fillText('Pilih produk untuk membuat pesanan', 836, 310); context.textAlign = 'left';
    } else {
        state.cart.slice(0, 5).forEach((item, index) => {
            const y = 205 + index * 48; context.fillStyle = '#172033'; context.font = '700 17px system-ui'; context.fillText(`${item.qty}× ${item.name}`, 684, y);
            context.textAlign = 'right'; context.font = '16px system-ui'; context.fillText(formatRupiah(item.price * item.qty), 986, y); context.textAlign = 'left';
        });
    }
    const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    context.fillStyle = '#64748b'; context.font = '18px system-ui'; context.fillText('Total', 684, 475);
    context.fillStyle = '#172033'; context.textAlign = 'right'; context.font = '700 24px system-ui'; context.fillText(formatRupiah(total), 988, 475); context.textAlign = 'left';
    context.fillStyle = state.cart.length ? '#16a34a' : '#cbd5e1'; context.beginPath(); context.roundRect(684, 500, 304, 56, 12); context.fill();
    context.fillStyle = '#ffffff'; context.textAlign = 'center'; context.font = '700 18px system-ui'; context.fillText('Bayar sekarang', 836, 535); context.textAlign = 'left';
    texture.needsUpdate = true;
    return texture;
}

function HardwareModel({ onScreenClick, onDetail, state, dispatch }) {
    const source = useGLTF('/models/yokapos-full-set.glb');
    const model = useMemo(() => source.scene.clone(true), [source.scene]);
    const drawer = useMemo(() => model.getObjectByName('Drawer_Inner'), [model]);
    const receipt = useMemo(() => model.getObjectByName('Printer_Paper'), [model]);
    const screen = useMemo(() => model.getObjectByName('Terminal_Screen'), [model]);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [paperOut, setPaperOut] = useState(false);

    useEffect(() => {
        if (!screen) return undefined;
        const texture = posPreviewTexture(state);
        const previousMaterial = screen.material;
        screen.material = new THREE.MeshBasicMaterial({ map: texture, toneMapped: false });
        return () => { screen.material.dispose(); texture.dispose(); screen.material = previousMaterial; };
    }, [screen, state.mode, state.category, state.cart, state.payment]);

    useEffect(() => {
        const completed = state.mode === 'RECEIPT';
        setPaperOut(completed);
        setDrawerOpen(completed && state.payment === 'Tunai');
    }, [state.mode, state.payment]);

    useEffect(() => {
        if (!receipt || state.mode !== 'RECEIPT') return undefined;
        const texture = receiptTexture(state);
        const previousMaterial = receipt.material;
        receipt.material = new THREE.MeshStandardMaterial({ map: texture, color: '#fffdf4', roughness: 0.96, metalness: 0, side: THREE.DoubleSide });
        return () => {
            receipt.material.dispose();
            texture.dispose();
            receipt.material = previousMaterial;
        };
    }, [receipt, state.mode, state.cart, state.payment]);

    useFrame((_, delta) => {
        if (drawer) drawer.position.z = THREE.MathUtils.damp(drawer.position.z, drawerOpen ? 0.25 : 0, 6, delta);
        if (receipt) {
            receipt.scale.y = THREE.MathUtils.damp(receipt.scale.y, paperOut ? 1 : 0.03, 7, delta);
            receipt.position.y = THREE.MathUtils.damp(receipt.position.y, paperOut ? 0.192 : 0.143, 7, delta);
        }
    });

    const interact = (event) => {
        event.stopPropagation();
        const target = interactiveParent(event.object);
        if (target.name === 'Terminal_Screen') {
            if (state.mode === 'DEMO' && event.uv) {
                const x = event.uv.x * 1024; const y = (1 - event.uv.y) * 576;
                if (y >= 95 && y <= 145) { const index = Math.floor((x - 28) / 132); if (index >= 0 && index < 4) dispatch({ type: 'CATEGORY', category: ['Semua', 'Kopi', 'Non Kopi', 'Makanan'][index] }); return; }
                if (x < 650 && y >= 162 && y <= 526) {
                    const column = Math.floor((x - 30) / 205); const row = Math.floor((y - 162) / 182); const index = row * 3 + column;
                    const visible = state.category === 'Semua' ? PRODUCTS : PRODUCTS.filter((product) => product.category === state.category);
                    if (column >= 0 && column < 3 && visible[index]) { dispatch({ type: 'ADD', product: visible[index] }); emit('demo_product_added', { product_id: visible[index].id }); }
                    return;
                }
                if (x >= 684 && y >= 495 && state.cart.length) { dispatch({ type: 'MODE', mode: 'CHECKOUT' }); emit('demo_checkout_started'); }
                return;
            }
            if (state.mode === 'CHECKOUT' && event.uv) {
                const x = event.uv.x * 1024; const y = (1 - event.uv.y) * 576;
                if (x < 430 && y < 100) { dispatch({ type: 'MODE', mode: 'DEMO' }); return; }
                if (y >= 145 && y <= 310 && x >= 490 && x <= 720) { dispatch({ type: 'PAYMENT', payment: 'Tunai' }); return; }
                if (y >= 145 && y <= 310 && x >= 735 && x <= 980) { dispatch({ type: 'PAYMENT', payment: 'QRIS' }); return; }
                if (y >= 345 && y <= 445 && state.payment) { dispatch({ type: 'MODE', mode: 'RECEIPT' }); emit('demo_completed', { payment_method: state.payment }); }
                return;
            }
            return onScreenClick();
        }
        if (target.name === 'Drawer_Inner') { setDrawerOpen((value) => !value); onDetail('Laci kas terbuka secara visual saja.'); return; }
        if (target.name.startsWith('Printer_')) { setPaperOut((value) => !value); onDetail('Printer thermal · klik lagi untuk menarik kertas.'); return; }
        if (target.name.startsWith('Scanner_')) { emit('demo_scanner_triggered'); onDetail('Barcode berhasil dipindai (simulasi).'); }
    };

    return <group rotation={[0, state.mode === 'EXPLORE' ? -0.05 : 0, 0]}>
        <primitive object={model} onClick={interact} />
    </group>;
}

function Scene({ state, dispatch, setMode, reducedMotion, onDetail, signupUrl, receiptInteractive, focusStage }) {
    return <>
        <ambientLight intensity={1.2} />
        <directionalLight position={[5, 7, 5]} intensity={2.8} color="#fffdf7" />
        <directionalLight position={[-5, 3, 2]} intensity={1.4} color="#bbf7d0" />
        <Suspense fallback={null}><Environment preset="studio" environmentIntensity={0.55} /></Suspense>
        <Suspense fallback={null}><HardwareModel onScreenClick={() => setMode('FOCUS')} onDetail={onDetail} state={state} dispatch={dispatch} /></Suspense>
        <ContactShadows position={[0, -0.01, 0]} opacity={0.5} scale={1.3} blur={2.4} far={1} />
        <CameraRig mode={state.mode} reducedMotion={reducedMotion} receiptInteractive={receiptInteractive} focusStage={focusStage} />
        <OrbitControls
            enabled={state.mode === 'INTRO' || state.mode === 'EXPLORE' || (state.mode === 'RECEIPT' && receiptInteractive)}
            target={state.mode === 'RECEIPT' ? [-0.27, 0.18, 0.05] : [0, 0.25, 0]}
            enablePan={state.mode === 'RECEIPT'}
            enableZoom={state.mode === 'RECEIPT'}
            minDistance={state.mode === 'RECEIPT' ? 0.24 : 0.62}
            maxDistance={state.mode === 'RECEIPT' ? 1.1 : 1.35}
            minPolarAngle={0.55}
            maxPolarAngle={1.58}
            onStart={() => emit('device_rotated', { focus: state.mode === 'RECEIPT' ? 'receipt' : 'hardware' })}
        />
    </>;
}

function Cart({ cart, changeQty, total, onCheckout }) {
    return <aside className="flex min-h-0 flex-col border-l border-slate-200 bg-white lg:w-[330px] lg:shrink-0">
        <div className="border-b border-slate-100 px-5 py-4"><h3 className="font-extrabold text-slate-950">Pesanan</h3><p className="text-xs text-slate-500">Dine in · Meja 04</p></div>
        <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
            {!cart.length && <div className="flex h-full min-h-32 items-center justify-center rounded-xl border border-dashed border-slate-300 text-center text-sm text-slate-500">Pilih produk untuk<br />membuat pesanan</div>}
            {cart.map((item) => <div key={item.id} className="rounded-xl bg-slate-50 p-3">
                <div className="flex justify-between gap-3"><p className="text-sm font-bold text-slate-800">{item.name}</p><p className="text-sm font-extrabold text-slate-900">{formatRupiah(item.price * item.qty)}</p></div>
                <div className="mt-2 flex items-center gap-2">
                    <button aria-label={`Kurangi ${item.name}`} onClick={() => changeQty(item.id, -1)} className="h-9 w-9 rounded-lg border border-slate-200 bg-white text-lg text-slate-700">−</button>
                    <span className="w-6 text-center text-sm font-bold">{item.qty}</span>
                    <button aria-label={`Tambah ${item.name}`} onClick={() => changeQty(item.id, 1)} className="h-9 w-9 rounded-lg bg-emerald-600 text-lg text-white">+</button>
                </div>
            </div>)}
        </div>
        <div className="border-t border-slate-200 p-4">
            <div className="mb-3 flex items-center justify-between"><span className="text-sm text-slate-500">Total</span><strong className="text-xl text-[#0B1B3F]">{formatRupiah(total)}</strong></div>
            <button disabled={!cart.length} onClick={onCheckout} className="min-h-12 w-full rounded-xl bg-emerald-600 px-4 font-extrabold text-white shadow-lg shadow-emerald-200 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">Bayar sekarang</button>
        </div>
    </aside>;
}

function EmbeddedCashierDemo({ state, dispatch }) {
    const categories = ['Semua', 'Kopi', 'Non Kopi', 'Makanan'];
    const visible = state.category === 'Semua' ? PRODUCTS : PRODUCTS.filter((product) => product.category === state.category);
    const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    const count = state.cart.reduce((sum, item) => sum + item.qty, 0);
    const add = (product) => { dispatch({ type: 'ADD', product }); emit('demo_product_added', { product_id: product.id }); };

    return <div className="flex h-full min-h-0 flex-col bg-[#dff3e7] text-slate-900">
        <header className="flex h-[11%] min-h-10 shrink-0 items-center bg-gradient-to-r from-[#087b43] to-[#12a05b] px-[2.5%] text-white">
            <img src="/images/brand/yoka-logo-white.png" alt="Yoka" className="h-[38%] min-h-4 w-auto" /><b className="ml-1 text-[clamp(9px,.92vw,14px)]">POS</b>
            <span className="ml-auto flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[clamp(7px,.58vw,9px)] font-bold"><span className="h-1 w-1 rounded-full bg-emerald-200" />Demo aman</span>
            <button onClick={() => dispatch({ type: 'MODE', mode: 'EXPLORE' })} className="ml-1.5 rounded-full border border-white/20 px-2.5 py-1 text-[clamp(7px,.58vw,9px)] font-bold hover:bg-white/10">Tutup</button>
        </header>

        <div className="flex min-h-0 flex-1">
            <main className="flex min-w-0 flex-1 flex-col">
                <nav className="flex h-[12%] min-h-9 shrink-0 items-center gap-[1.2%] overflow-hidden bg-white px-[2.5%]">
                    {categories.map((category) => <button key={category} onClick={() => dispatch({ type: 'CATEGORY', category })} className={`rounded-full px-[2.7%] py-1 text-[clamp(7px,.62vw,10px)] font-extrabold ${state.category === category ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600'}`}>{category}</button>)}
                </nav>
                <div className="grid min-h-0 flex-1 content-start grid-cols-4 gap-[2.2%] overflow-y-auto p-[2.8%] [grid-auto-rows:42%]">
                    {visible.map((product) => <button key={product.id} onClick={() => add(product)} className="group flex min-h-0 flex-col overflow-hidden rounded-[clamp(5px,.6vw,9px)] border border-emerald-900/10 bg-white text-left shadow-sm transition hover:border-emerald-500/40 hover:shadow-md active:scale-[.97]">
                        <img src={product.image} alt="" className="h-[58%] w-full shrink-0 bg-slate-50 object-cover" />
                        <span className="block min-h-0 flex-1 px-[8%] py-[5%]"><b className="block truncate text-[clamp(7px,.57vw,9px)] leading-tight">{product.name}</b><span className="mt-0.5 block text-[clamp(7px,.55vw,9px)] font-extrabold text-emerald-700">{formatRupiah(product.price)}</span></span>
                    </button>)}
                </div>
            </main>

            <aside className="flex w-[29%] min-w-0 shrink-0 flex-col border-l border-slate-200 bg-white">
                <div className="shrink-0 border-b border-slate-100 px-[8%] py-[5%]"><div className="flex items-center justify-between"><b className="text-[clamp(9px,.78vw,12px)]">Pesanan</b>{count > 0 && <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[clamp(6px,.5vw,8px)] font-bold text-emerald-700">{count} item</span>}</div><p className="mt-0.5 text-[clamp(6px,.52vw,8px)] text-slate-500">Dine in · Meja 04</p></div>
                <div className="min-h-0 flex-1 space-y-[4%] overflow-y-auto p-[6%]">
                    {!state.cart.length && <div className="flex h-full items-center justify-center rounded-lg border border-dashed border-slate-300 px-2 text-center text-[clamp(7px,.56vw,9px)] leading-relaxed text-slate-500">Pilih produk untuk<br />membuat pesanan</div>}
                    {state.cart.map((item) => <div key={item.id} className="rounded-lg bg-slate-50 p-[4%]">
                        <div className="flex items-start justify-between gap-2"><b className="truncate text-[clamp(7px,.56vw,9px)]">{item.name}</b><span className="shrink-0 text-[clamp(6px,.5vw,8px)] font-extrabold">{formatRupiah(item.price * item.qty)}</span></div>
                        <div className="mt-[5%] flex items-center gap-1.5"><button aria-label={`Kurangi ${item.name}`} onClick={() => dispatch({ type: 'QTY', id: item.id, delta: -1 })} className="flex h-5 w-5 items-center justify-center rounded border border-slate-200 bg-white text-xs">−</button><span className="w-4 text-center text-[9px] font-bold">{item.qty}</span><button aria-label={`Tambah ${item.name}`} onClick={() => dispatch({ type: 'QTY', id: item.id, delta: 1 })} className="flex h-5 w-5 items-center justify-center rounded bg-emerald-600 text-xs text-white">+</button></div>
                    </div>)}
                </div>
                <div className="shrink-0 border-t border-slate-200 p-[5%]"><div className="flex items-center justify-between"><span className="text-[clamp(7px,.56vw,9px)] text-slate-500">Total</span><b className="text-[clamp(9px,.78vw,12px)] text-[#0B1B3F]">{formatRupiah(total)}</b></div><button disabled={!state.cart.length} onClick={() => { dispatch({ type: 'MODE', mode: 'CHECKOUT' }); emit('demo_checkout_started', { total }); }} className="mt-[4%] w-full rounded-lg bg-emerald-600 py-[4%] text-[clamp(7px,.58vw,9px)] font-extrabold text-white disabled:bg-slate-300">Bayar sekarang</button></div>
            </aside>
        </div>
    </div>;
}

function EmbeddedCheckout({ state, dispatch }) {
    const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    return <div className="flex h-full bg-[#e8f7ef] text-slate-900">
        <div className="flex w-[42%] flex-col bg-[#087b43] p-[6%] text-white"><button onClick={() => dispatch({ type: 'MODE', mode: 'DEMO' })} className="w-fit text-[clamp(8px,.72vw,11px)] font-bold text-white/80">← Kembali</button><div className="my-auto"><p className="text-[clamp(8px,.72vw,11px)] font-extrabold uppercase tracking-wider text-emerald-200">Total pembayaran</p><h3 className="mt-2 text-[clamp(22px,2.5vw,40px)] font-extrabold">{formatRupiah(total)}</h3><p className="mt-3 max-w-xs text-[clamp(8px,.72vw,11px)] leading-relaxed text-emerald-50/80">Pilih metode pembayaran untuk melanjutkan simulasi transaksi.</p></div><p className="text-[clamp(7px,.62vw,10px)] text-emerald-100/70">Tidak terhubung ke pembayaran nyata</p></div>
        <div className="flex flex-1 flex-col justify-center p-[7%]"><p className="text-[clamp(9px,.85vw,13px)] font-extrabold text-[#0B1B3F]">Metode pembayaran</p><div className="mt-[5%] grid grid-cols-2 gap-[4%]">{['Tunai', 'QRIS'].map((payment) => <button key={payment} onClick={() => dispatch({ type: 'PAYMENT', payment })} className={`aspect-[1.35] rounded-xl border-2 text-[clamp(10px,.9vw,14px)] font-extrabold transition ${state.payment === payment ? 'border-emerald-600 bg-emerald-50 text-emerald-700 shadow-sm' : 'border-slate-200 bg-white text-slate-600'}`}>{payment}</button>)}</div><button disabled={!state.payment} onClick={() => { dispatch({ type: 'MODE', mode: 'RECEIPT' }); emit('demo_completed', { payment_method: state.payment, total }); }} className="mt-[7%] rounded-xl bg-emerald-600 py-[4%] text-[clamp(9px,.8vw,13px)] font-extrabold text-white shadow-lg disabled:bg-slate-300 disabled:shadow-none">Selesaikan pembayaran</button></div>
    </div>;
}

function CashierDemo({ state, dispatch }) {
    const categories = ['Semua', 'Kopi', 'Non Kopi', 'Makanan'];
    const visible = state.category === 'Semua' ? PRODUCTS : PRODUCTS.filter((p) => p.category === state.category);
    const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    const add = (product) => { dispatch({ type: 'ADD', product }); emit('demo_product_added', { product_id: product.id }); };
    return <div className="flex h-full min-h-0 flex-col bg-[#cfeadb]">
        <header className="flex h-14 shrink-0 items-center gap-2 bg-gradient-to-r from-[#0a7a43] to-[#10975a] px-4 text-white"><img src="/images/brand/yoka-logo-white.png" alt="Yoka" className="h-6" /><b>POS</b><button onClick={() => dispatch({ type: 'MODE', mode: 'EXPLORE' })} className="ml-auto rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold hover:bg-white/25">Lihat perangkat</button><span className="hidden rounded-full bg-white/15 px-3 py-1.5 text-xs sm:inline">Demo aman · tanpa transaksi nyata</span></header>
        <div className="flex min-h-0 flex-1 flex-col lg:flex-row">
            <main className="flex min-h-0 flex-1 flex-col">
                <div className="flex shrink-0 gap-2 overflow-x-auto bg-white p-3">
                    {categories.map((category) => <button key={category} onClick={() => dispatch({ type: 'CATEGORY', category })} className={`h-10 shrink-0 rounded-full px-4 text-sm font-bold ${state.category === category ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'}`}>{category}</button>)}
                </div>
                <div className="grid min-h-0 flex-1 grid-cols-2 gap-3 overflow-y-auto p-3 sm:grid-cols-3 xl:grid-cols-4">
                    {visible.map((product) => <button key={product.id} onClick={() => add(product)} className="overflow-hidden rounded-2xl border border-emerald-700/20 bg-white text-left shadow-sm transition hover:-translate-y-0.5 hover:shadow-md active:scale-[.98]">
                        <img src={product.image} alt="" className="aspect-[5/3] w-full object-cover" />
                        <span className="block p-3"><b className="block text-sm text-slate-900">{product.name}</b><span className="mt-1 block text-sm font-extrabold text-emerald-700">{formatRupiah(product.price)}</span></span>
                    </button>)}
                </div>
            </main>
            <Cart cart={state.cart} total={total} changeQty={(id, delta) => dispatch({ type: 'QTY', id, delta })} onCheckout={() => { dispatch({ type: 'MODE', mode: 'CHECKOUT' }); emit('demo_checkout_started', { total }); }} />
        </div>
    </div>;
}

function Checkout({ state, dispatch }) {
    const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    return <div className="flex h-full items-center justify-center bg-slate-100 p-5">
        <div className="w-full max-w-md rounded-3xl bg-white p-6 shadow-xl sm:p-8">
            <button onClick={() => dispatch({ type: 'MODE', mode: 'DEMO' })} className="mb-5 text-sm font-bold text-slate-500">← Kembali ke pesanan</button>
            <p className="text-sm font-bold uppercase tracking-wider text-emerald-600">Pembayaran simulasi</p>
            <h3 className="mt-2 text-3xl font-extrabold text-[#0B1B3F]">{formatRupiah(total)}</h3>
            <div className="mt-6 grid grid-cols-2 gap-3">
                {['Tunai', 'QRIS'].map((payment) => <button key={payment} onClick={() => dispatch({ type: 'PAYMENT', payment })} className={`min-h-20 rounded-2xl border-2 px-4 font-extrabold ${state.payment === payment ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-600'}`}>{payment}</button>)}
            </div>
            <p className="mt-5 rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">Ini hanya simulasi. Tidak ada gateway pembayaran, stok, printer, atau laporan produksi yang diakses.</p>
            <button disabled={!state.payment} onClick={() => { dispatch({ type: 'MODE', mode: 'RECEIPT' }); emit('demo_completed', { payment_method: state.payment, total }); }} className="mt-5 min-h-12 w-full rounded-xl bg-emerald-600 font-extrabold text-white disabled:bg-slate-300">Selesaikan pembayaran</button>
        </div>
    </div>;
}

function ScreenReceipt({ state, dispatch, signupUrl }) {
    const total = state.cart.reduce((sum, item) => sum + item.price * item.qty, 0);
    return <div className="flex h-full flex-col items-center justify-center bg-[#e8f7ef] px-12 text-center text-[#0B1B3F]">
        <div className="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-600 text-white shadow-xl"><svg className="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path strokeLinecap="round" strokeLinejoin="round" d="m5 12 4 4L19 6" /></svg></div>
        <p className="mt-7 text-sm font-extrabold uppercase tracking-[.16em] text-emerald-700">Pembayaran {state.payment} berhasil</p>
        <h3 className="mt-2 text-4xl font-extrabold">{formatRupiah(total)}</h3>
        <p className="mt-3 max-w-lg text-lg text-slate-600">Struk sedang keluar dari printer. Semua perangkat tetap hanya menjalankan simulasi visual.</p>
        <div className="mt-7 flex gap-3">
            <button onClick={() => dispatch({ type: 'RESET' })} className="rounded-full border border-slate-300 bg-white px-6 py-3 text-base font-bold">Transaksi baru</button>
            <a href={signupUrl} onClick={() => emit('demo_signup_clicked')} className="rounded-full bg-emerald-600 px-6 py-3 text-base font-extrabold text-white">Coba Yoka gratis</a>
        </div>
    </div>;
}

const initialState = { mode: 'INTRO', cart: [], category: 'Semua', payment: '' };
function reducer(state, action) {
    if (action.type === 'MODE') return { ...state, mode: action.mode };
    if (action.type === 'CATEGORY') return { ...state, category: action.category };
    if (action.type === 'PAYMENT') return { ...state, payment: action.payment };
    if (action.type === 'ADD') { const found = state.cart.find((item) => item.id === action.product.id); return { ...state, cart: found ? state.cart.map((item) => item.id === action.product.id ? { ...item, qty: item.qty + 1 } : item) : [...state.cart, { ...action.product, qty: 1 }] }; }
    if (action.type === 'QTY') return { ...state, cart: state.cart.map((item) => item.id === action.id ? { ...item, qty: item.qty + action.delta } : item).filter((item) => item.qty > 0) };
    if (action.type === 'RESET') return { ...initialState, mode: 'RETURNING' };
    if (action.type === 'STORY_STAGE') {
        const firstItem = { ...PRODUCTS[0], qty: 1 };
        const secondItem = { ...PRODUCTS[4], qty: 1 };
        if (action.stage === 0) return { ...initialState };
        if (action.stage === 1) return { ...initialState, mode: 'FOCUS' };
        if (action.stage === 2) return { ...initialState, mode: 'DEMO' };
        if (action.stage === 3) return { ...initialState, mode: 'DEMO', cart: [firstItem] };
        if (action.stage === 4) return { ...initialState, mode: 'DEMO', cart: [firstItem, secondItem] };
        if (action.stage === 5) return { ...initialState, mode: 'CHECKOUT', cart: [firstItem, secondItem] };
        if (action.stage === 6) return { ...initialState, mode: 'CHECKOUT', cart: [firstItem, secondItem], payment: 'Tunai' };
        return { ...initialState, mode: 'RECEIPT', cart: [firstItem, secondItem], payment: 'Tunai' };
    }
    return state;
}

function StaticFallback({ onDemo }) {
    return <div className="absolute inset-0 flex items-center justify-center bg-[#f5f8f6] p-6"><div className="max-w-xl text-center"><img src="/images/photos/terminal-pos-premium.png" alt="Perangkat kasir Yoka POS" className="mx-auto max-h-[360px] object-contain" /><p className="mt-5 text-sm text-slate-600">Tampilan 3D tidak didukung di perangkat ini. Demo kasir tetap dapat digunakan.</p><button onClick={onDemo} className="mt-5 min-h-12 rounded-full bg-emerald-600 px-6 font-extrabold text-white">Buka demo kasir</button></div></div>;
}

function Experience({ signupUrl }) {
    const [state, dispatch] = useReducer(reducer, initialState);
    const [hasWebgl] = useState(webglAvailable);
    const [hardwareDetail, setHardwareDetail] = useState('');
    const [receiptInteractive, setReceiptInteractive] = useState(false);
    const [storyStage, setStoryStage] = useState(0);
    const [autoPaused, setAutoPaused] = useState(false);
    const reducedMotion = useMemo(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches, []);
    const focusTimer = useRef(null);
    useEffect(() => { emit('3d_experience_viewed'); return () => clearTimeout(focusTimer.current); }, []);
    useEffect(() => {
        if (state.mode !== 'RECEIPT') { setReceiptInteractive(false); return undefined; }
        setReceiptInteractive(false);
        const timer = window.setTimeout(() => setReceiptInteractive(true), reducedMotion ? 0 : 1700);
        return () => window.clearTimeout(timer);
    }, [state.mode, reducedMotion]);
    useEffect(() => {
        if (autoPaused || reducedMotion) return undefined;
        const durations = [1800, 1300, 1900, 1800, 1800, 1600, 1400, 4200];
        const timer = window.setTimeout(() => {
            const nextStage = (storyStage + 1) % 8;
            setStoryStage(nextStage);
            dispatch({ type: 'STORY_STAGE', stage: nextStage });
        }, durations[storyStage]);
        return () => window.clearTimeout(timer);
    }, [storyStage, autoPaused, reducedMotion]);
    useEffect(() => {
        if (state.mode !== 'RETURNING') return undefined;
        const timer = window.setTimeout(() => dispatch({ type: 'MODE', mode: 'DEMO' }), reducedMotion ? 0 : 1250);
        return () => window.clearTimeout(timer);
    }, [state.mode, reducedMotion]);
    const setSceneMode = (mode) => {
        setHardwareDetail('');
        dispatch({ type: 'MODE', mode });
        if (mode === 'FOCUS') { clearTimeout(focusTimer.current); focusTimer.current = setTimeout(() => { dispatch({ type: 'MODE', mode: 'DEMO' }); emit('demo_started'); }, reducedMotion ? 0 : 850); }
    };
    const showSceneControls = ['INTRO', 'EXPLORE', 'FOCUS'].includes(state.mode);
    const guideStage = state.mode === 'RECEIPT' ? 7
        : state.mode === 'CHECKOUT' ? (state.payment ? 6 : 5)
            : state.mode === 'DEMO' ? (state.cart.length >= 2 ? 4 : state.cart.length === 1 ? 3 : 2)
                : state.mode === 'FOCUS' || state.mode === 'RETURNING' ? 1 : storyStage;
    const guide = [
        ['Kenali perangkat', 'Geser hardware untuk melihatnya dari sisi lain.'],
        ['Masuk ke kasir', 'Kamera mengarah ke layar POS.'],
        ['Mulai transaksi', 'Pilih produk langsung pada layar monitor.'],
        ['Produk ditambahkan', 'Es Kopi Susu masuk ke panel pesanan.'],
        ['Lengkapi pesanan', 'Croissant ditambahkan sebagai pendamping.'],
        ['Lanjut pembayaran', 'Kasir menghitung total secara otomatis.'],
        ['Pilih Tunai', 'Laci kas akan terbuka setelah transaksi selesai.'],
        ['Struk tercetak', 'Cubit atau scroll untuk memeriksa isi struk.'],
    ][guideStage];
    const guidePresentation = [
        { position: 'left-1/2 top-8 -translate-x-1/2 text-center', color: '#ffffff' },
        { position: 'left-1/2 top-[14%] -translate-x-1/2 text-center', color: '#86efac' },
        { position: 'left-6 top-[18%] text-left sm:left-10 lg:left-[8%]', color: '#ffffff' },
        { position: 'right-6 top-[22%] text-right sm:right-10 lg:right-[8%]', color: '#fef3c7' },
        { position: 'right-6 top-[42%] -translate-y-1/2 text-right sm:right-10 lg:right-[7%]', color: '#bbf7d0' },
        { position: 'left-1/2 bottom-[14%] -translate-x-1/2 text-center', color: '#ffffff' },
        { position: 'left-1/2 top-[16%] -translate-x-1/2 text-center', color: '#fde68a' },
        { position: 'left-5 top-[22%] text-left sm:left-10 lg:left-[7%]', color: '#a7f3d0' },
    ][guideStage];
    return <div className="relative h-[580px] min-h-[580px] overflow-hidden bg-[#2b241f] text-slate-900 sm:h-[680px] sm:min-h-[680px] lg:h-[760px] lg:min-h-[760px]" onPointerDownCapture={() => setAutoPaused(true)} onKeyDownCapture={() => setAutoPaused(true)}>
        <img src="/images/3d/yokapos-cafe-backdrop.webp" alt="" className="pointer-events-none absolute inset-0 h-full w-full object-cover" />
        <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-slate-950/5 via-transparent to-slate-950/20" />
        {hasWebgl ? <Canvas dpr={[1, 1.6]} camera={{ position: [0, 0.34, 0.72], fov: 38 }} frameloop="always" gl={{ antialias: true, alpha: true, powerPreference: 'high-performance' }}><Scene state={state} dispatch={dispatch} setMode={setSceneMode} reducedMotion={reducedMotion} onDetail={setHardwareDetail} signupUrl={signupUrl} receiptInteractive={receiptInteractive} focusStage={guideStage} /></Canvas> : (showSceneControls ? <StaticFallback onDemo={() => setSceneMode('FOCUS')} /> : null)}
        <div key={guideStage} className={`pointer-events-none absolute z-20 max-w-[86%] ${guidePresentation.position}`} style={{ color: guidePresentation.color }}>
            <p className="text-[28px] font-black leading-[1.02] sm:text-[38px] lg:text-[52px]">
                {guide[0].split(' ').map((word, index) => <span key={`${word}-${index}`} className="yoka-guide-word mr-[.3em] inline-block" style={{ '--word-delay': `${index * 90}ms` }}>{word}</span>)}
            </p>
            <p className="yoka-guide-detail mx-auto mt-2 hidden max-w-lg text-sm font-extrabold leading-relaxed opacity-90 sm:block lg:text-lg">{autoPaused ? guide[1] : `${guide[1]} Sentuh perangkat untuk mencoba.`}</p>
        </div>
        {hasWebgl && state.mode === 'RECEIPT' && <div className="yoka-experience-enter absolute bottom-3 left-3 right-3 z-20 rounded-xl border border-emerald-200 bg-white/95 p-3 shadow-2xl backdrop-blur sm:bottom-5 sm:left-auto sm:right-5 sm:w-[360px] sm:rounded-2xl sm:p-5">
            <p className="text-[10px] font-extrabold uppercase tracking-[.14em] text-emerald-700 sm:text-xs">Transaksi berhasil</p>
            <p className="mt-1 text-base font-extrabold text-[#0B1B3F] sm:text-xl">Struk sedang dicetak</p>
            <p className="mt-1 text-xs text-slate-600 sm:mt-2 sm:text-sm">{receiptInteractive ? 'Geser atau cubit untuk memperbesar struk.' : 'Kamera sedang mengarah ke struk…'}</p>
            <div className="mt-3 flex gap-2 sm:mt-4"><button onClick={() => dispatch({ type: 'RESET' })} className="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-bold sm:px-4 sm:py-2 sm:text-sm">Transaksi baru</button><a href={signupUrl} onClick={() => emit('demo_signup_clicked')} className="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-extrabold text-white sm:px-4 sm:py-2 sm:text-sm">Coba Yoka</a></div>
        </div>}
        {showSceneControls && hardwareDetail && <div role="status" className="absolute right-4 top-4 max-w-xs rounded-xl border border-slate-200 bg-white/90 px-4 py-3 text-sm font-bold text-slate-800 shadow-xl backdrop-blur">{hardwareDetail}</div>}
        {!hasWebgl && state.mode === 'DEMO' && <CashierDemo state={state} dispatch={dispatch} />}
        {!hasWebgl && state.mode === 'CHECKOUT' && <Checkout state={state} dispatch={dispatch} />}
        {!hasWebgl && state.mode === 'RECEIPT' && <ScreenReceipt state={state} dispatch={dispatch} signupUrl={signupUrl} />}
    </div>;
}

export function mountYokaProductExperience(element) {
    createRoot(element).render(<Experience signupUrl={element.dataset.signupUrl || '/register'} />);
}

useGLTF.preload('/models/yokapos-full-set.glb');
