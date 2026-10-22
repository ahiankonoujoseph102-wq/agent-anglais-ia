// Parties de three.js utilisées par public/js/teacher-3d.js (regroupées par build-vendor.mjs).
// WebGLRenderTarget : image de Teacher Joe pour « Enregistrer ma séance » (caméra de la vidéo).
export {
    BoxGeometry, CanvasTexture, Color, CylinderGeometry, DirectionalLight, Euler, Group, HemisphereLight,
    Mesh, MeshLambertMaterial, PerspectiveCamera, PlaneGeometry, Quaternion, Scene, SphereGeometry,
    SRGBColorSpace, WebGLRenderer, WebGLRenderTarget,
} from 'three';
export { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
export { MeshoptDecoder } from 'three/addons/libs/meshopt_decoder.module.js';
