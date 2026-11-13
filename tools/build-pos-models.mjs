import fs from 'node:fs/promises';
import path from 'node:path';
import * as THREE from 'three';
import { GLTFExporter } from 'three/examples/jsm/exporters/GLTFExporter.js';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';

// GLTFExporter targets browsers; this minimal build-only shim lets it serialize
// Blob buffers in Node without adding another production dependency.
globalThis.FileReader ??= class FileReader {
    readAsArrayBuffer(blob) {
        blob.arrayBuffer().then((value) => { this.result = value; this.onloadend?.(); });
    }

    readAsDataURL(blob) {
        blob.arrayBuffer().then((value) => {
            this.result = `data:${blob.type};base64,${Buffer.from(value).toString('base64')}`;
            this.onloadend?.();
        });
    }
};

const outputDir = path.resolve('public/models');
const blackPlastic = new THREE.MeshStandardMaterial({ name: 'Matte_Black_ABS', color: 0x15191e, roughness: 0.78, metalness: 0.05 });
const charcoal = new THREE.MeshStandardMaterial({ name: 'Charcoal_Bezel', color: 0x090b0e, roughness: 0.68, metalness: 0.08 });
const metal = new THREE.MeshStandardMaterial({ name: 'Powder_Coated_Metal', color: 0x11161b, roughness: 0.82, metalness: 0.16 });
const glass = new THREE.MeshStandardMaterial({ name: 'Display_Glass', color: 0x123a36, roughness: 0.24, metalness: 0.05, emissive: 0x092b25, emissiveIntensity: 0.35 });
const paper = new THREE.MeshStandardMaterial({ name: 'Warm_Receipt_Paper', color: 0xfffdf4, roughness: 0.96, metalness: 0 });
const scannerGlass = new THREE.MeshStandardMaterial({ name: 'Scanner_Glass', color: 0x173c4b, roughness: 0.18, metalness: 0.12 });
const green = new THREE.MeshStandardMaterial({ name: 'Yoka_Green', color: 0x16a34a, roughness: 0.55, metalness: 0.03 });

function mesh(name, geometry, material, position = [0, 0, 0], rotation = [0, 0, 0]) {
    geometry.computeVertexNormals();
    const value = new THREE.Mesh(geometry, material);
    value.name = name;
    value.position.set(...position);
    value.rotation.set(...rotation);
    value.castShadow = true;
    value.receiveShadow = true;
    return value;
}

function roundedBox(width, height, depth, radius = 0.006, segments = 3) {
    return new RoundedBoxGeometry(width, height, depth, segments, Math.min(radius, width / 4, height / 4, depth / 4));
}

function terminal() {
    const root = new THREE.Group(); root.name = 'YokaPOS_Terminal';
    root.userData = { units: 'meters', width: 0.37, height: 0.34, depth: 0.19, front: '+Z' };
    const tilt = -0.07;
    const screenGroup = new THREE.Group(); screenGroup.name = 'Terminal_Display_Assembly';
    screenGroup.position.set(0, 0.225, 0.005); screenGroup.rotation.x = tilt;
    screenGroup.add(mesh('Terminal_Back', roundedBox(0.378, 0.238, 0.032, 0.012), blackPlastic));
    screenGroup.add(mesh('Terminal_Body', roundedBox(0.372, 0.232, 0.029, 0.011), blackPlastic, [0, 0, 0.003]));
    screenGroup.add(mesh('Terminal_Bezel', roundedBox(0.366, 0.226, 0.009, 0.01), charcoal, [0, 0, 0.019]));
    screenGroup.add(mesh('Terminal_Screen', new THREE.PlaneGeometry(0.334, 0.188, 1, 1), glass, [0, 0, 0.024]));
    screenGroup.add(mesh('Terminal_Logo', roundedBox(0.035, 0.007, 0.001, 0.001), green, [0, -0.105, 0.0245]));
    root.add(screenGroup);
    root.add(mesh('Terminal_Stand', roundedBox(0.075, 0.11, 0.065, 0.008), metal, [0, 0.085, -0.035], [-0.1, 0, 0]));
    root.add(mesh('Terminal_Base', roundedBox(0.23, 0.018, 0.16, 0.007), metal, [0, 0.015, -0.025]));
    return root;
}

function drawer() {
    const root = new THREE.Group(); root.name = 'YokaPOS_Drawer';
    root.userData = { units: 'meters', width: 0.41, height: 0.10, depth: 0.42, animationAxis: '+Z' };
    root.add(mesh('Drawer_Body', roundedBox(0.41, 0.10, 0.42, 0.008), metal, [0, 0.05, 0]));
    const moving = new THREE.Group(); moving.name = 'Drawer_Inner'; moving.userData = { pivot: 'rear-center', animation: 'translate local +Z', maxTravel: 0.31 };
    moving.add(mesh('Drawer_Tray', roundedBox(0.388, 0.065, 0.37, 0.004), charcoal, [0, 0.052, 0.012]));
    moving.add(mesh('Drawer_Front', roundedBox(0.398, 0.086, 0.018, 0.004), blackPlastic, [0, 0.05, 0.218]));
    moving.add(mesh('Drawer_Document_Slot', roundedBox(0.19, 0.006, 0.003, 0.001), charcoal, [-0.045, 0.062, 0.228]));
    moving.add(mesh('Drawer_Lock', new THREE.CylinderGeometry(0.012, 0.012, 0.008, 24), metal, [0.13, 0.052, 0.23], [Math.PI / 2, 0, 0]));
    root.add(moving);
    return root;
}

function printer() {
    const root = new THREE.Group(); root.name = 'YokaPOS_Printer';
    root.userData = { units: 'meters', width: 0.145, height: 0.135, depth: 0.19, paperAxis: '+Y' };
    root.add(mesh('Printer_Body', roundedBox(0.145, 0.12, 0.19, 0.012), blackPlastic, [0, 0.06, 0]));
    root.add(mesh('Printer_Top', roundedBox(0.133, 0.025, 0.16, 0.01), charcoal, [0, 0.126, -0.008], [-0.04, 0, 0]));
    root.add(mesh('Printer_Slot', roundedBox(0.105, 0.006, 0.014, 0.002), charcoal, [0, 0.139, 0.045]));
    root.add(mesh('Printer_Buttons', new THREE.CylinderGeometry(0.005, 0.005, 0.003, 16), blackPlastic, [0.045, 0.137, 0.067]));
    root.add(mesh('Printer_Status', new THREE.SphereGeometry(0.003, 12, 8), green, [0.057, 0.139, 0.066]));
    const receipt = mesh('Printer_Paper', new THREE.PlaneGeometry(0.096, 0.12, 1, 5), paper, [0, 0.192, 0.048], [-0.08, 0, 0]);
    receipt.userData = { pivot: 'output-slot', animation: 'scale local Y from 0.02 to 1' };
    root.add(receipt);
    return root;
}

function scanner() {
    const root = new THREE.Group(); root.name = 'YokaPOS_Scanner';
    root.userData = { units: 'meters', width: 0.15, height: 0.26, depth: 0.16 };
    root.add(mesh('Scanner_Stand_Base', roundedBox(0.12, 0.018, 0.105, 0.009), metal, [0, 0.009, 0]));
    root.add(mesh('Scanner_Stand', new THREE.TorusGeometry(0.105, 0.009, 10, 28, Math.PI * 0.67), metal, [0.015, 0.105, 0], [0, Math.PI / 2, -0.22]));
    const body = new THREE.Group(); body.name = 'Scanner_Body'; body.position.set(0.028, 0.225, 0.016); body.rotation.set(-0.35, 0, -0.35);
    body.add(mesh('Scanner_Head', roundedBox(0.09, 0.05, 0.075, 0.014), blackPlastic));
    body.add(mesh('Scanner_Handle', roundedBox(0.038, 0.12, 0.045, 0.012), blackPlastic, [-0.005, -0.072, -0.006], [0, 0, -0.16]));
    body.add(mesh('Scanner_Glass', roundedBox(0.068, 0.03, 0.004, 0.006), scannerGlass, [0, 0.005, 0.04]));
    const trigger = mesh('Scanner_Trigger', roundedBox(0.022, 0.03, 0.006, 0.003), green, [0.019, -0.065, 0.02], [0, 0, -0.16]);
    trigger.userData = { interaction: 'simulated-scan' };
    body.add(trigger); root.add(body);
    return root;
}

function fullSet() {
    const root = new THREE.Group(); root.name = 'YokaPOS_Full_Set'; root.userData = { units: 'meters', front: '+Z', up: '+Y', reference: 'supplied POS equipment photo' };
    const cashDrawer = drawer(); cashDrawer.position.set(0, 0, 0); root.add(cashDrawer);
    const display = terminal(); display.position.set(0, 0.105, 0.005); root.add(display);
    const thermal = printer(); thermal.position.set(-0.31, 0, 0.075); root.add(thermal);
    const barcode = scanner(); barcode.position.set(0.31, 0, 0.07); root.add(barcode);
    return root;
}

async function exportBinary(object, filename) {
    object.traverse((item) => { if (item.isMesh) { item.geometry.computeBoundingBox(); item.geometry.computeBoundingSphere(); } });
    const exporter = new GLTFExporter();
    const result = await exporter.parseAsync(object, { binary: true, onlyVisible: true, truncateDrawRange: true });
    await fs.writeFile(path.join(outputDir, filename), Buffer.from(result));
}

await fs.mkdir(outputDir, { recursive: true });
await exportBinary(terminal(), 'yokapos-terminal.glb');
await exportBinary(drawer(), 'yokapos-drawer.glb');
await exportBinary(printer(), 'yokapos-printer.glb');
await exportBinary(scanner(), 'yokapos-scanner.glb');
await exportBinary(fullSet(), 'yokapos-full-set.glb');
console.log('Generated YokaPOS GLB assets in public/models');
