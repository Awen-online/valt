// regal-particles.js
// import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.158.0/build/three.module.js';
// import { OrbitControls } from 'https://cdn.jsdelivr.net/npm/three@0.158.0/examples/jsm/controls/OrbitControls.js';

let scene, camera, renderer, particles;

const particleCount = 1000;
const colors = [
    new THREE.Color('#EBC48B'), // Primary
    new THREE.Color('#817269'), // Secondary
    new THREE.Color('#C5AD90'), // Third
    new THREE.Color('#49303C'), // Accent
    new THREE.Color('#FF9191'), // Custom
    new THREE.Color('#2F3D60')  // Custom (background)
];

// Rest of the script remains the same...
function init() {
    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.set(0, 50, 100);
    camera.lookAt(0, 0, 0);

    renderer = new THREE.WebGLRenderer();
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.body.appendChild(renderer.domElement);
    document.body.style.margin = '0';
    document.body.style.overflow = 'hidden';
    document.body.style.backgroundColor = '#2F3D60';

    const geometry = new THREE.BufferGeometry();
    const positions = new Float32Array(particleCount * 3);
    const colorsArray = new Float32Array(particleCount * 3);
    const sizes = new Float32Array(particleCount);

    for (let i = 0; i < particleCount; i++) {
        positions[i * 3] = (Math.random() - 0.5) * 200;
        positions[i * 3 + 1] = (Math.random() - 0.5) * 200;
        positions[i * 3 + 2] = (Math.random() - 0.5) * 200;
        const colorIdx = Math.floor(Math.random() * colors.length);
        colors[colorIdx].toArray(colorsArray, i * 3);
        sizes[i] = 10 + Math.random() * 10;
    }

    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    geometry.setAttribute('customColor', new THREE.BufferAttribute(colorsArray, 3));
    geometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1));

    const material = new THREE.ShaderMaterial({
        uniforms: {
            time: { value: 0 },
            pointTexture: { value: new THREE.TextureLoader().load('https://threejs.org/examples/textures/sprites/circle.png') }
        },
        vertexShader: `
            attribute vec3 customColor;
            attribute float size;
            varying vec3 vColor;
            uniform float time;
            void main() {
                vColor = customColor;
                vec4 mvPosition = modelViewMatrix * vec4(position, 1.0);
                gl_PointSize = size * (300.0 / -mvPosition.z) * (sin(time + position.x) * 0.5 + 1.0);
                gl_Position = projectionMatrix * mvPosition;
            }
        `,
        fragmentShader: `
            uniform sampler2D pointTexture;
            varying vec3 vColor;
            void main() {
                gl_FragColor = vec4(vColor, 1.0) * texture2D(pointTexture, gl_PointCoord);
                if (gl_FragColor.a < 0.5) discard;
            }
        `,
        transparent: true,
        blending: THREE.AdditiveBlending,
        depthTest: false
    });

    particles = new THREE.Points(geometry, material);
    scene.add(particles);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });

    animate();
}

function animate() {
    requestAnimationFrame(animate);
    particles.material.uniforms.time.value += 0.02;
    particles.rotation.y += 0.001;
    renderer.render(scene, camera);
}

init();