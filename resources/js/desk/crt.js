// Экран кинескопа: картинка вписывается без обрезки, сверху скан-линии, виньетка и «снег».
import * as THREE from 'three';

export function crtMaterial(initialMap, screenAspect) {
    return new THREE.ShaderMaterial({
        toneMapped: false,
        uniforms: {
            map: { value: initialMap },
            mapAspect: { value: 4 / 3 },
            screenAspect: { value: screenAspect },
            time: { value: 0 },
            power: { value: 1 },
            noise: { value: 0.06 },
            brightness: { value: 1.05 },
        },
        vertexShader: /* glsl */ `
            varying vec2 vUv;
            void main() {
                vUv = uv;
                gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
            }
        `,
        fragmentShader: /* glsl */ `
            uniform sampler2D map;
            uniform float mapAspect;
            uniform float screenAspect;
            uniform float time;
            uniform float power;
            uniform float noise;
            uniform float brightness;
            varying vec2 vUv;

            float hash(vec2 p) { return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453); }

            void main() {
                // выпуклость стекла
                vec2 cc = vUv - 0.5;
                float d = dot(cc, cc);
                vec2 uv = 0.5 + cc * (1.0 + d * 0.18);

                // включение: белая полоса, которая раскрывается по вертикали
                float open = smoothstep(0.0, 1.0, power);
                float band = abs(uv.y - 0.5) * 2.0;
                float visible = step(band, max(open, 0.012)) * step(abs(uv.x - 0.5) * 2.0, min(1.0, power * 6.0));

                // вписываем картинку (contain), остальное — чёрные поля
                vec2 m = uv;
                float r = mapAspect / screenAspect;
                if (r > 1.0) { m.y = (m.y - 0.5) * r + 0.5; } else { m.x = (m.x - 0.5) / r + 0.5; }
                float inside = step(0.0, m.x) * step(m.x, 1.0) * step(0.0, m.y) * step(m.y, 1.0);
                vec3 col = texture2D(map, clamp(m, 0.0, 1.0)).rgb * inside;

                float n = hash(uv * vec2(640.0, 480.0) + fract(time * 13.0));
                col = mix(col, vec3(n), noise);
                col *= 0.86 + 0.14 * sin(uv.y * 900.0 + time * 4.0);
                col *= 1.0 - smoothstep(0.18, 0.62, d * 2.2);
                col *= brightness;
                col += vec3(1.0) * (1.0 - open) * visible * 0.9;

                float edge = step(0.0, uv.x) * step(uv.x, 1.0) * step(0.0, uv.y) * step(uv.y, 1.0);
                gl_FragColor = vec4(col * visible * edge, 1.0);
                #include <colorspace_fragment>
            }
        `,
    });
}
