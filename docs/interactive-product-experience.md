# YokaPOS interactive product experience

The landing-page demo is mounted by `resources/js/app.js` only when its section is within 500px of the viewport. All cart, payment, and receipt state stays in React memory. It does not call POS routes, payment gateways, inventory APIs, tenant data, or printer APIs.

## 3D assets

The browser loads the deploy-compatible copy at `public/images/3d/yokapos-full-set.glb`; canonical generated models remain in `public/models`. The procedural source is `tools/build-pos-models.mjs`; run `node tools/build-pos-models.mjs` after changing dimensions or materials. It produces:

- `yokapos-terminal.glb` — 370 × 340 × 190 mm assembled terminal envelope
- `yokapos-drawer.glb` — 410 × 100 × 420 mm
- `yokapos-printer.glb` — 145 × 135 × 190 mm
- `yokapos-scanner.glb` — approximately 150 × 260 × 160 mm
- `yokapos-full-set.glb` — the landing-page assembly

The source uses meters, +Y up, +Z front, and a centered assembled root. It is procedural and editable without Blender. A `.blend` file is not included because Blender was not available in the build environment; the generator is the authoritative editable source.

### Interactive nodes and pivots

- `Terminal_Screen`: separate plane with predictable default plane UVs; clicking enters the HTML cashier demo.
- `Drawer_Inner`: separate group at the drawer origin; translate on local +Z up to 0.31 m.
- `Printer_Paper`: separate subdivided plane at the output slot; animate local Y scale.
- `Scanner_Trigger`: separate trigger mesh; clicking emits a simulated scan event.

Static supporting nodes retain the names from the modeling specification, including `Terminal_Body`, `Terminal_Bezel`, `Terminal_Back`, `Terminal_Stand`, `Terminal_Base`, `Drawer_Body`, `Drawer_Front`, `Drawer_Lock`, `Printer_Body`, `Printer_Top`, `Printer_Slot`, `Printer_Buttons`, `Scanner_Body`, `Scanner_Glass`, and `Scanner_Stand`.

### Camera recommendations

- Three-quarter presentation: position `[0.72, 0.48, 0.90]`, target `[0, 0.25, 0]`, FOV 38°.
- Screen focus: position `[0, 0.32, 0.72]`, target `[0, 0.25, 0]`, FOV 38°.
- Orbit distance is constrained to 0.62–1.35 m to avoid clipping through the hardware.

### Modeling assumptions

The supplied 550 × 479 reference photograph is the visual proportion reference. Exact manufacturer dimensions were unavailable. Bevels, stand curvature, scanner proportions, and component spacing are presentation-focused rather than CAD-accurate. Materials use solid PBR colors, so the GLBs have no missing external texture dependencies.

### Validation

The complete GLB is about 1.0 MB and each individual asset is below 350 KB. Required interactive node names were verified in every relevant GLB. Production Vite builds load the full set through Drei `useGLTF`. Browser screenshots from multiple angles are not committed because this environment had no browser capture or Blender renderer; capture front and three-quarter screenshots during visual QA on the target browsers.

## Analytics hooks

Events are emitted as browser events prefixed with `yoka:` and pushed to `window.dataLayer` when available: `3d_experience_viewed`, `device_rotated`, `demo_started`, `demo_product_added`, `demo_checkout_started`, `demo_completed`, and `demo_signup_clicked`. No customer or payment data is included.

## Safety and fallback

WebGL capability is checked before creating the scene. Unsupported browsers receive a static terminal image and can still open the HTML demo. Reduced-motion preferences skip the camera and receipt animations. The cashier UI uses native buttons and large touch targets.
