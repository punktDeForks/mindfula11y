import { unsafeCSS } from 'lit';
export default unsafeCSS("@layer component{.disclosure{cursor:pointer;list-style:none;&::-webkit-details-marker{display:none}}[open]>.disclosure .marker{rotate:180deg}}");
