import * as THREE from 'three';
import { WebGPURenderer } from 'three/webgpu';
import { MeshBasicNodeMaterial } from 'three/webgpu';
import * as culori from 'culori';
import { main } from './shaderNode.js';
import {
    u_time, u_resolution, u_mouse, u_speed,
    u_color1, u_color2, u_color3, u_color4,
    u_brightness, u_contrast, u_noise, u_wave_amplitude, u_wave_frequency
} from './commonUniforms.js';

class WebGPUGradient {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        this.init();
    }

    async init() {
        this.scene = new THREE.Scene();
        this.camera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 10);
        this.camera.position.z = 1;

        // WebGPU Renderer
        this.renderer = new WebGPURenderer({
            canvas: this.canvas,
            antialias: true,
            alpha: false
        });
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        try {
            await this.renderer.init();
        } catch (error) {
            console.error('Failed to initialize WebGPURenderer:', error);
            return;
        }

        // Setup Uniforms
        u_speed.value = 0.5;
        u_color1.value = this.oklchToThree({ l: 0.670, c: 0.130, h: 73.0 });
        u_color2.value = this.oklchToThree({ l: 0.710, c: 0.152, h: 229.0 });
        u_color3.value = this.oklchToThree({ l: 0.600, c: 0.196, h: 76.0 });
        u_color4.value = this.oklchToThree({ l: 0.660, c: 0.243, h: 304.0 });

        u_brightness.value = 0;
        u_contrast.value = 1;
        u_noise.value = 0;
        u_wave_amplitude.value = 1.2;
        u_wave_frequency.value = 2.5;

        // Material
        const material = new MeshBasicNodeMaterial();
        material.colorNode = main();

        const geometry = new THREE.PlaneGeometry(2, 2);
        this.mesh = new THREE.Mesh(geometry, material);
        this.scene.add(this.mesh);

        this.clock = new THREE.Clock();
        window.addEventListener('resize', this.onResize.bind(this));
        this.onResize();

        window.addEventListener('pointermove', (event) => {
            if (!this.canvas) return;
            const rect = this.canvas.getBoundingClientRect();
            const w = Math.max(rect.width, 1);
            const h = Math.max(rect.height, 1);
            const x = (event.clientX - rect.left) / w;
            const y = 1 - (event.clientY - rect.top) / h;
            u_mouse.value.set(x, y);
        }, { passive: true });

        this.animate();
    }

    oklchToThree(oklch) {
        const rgb = culori.rgb({ mode: 'oklch', ...oklch });
        return new THREE.Color(rgb.r, rgb.g, rgb.b);
    }

    onResize() {
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        const pixelRatio = this.renderer.getPixelRatio();
        u_resolution.value.set(window.innerWidth * pixelRatio, window.innerHeight * pixelRatio);
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        u_time.value = this.clock.getElapsedTime();
        this.renderer.render(this.scene, this.camera);
    }
}

new WebGPUGradient('gradient-canvas');
